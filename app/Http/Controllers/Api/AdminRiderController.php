<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRiderRequest;
use App\Http\Requests\ActivateRiderRequest;
use App\Models\Rider;
use App\Models\RiderInvitation;
use App\Models\User;
use App\Mail\RiderInvitationMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminRiderController extends Controller
{
    public function store(StoreRiderRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = DB::transaction(function () use ($validated) {

            // 1. Create inactive user account
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => Hash::make(Str::random(64)),
                'is_active' => false,
            ]);

            // 2. Assign rider role
            $user->assignRole('rider');

            // 3. Create rider profile
            $rider = Rider::create([
                'user_id' => $user->id,
                'latitude' => null,
                'longitude' => null,
                'is_available' => false,
                'status' => 'offline',
            ]);

            // 4. Generate invitation token
            $plainToken = Str::random(64);

            // 5. Store only the hashed token
            $invitation = RiderInvitation::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(24),
            ]);

            return [
                'user' => $user,
                'rider' => $rider,
                'invitation' => $invitation,
                'token' => $plainToken,
            ];
        });
            $activationUrl = config('app.frontend_url', 'http://localhost:5173')
            . '/rider/activate?token=' . $result['token'];

              Mail::to($result['user']->email)
            ->queue(new RiderInvitationMail(
                $result['user'],
                $activationUrl
            ));
             return response()->json([
            'success' => true,
            'message' => 'Rider account created successfully.',
            'data' => [
                'rider' => [
                    'id' => $result['rider']->id,
                    'user_id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'phone' => $result['user']->phone,
                    'is_active' => $result['user']->is_active,
                    'status' => $result['rider']->status,
                ],
            ],
        ], 201);
    }
    public function activate(ActivateRiderRequest $request): JsonResponse
{
    $validated = $request->validated();

    $tokenHash = hash('sha256', $validated['token']);

    $invitation = RiderInvitation::where('token_hash', $tokenHash)
        ->with('user')
        ->first();

    if (! $invitation) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid invitation token.',
        ], 400);
    }

    if ($invitation->accepted_at) {
        return response()->json([
            'success' => false,
            'message' => 'This invitation has already been used.',
        ], 400);
    }

    if ($invitation->expires_at->isPast()) {
        return response()->json([
            'success' => false,
            'message' => 'This invitation has expired.',
        ], 400);
    }

    $user = $invitation->user;

    if (! $user->hasRole('rider')) {
        return response()->json([
            'success' => false,
            'message' => 'This invitation is not valid for a rider account.',
        ], 403);
    }

    DB::transaction(function () use ($user, $invitation, $validated) {

        $user->update([
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $invitation->update([
            'accepted_at' => now(),
        ]);
    });

    return response()->json([
        'success' => true,
        'message' => 'Rider account activated successfully. You can now log in.',
    ], 200);
}
}