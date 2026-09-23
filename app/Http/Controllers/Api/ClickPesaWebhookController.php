<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\OrderFinancialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClickPesaWebhookController extends Controller
{
    /**
     * Handle ClickPesa's PAYMENT RECEIVED / PAYMENT FAILED webhook events.
     *
     * Register this route WITHOUT the auth:sanctum middleware (ClickPesa is
     * calling your server directly, not an authenticated user), and set this
     * URL as your webhook in the ClickPesa merchant dashboard under
     * Settings -> Payments. While testing with ngrok, this must be your
     * public ngrok URL + this route's path.
     */
    public function handle(
        Request $request,
        OrderFinancialService $orderFinancialService
    ): JsonResponse {
        $payload = $request->all();
        $event = strtoupper((string) ($payload['event'] ?? $payload['eventType'] ?? ''));

        if (! in_array($event, ['PAYMENT RECEIVED', 'PAYMENT FAILED'], true)) {
            Log::warning('ClickPesa webhook: unsupported event', $payload);

            return response()->json(['status' => 'ignored'], 200);
        }

        Log::info('ClickPesa webhook received', $payload);

        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            Log::warning('ClickPesa webhook data must be an object', $payload);

            return response()->json(['status' => 'ignored'], 200);
        }

        $orderReference = $data['orderReference'] ?? null;
        $status = $data['status'] ?? null;
        $transactionId = $data['id'] ?? null;

        if (! $orderReference) {
            Log::warning('ClickPesa webhook missing orderReference', $payload);

            return response()->json(['status' => 'ignored'], 200);
        }

        // Matches the `reference` column set in OrderController::payment()
        // when the USSD push was initiated (order{id}payment{id}).
        $paymentFound = DB::transaction(function () use (
            $orderReference,
            $status,
            $transactionId,
            $orderFinancialService,
            $event,
            $data
        ): bool {
            $payment = Payment::where('reference', $orderReference)
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                return false;
            }

            $newStatus = match (true) {
                $event === 'PAYMENT RECEIVED'
                    && strtoupper((string) $status) === 'SUCCESS' => 'paid',
                $event === 'PAYMENT FAILED'
                    && strtoupper((string) $status) === 'FAILED' => 'failed',
                default => $payment->status,
            };
            if ($newStatus === 'paid') {
                $reportedAmount = $data['collectedAmount'] ?? null;
                $reportedCurrency = strtoupper((string) ($data['collectedCurrency'] ?? ''));

                if (
                    ! is_numeric($reportedAmount)
                    || number_format((float) $reportedAmount, 2, '.', '')
                        !== number_format((float) $payment->amount, 2, '.', '')
                    || $reportedCurrency !== strtoupper((string) $payment->currency)
                ) {
                    return false;
                }
            }

            $becamePaid = $payment->status !== 'paid' && $newStatus === 'paid';

            $payment->update([
                'status' => $newStatus,
                'transaction_id' => $transactionId,
                'paid_at' => $newStatus === 'paid' ? ($payment->paid_at ?? now()) : $payment->paid_at,
            ]);

            if ($becamePaid) {
                $orderFinancialService->calculate($payment->order);
            }

            return true;
        });

        if (! $paymentFound) {
            Log::warning(
                'ClickPesa webhook: payment not found or payment validation failed',
                $payload
            );

            return response()->json(['status' => 'ignored'], 200);
        }

        return response()->json(['status' => 'received'], 200);
    }
}
