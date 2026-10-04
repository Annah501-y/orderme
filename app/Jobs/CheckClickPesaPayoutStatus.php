<?php

namespace App\Jobs;

use App\Models\RiderDeliveryFinancial;
use App\Models\SellerOrderFinancial;
use App\Services\ClickPesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Reconcile provider callbacks against ClickPesa's authenticated status API. */
class CheckClickPesaPayoutStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Keep checking until ClickPesa returns a final payout state.
    public int $tries = 0;

    public function __construct(public string $reference) {}

    public function handle(ClickPesaService $clickPesa): void
    {
        $financial = SellerOrderFinancial::where('provider_reference', $this->reference)->first()
            ?? RiderDeliveryFinancial::where('provider_reference', $this->reference)->first();
        if (! $financial || $financial->payout_status === 'paid') {
            return;
        }

        try {
            $response = $clickPesa->queryPayoutStatus($this->reference);
        } catch (Throwable $exception) {
            $this->release(60);
            return;
        }

        $status = strtoupper((string) data_get($response, 'status', data_get($response, 'data.status', 'PROCESSING')));
        $updates = ['provider_transaction_id' => data_get($response, 'id', data_get($response, 'data.id'))];
        if (in_array($status, ['SUCCESS', 'COMPLETED', 'PAID'], true)) {
            $updates += ['payout_status' => 'paid', 'paid_at' => $financial->paid_at ?? now(), 'failure_reason' => null];
        } elseif (in_array($status, ['REFUNDED', 'REVERSED', 'FAILED'], true)) {
            $updates += ['payout_status' => 'failed', 'failure_reason' => 'ClickPesa reported payout status '.$status.'.'];
        } else {
            $updates['payout_status'] = 'processing';
        }
        $financial->update($updates);

        if ($financial->payout_status === 'processing') {
            self::dispatch($this->reference)->delay(now()->addMinute())->onQueue('payouts');
        }
    }
}
