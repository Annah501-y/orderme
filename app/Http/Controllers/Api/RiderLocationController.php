<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryOtp;
use App\Models\Rider;
use App\Services\BeemSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RiderLocationController extends Controller
{
    /**
     * Save rider GPS coordinates and handle nearby customer drop-offs.
     */
    public function update(Request $request, BeemSmsService $sms): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('rider')) {
            return response()->json([
                'success' => false,
                'message' => 'Only rider accounts can update rider location.',
            ], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        $rider->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'status' => 'online',
            'is_available' => true,
        ]);

        // Only active deliveries with all pickups complete can trigger customer arrival and OTP delivery.
        $arrivedDeliveries = [];
        $deliveries = Delivery::with(['order.user', 'order.address', 'deliveries_stops', 'otp'])
            ->where('rider_id', $rider->id)
            ->where('status', 'started')
            ->get();

        foreach ($deliveries as $delivery) {
            $stops = $delivery->deliveries_stops;
            $customerStop = $stops->first(fn ($stop) =>
                $stop->stop_type === 'delivery' && $stop->seller_order_id === null
            );

            if (! $customerStop || $customerStop->status === 'completed') {
                continue;
            }

            $pickupIncomplete = $stops->contains(fn ($stop) =>
                $stop->stop_type === 'pickup' && $stop->status !== 'completed'
            );

            if ($pickupIncomplete || $customerStop->latitude === null || $customerStop->longitude === null) {
                continue;
            }

            // A 100 metre radius absorbs normal GPS drift while keeping arrival tied to the saved address.
            if ($this->distanceInMeters(
                (float) $validated['latitude'],
                (float) $validated['longitude'],
                (float) $customerStop->latitude,
                (float) $customerStop->longitude
            ) > 100) {
                continue;
            }

            if ($customerStop->status === 'pending') {
                $customerStop->update([
                    'status' => 'arrived',
                    'arrived_at' => now(),
                ]);
            }

            $order = $delivery->order;
            $customerPhone = $order?->guest_phone
                ?? $order?->user?->phone
                ?? $order?->address?->phone;

            if (! $customerPhone) {
                $arrivedDeliveries[] = [
                    'delivery_id' => $delivery->id,
                    'otp_sent' => false,
                    'message' => 'Arrival detected, but the customer has no reachable phone number.',
                ];
                continue;
            }

            $otp = $delivery->otp;
            $alreadyValid = $otp
                && ! $otp->verified_at
                && $otp->expires_at?->isFuture()
                && $otp->attempts < $otp->max_attempts;

            if ($alreadyValid) {
                $arrivedDeliveries[] = [
                    'delivery_id' => $delivery->id,
                    'otp_sent' => true,
                    'expires_at' => $otp->expires_at,
                ];
                continue;
            }

            $code = (string) random_int(100000, 999999);
            $otp = DeliveryOtp::updateOrCreate(
                ['delivery_id' => $delivery->id],
                [
                    'otp_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(10),
                    'verified_at' => null,
                    'attempts' => 0,
                    'max_attempts' => 3,
                ]
            );

            try {
                $sms->send(
                    $customerPhone,
                    "Your OrderMe verification code for order #{$delivery->order_id}, delivery #{$delivery->id}, is {$code}. Share it with your rider after receiving this delivery. It expires in 10 minutes."
                );
                $arrivedDeliveries[] = [
                    'delivery_id' => $delivery->id,
                    'otp_sent' => true,
                    'expires_at' => $otp->expires_at,
                ];
            } catch (\Throwable $exception) {
                $otp->delete();
                Log::warning('Automatic customer delivery OTP SMS could not be sent.', [
                    'delivery_id' => $delivery->id,
                    'exception' => $exception::class,
                ]);
                $arrivedDeliveries[] = [
                    'delivery_id' => $delivery->id,
                    'otp_sent' => false,
                    'message' => 'Arrival detected, but the verification code could not be sent. GPS updates will retry.',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rider location updated successfully.',
            'data' => [
                'rider_id' => $rider->id,
                'latitude' => $rider->latitude,
                'longitude' => $rider->longitude,
                'status' => $rider->status,
                'is_available' => $rider->is_available,
                'arrived_deliveries' => $arrivedDeliveries,
            ],
        ], 200);
    }

    /** Calculate distance between two GPS points using the Haversine formula. */
    private function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
