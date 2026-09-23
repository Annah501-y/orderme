<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('ClickPesa webhook received', $payload);

        // TODO: confirm the exact payload shape ClickPesa sends against
        // their docs/dashboard sample payloads — field names below
        // (orderReference, status, id) are the commonly documented ones
        // but verify before relying on them in production.
        $orderReference = $payload['orderReference'] ?? null;
        $status = $payload['status'] ?? null; // e.g. SUCCESS, FAILED, PROCESSING
        $transactionId = $payload['id'] ?? null;

        if (! $orderReference) {
            Log::warning('ClickPesa webhook missing orderReference', $payload);
            return response()->json(['status' => 'ignored'], 200);
        }

        // Matches the `reference` column set in OrderController::payment()
        // when the USSD push was initiated (order{id}payment{id}).
        $payment = Payment::where('reference', $orderReference)
            ->latest()
            ->first();

        if (! $payment) {
            Log::warning('ClickPesa webhook: no matching payment found', $payload);
            return response()->json(['status' => 'ignored'], 200);
        }

        $newStatus = match (strtoupper((string) $status)) {
            'SUCCESS', 'PAID', 'COMPLETED' => 'paid',
            'FAILED', 'CANCELLED' => 'failed',
            default => $payment->status,
        };

        $payment->update([
            'status' => $newStatus,
            'transaction_id' => $transactionId,
            'paid_at' => $newStatus === 'paid' ? now() : $payment->paid_at,
        ]);

        // Optionally update the related Order's status here too, e.g.
        // mark it as confirmed once payment status is 'paid'.

        return response()->json(['status' => 'received'], 200);
    }
}