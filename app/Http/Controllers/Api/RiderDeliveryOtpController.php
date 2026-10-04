<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDeliveryOtpRequest;
use App\Http\Requests\VerifyDeliveryOtpRequest;
use App\Models\Delivery;
use App\Models\DeliveryOtp;
use App\Models\Rider;
use App\Services\BeemSmsService;
use App\Services\PayoutOrchestrator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RiderDeliveryOtpController extends Controller
{
    public function generate(
        GenerateDeliveryOtpRequest $request,
        Delivery $delivery,
        BeemSmsService $sms
    ): JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Find rider profile
        |--------------------------------------------------------------------------
        */
        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure this delivery belongs to this rider
        |--------------------------------------------------------------------------
        */
        if ((int) $delivery->rider_id !== (int) $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this delivery.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery must have started
        |--------------------------------------------------------------------------
        */
        if ($delivery->status !== 'started') {
            return response()->json([
                'success' => false,
                'message' => 'The delivery must be started before generating an OTP.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find the customer delivery stop
        |--------------------------------------------------------------------------
        */
        $customerStop = $delivery->deliveries_stops()
            ->where('stop_type', 'delivery')
            ->whereNull('seller_order_id')
            ->firstOrFail();

        if (! $customerStop) {
            return response()->json([
                'success' => false,
                'message' => 'Customer delivery stop not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Customer stop must have been reached
        |--------------------------------------------------------------------------
        */
        if ($customerStop->status !== 'arrived') {
            return response()->json([
                'success' => false,
                'message' => 'The rider must arrive at the customer location before generating the OTP.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Confirm every seller pickup is complete before customer verification
        |--------------------------------------------------------------------------
        */
        $unfinishedPickupExists = $delivery->deliveries_stops()
            ->where('stop_type', 'pickup')
            ->where('status', '!=', 'completed')
            ->exists();

        if ($unfinishedPickupExists) {
            return response()->json([
                'success' => false,
                'message' => 'Complete every seller pickup before requesting the customer delivery OTP.',
            ], 422);
        }

        $order = $delivery->order;
        $customerPhone = $order?->guest_phone
            ?? $order?->user?->phone
            ?? $order?->address?->phone;

        if (! $customerPhone) {
            return response()->json([
                'success' => false,
                'message' => 'A reachable customer phone number is required to send the delivery OTP.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check for an existing valid OTP
        |--------------------------------------------------------------------------
        */
        $existingOtp = $delivery->otp;

        if (
            $existingOtp &&
            ! $existingOtp->verified_at &&
            $existingOtp->expires_at->isFuture() &&
            $existingOtp->attempts < $existingOtp->max_attempts
        ) {
            return response()->json([
                'success' => true,
                'message' => 'A valid code has already been sent. Ask the customer to share that code.',
                'data' => [
                    'delivery_id' => $delivery->id,
                    'expires_at' => $existingOtp->expires_at,
                    'expires_in_minutes' => max(1, now()->diffInMinutes($existingOtp->expires_at)),
                    'already_sent' => true,
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Generate exactly 6 numeric digits
        |--------------------------------------------------------------------------
        */
        $otp = (string) random_int(100000, 999999);

        /*
        |--------------------------------------------------------------------------
        | Store hashed OTP
        |--------------------------------------------------------------------------
        */
        $deliveryOtp = DeliveryOtp::updateOrCreate(
            [
                'delivery_id' => $delivery->id,
            ],
            [
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
                'attempts' => 0,
                'max_attempts' => 3,
            ]
        );

        /* Send the verification code to the customer without exposing it to the rider. */
        try {
            $sms->send(
                $customerPhone,
                "Your OrderMe verification code for order #{$delivery->order_id}, delivery #{$delivery->id}, is {$otp}. Share it with your rider after receiving this delivery. It expires in 10 minutes."
            );
        } catch (\Throwable $exception) {
            $deliveryOtp->delete();
            Log::warning('Customer delivery verification SMS could not be sent.', [
                'delivery_id' => $delivery->id,
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not send the customer delivery code. Please try again later.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Delivery verification code sent to the customer.',
            'data' => [
                'delivery_id' => $delivery->id,
                'expires_at' => $deliveryOtp->expires_at,
                'expires_in_minutes' => 10,
            ],
        ], 201);
    }

    /**
     * Verify the OTP provided by the customer.
     */
    public function verify(
        VerifyDeliveryOtpRequest $request,
        Delivery $delivery,
        PayoutOrchestrator $payoutOrchestrator
    ): JsonResponse {
        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Find rider profile
        |--------------------------------------------------------------------------
        */
        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Make sure this delivery belongs to this rider
        |--------------------------------------------------------------------------
        */
        if ((int) $delivery->rider_id !== (int) $rider->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this delivery.',
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery must have started
        |--------------------------------------------------------------------------
        */
        if ($delivery->status !== 'started') {
            return response()->json([
                'success' => false,
                'message' => 'The delivery must be started before verifying the OTP.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find the customer delivery stop
        |--------------------------------------------------------------------------
        */
        $customerStop = $delivery->deliveries_stops()
            ->where('stop_type', 'delivery')
            ->whereNull('seller_order_id')
            ->first();

        if (! $customerStop) {
            return response()->json([
                'success' => false,
                'message' => 'Customer delivery stop not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Customer must have arrived
        |--------------------------------------------------------------------------
        */
        if ($customerStop->status !== 'arrived') {
            return response()->json([
                'success' => false,
                'message' => 'The rider must arrive at the customer location first.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Find OTP
        |--------------------------------------------------------------------------
        */
        $deliveryOtp = $delivery->otp;

        if (! $deliveryOtp) {
            return response()->json([
                'success' => false,
                'message' => 'No OTP has been generated for this delivery.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent reuse of verified OTP
        |--------------------------------------------------------------------------
        */
        if ($deliveryOtp->verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'This OTP has already been verified.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check expiration
        |--------------------------------------------------------------------------
        */
        if ($deliveryOtp->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'The OTP has expired.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Check maximum attempts
        |--------------------------------------------------------------------------
        */
        if ($deliveryOtp->attempts >= $deliveryOtp->max_attempts) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum OTP verification attempts exceeded.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Verify OTP
        |--------------------------------------------------------------------------
        */
        $providedOtp = $request->validated()['otp'];

        if (! Hash::check($providedOtp, $deliveryOtp->otp_hash)) {
            $deliveryOtp->increment('attempts');

            return response()->json([
                'success' => false,
                'message' => 'The OTP provided is incorrect.',
                'attempts_remaining' => max(
                    0,
                    $deliveryOtp->max_attempts - $deliveryOtp->attempts
                ),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Mark OTP as verified
        |--------------------------------------------------------------------------
        */
        $deliveryOtp->update([
            'verified_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Complete customer delivery stop
        |--------------------------------------------------------------------------
        */
        $customerStop->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check whether all delivery stops are completed
        |--------------------------------------------------------------------------
        */
        $allStopsCompleted = $delivery->deliveries_stops()
            ->where('status', '!=', 'completed')
            ->doesntExist();

        /*
        |--------------------------------------------------------------------------
        | Complete the entire delivery
        |--------------------------------------------------------------------------
        */
        if ($allStopsCompleted) {
            $delivery->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Release each seller's order as soon as its assigned rider completes that delivery.
            $sellerOrders = $delivery->deliveries_stops()
                ->whereNotNull('seller_order_id')
                ->with('sellerOrder')
                ->get()
                ->pluck('sellerOrder')
                ->filter()
                ->unique('id');

            foreach ($sellerOrders as $sellerOrder) {
                if ($sellerOrder->status !== 'cancelled') {
                    $sellerOrder->update(['status' => 'delivered']);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Mark the parent order complete when all assigned deliveries are done
            |--------------------------------------------------------------------------
            */
            $order = $delivery->order;
            $unfinishedDeliveryExists = $order?->deliveries()
                ->where(function (Builder $query): void {
                    $query->where('status', '!=', 'completed')
                        ->orWhereNull('completed_at');
                })
                ->exists();
            $unfinishedSellerOrderExists = $order?->sellerOrders()
                ->whereNotIn('status', ['delivered', 'cancelled'])
                ->exists();

            if ($order && ! $unfinishedDeliveryExists && ! $unfinishedSellerOrderExists) {
                $order->update(['status' => 'delivered']);
            }
        }

        // Queue a payout only after this delivery is complete and its OTP was verified.
        if ($delivery->status === 'completed' && $delivery->completed_at) {
            $payoutOrchestrator->queueEligibleForDelivery($delivery->fresh());
        }

        $delivery->refresh();
        $customerStop->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Customer OTP verified successfully and this rider delivery is complete.',
            'data' => [
                'delivery_id' => $delivery->id,
                'otp_verified_at' => $deliveryOtp->verified_at,
                'otp_status' => 'verified',
                'customer_stop_id' => $customerStop->id,
                'customer_stop_status' => $customerStop->status,
                'delivery_status' => $delivery->status,
                'delivery_completed_at' => $delivery->completed_at,
            ],
        ]);
    }
}
