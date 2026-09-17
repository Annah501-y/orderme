<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderLocationController extends Controller
{
    /**
     * Update the current GPS location of the logged-in rider.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // Make sure the logged-in user is a rider.
        if (! $user->hasRole('rider')) {
            return response()->json([
                'success' => false,
                'message' => 'Only rider accounts can update rider location.',
            ], 403);
        }

        // Validate GPS coordinates.
        $validated = $request->validate([
            'latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],
        ]);

        // Find the rider profile belonging to the logged-in user.
        $rider = Rider::where('user_id', $user->id)->first();

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Rider profile not found.',
            ], 404);
        }

        // Update the rider's current location.
        $rider->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'status' => 'online',
            'is_available' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rider location updated successfully.',
            'data' => [
                'rider_id' => $rider->id,
                'latitude' => $rider->latitude,
                'longitude' => $rider->longitude,
                'status' => $rider->status,
                'is_available' => $rider->is_available,
            ],
        ], 200);
    }
}