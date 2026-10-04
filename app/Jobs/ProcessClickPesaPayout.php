<?php

namespace App\Jobs;

use App\Models\RiderDeliveryFinancial;
use App\Models\SellerOrderFinancial;
use App\Models\User;
use App\Services\ClickPesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Submit one payout while respecting ClickPesa's merchant request limit. */
class ProcessClickPesaPayout implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 100;

    public function __construct(public string $type, public int $financialId, public int $userId) {}

    public function middleware(): array
    {
        return [(new RateLimited('clickpesa-payouts'))->releaseAfter(65)];
    }

    public function handle(ClickPesaService $clickPesa): void
    {
        $financial = $this->financial();
        if (! $financial || $financial->payout_status !== 'queued') {
            return;
        }
        $destination = User::with('payoutDestination')->find($this->userId)?->payoutDestination;
        if (! $destination) {
            $financial->update(['payout_status' => 'pending', 'failure_reason' => 'Add a payout destination to continue.']);
            return;
        }

        $amount = $this->type === 'seller'
            ? (float) $financial->seller_payout_amount
            : (float) $financial->rider_payout_amount;
        $reference = (string) $financial->provider_reference;
        if ($amount <= 0 || ! $reference) {
            $financial->update(['payout_status' => 'failed', 'failure_reason' => 'The payout amount or reference is invalid.']);
            return;
        }

        try {
            $payload = ['amount' => number_format($amount, 2, '.', ''), 'order_reference' => $reference, 'currency' => 'TZS'];
            if ($destination->payout_method === 'mobile_money') {
                if (! $destination->verified_at) {
                    $financial->update(['payout_status' => 'pending']);
                    return;
                }
                $payload['phone_number'] = $destination->mobile_phone;
                $clickPesa->previewMobilePayout($payload);
                $result = $clickPesa->createMobilePayout($payload);
            } else {
                $payload += [
                    'account_number' => $destination->bank_account_number,
                    'account_name' => $destination->bank_account_name,
                    'bic' => $destination->bank_bic,
                ];
                $preview = $clickPesa->previewBankPayout($payload);
                $resolved = strtoupper((string) data_get($preview, 'nameLookupStatus', data_get($preview, 'data.nameLookupStatus', '')));
                if (! in_array($resolved, ['RESOLVED', 'UNAVAILABLE'], true)) {
                    $financial->update(['payout_status' => 'failed', 'failure_reason' => 'ClickPesa could not verify the bank account name. Check the saved account details.']);
                    return;
                }
                $providerName = trim((string) data_get($preview, 'receiver.accountName', ''));
                if ($resolved === 'RESOLVED' && $providerName !== ''
                    && mb_strtolower($providerName) !== mb_strtolower(trim((string) $destination->bank_account_name))) {
                    $financial->update(['payout_status' => 'failed', 'failure_reason' => 'The entered bank account name does not match the name returned by ClickPesa.']);
                    return;
                }
                $result = $clickPesa->createBankPayout($payload);
            }

            $this->applyStatus($financial, $result);
            if ($financial->payout_status === 'processing') {
                CheckClickPesaPayoutStatus::dispatch($reference)->delay(now()->addMinute())->onQueue('payouts');
            }
        } catch (Throwable $exception) {
            // Reusing the same unique reference makes a retry safe if the provider accepted
            // the transfer but the HTTP response was lost.
            try {
                $existing = $clickPesa->queryPayoutStatus($reference);
                $this->applyStatus($financial, $existing);
                if ($financial->payout_status === 'processing') {
                    CheckClickPesaPayoutStatus::dispatch($reference)->delay(now()->addMinute())->onQueue('payouts');
                }
            } catch (Throwable $queryException) {
                $financial->update([
                    // Keep the same reference while retrying: the original payout may
                    // have been accepted even though both HTTP responses were lost.
                    'payout_status' => 'queued',
                    'failure_reason' => 'Waiting to confirm the ClickPesa payout; the same reference will be retried safely.',
                ]);
                $this->release(300);
                Log::error('ClickPesa payout submission could not be reconciled.', [
                    'reference' => $reference,
                    'exception' => $exception::class,
                    'query_exception' => $queryException::class,
                ]);
            }
        }
    }

    private function financial(): SellerOrderFinancial|RiderDeliveryFinancial|null
    {
        return $this->type === 'seller'
            ? SellerOrderFinancial::find($this->financialId)
            : RiderDeliveryFinancial::find($this->financialId);
    }

    private function applyStatus(SellerOrderFinancial|RiderDeliveryFinancial $financial, array $response): void
    {
        $status = strtoupper((string) data_get($response, 'status', data_get($response, 'data.status', 'PROCESSING')));
        $transactionId = data_get($response, 'id', data_get($response, 'data.id'));
        $updates = ['provider_transaction_id' => $transactionId];
        if (in_array($status, ['SUCCESS', 'COMPLETED', 'PAID'], true)) {
            $updates += ['payout_status' => 'paid', 'paid_at' => $financial->paid_at ?? now(), 'failure_reason' => null];
        } elseif (in_array($status, ['REFUNDED', 'REVERSED', 'FAILED'], true)) {
            $updates += ['payout_status' => 'failed', 'failure_reason' => 'ClickPesa reported payout status '.$status.'.'];
        } else {
            $updates['payout_status'] = 'processing';
        }
        $financial->update($updates);
    }

    public function failed(Throwable $exception): void
    {
        $financial = $this->financial();
        if ($financial && ! in_array($financial->payout_status, ['paid', 'processing'], true)) {
            $financial->update(['payout_status' => 'failed', 'failure_reason' => 'Payout job failed. Check ClickPesa before retrying.']);
        }
    }
}
