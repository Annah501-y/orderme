<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDeliveryOtpRequest;
use App\Models\Delivery;
use App\Models\DeliveryOtp;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RiderDeliveryOtpController extends Controller
{
    public function generate(
        GenerateDeliveryOtpRequest $request,
        Delivery $delivery
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
            ->first();

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
                'success' => false,
                'message' => 'An active OTP already exists for this delivery.',
            ], 422);
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

        /*
        |--------------------------------------------------------------------------
        | SMS sending will be added separately
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'success' => true,
            'message' => 'Delivery OTP generated successfully.',
            'data' => [
                'delivery_id' => $delivery->id,
                'expires_at' => $deliveryOtp->expires_at,
                'expires_in_minutes' => 10,

                /*
                |--------------------------------------------------------------------------
                | Temporary testing value
                |--------------------------------------------------------------------------
                |
                | Remove this field when the SMS provider is connected.
                |
                */
                'otp' => $otp,
            ],
        ], 201);
    }
}