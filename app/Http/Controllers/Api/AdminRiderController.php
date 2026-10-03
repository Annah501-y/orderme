<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateRiderRequest;
use App\Http\Requests\StoreRiderRequest;
use App\Mail\RiderInvitationMail;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AdminRiderController extends Controller
{
    public function applications(): JsonResponse
    {
        $applications = RiderApplication::query()
            ->with('user')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Rider applications retrieved successfully.',
            'data' => $applications,
        ]);
    }

    public function inviteApplicant(RiderApplication $riderApplication): JsonResponse
    {
        if (! in_array($riderApplication->status, ['pending', 'invited'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending rider applicants can be invited.',
            ], 422);
        }

        if (! $riderApplication->user_id && User::where('email', $riderApplication->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'An account already uses this email address.',
            ], 422);
        }

        if ($riderApplication->user?->riderInvitation?->accepted_at) {
            return response()->json([
                'success' => false,
                'message' => 'This rider invitation has already been accepted.',
            ], 422);
        }

        $plainToken = Str::random(64);

        $result = DB::transaction(function () use ($plainToken, $riderApplication): array {
            $user = $riderApplication->user;

            if (! $user) {
                $user = User::create([
                    'name' => $riderApplication->name,
                    'email' => $riderApplication->email,
                    'phone' => $riderApplication->phone,
                    'password' => Str::random(64),
                ]);
                $user->forceFill(['is_active' => false])->save();
                $user->assignRole(['buyer', 'rider']);

                Rider::create([
                    'user_id' => $user->id,
                    'latitude' => null,
                    'longitude' => null,
                    'is_available' => false,
                    'status' => 'offline',
                ]);
            }

            $invitation = $user->riderInvitation()->updateOrCreate([], [
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(24),
                'accepted_at' => null,
            ]);

            $riderApplication->update([
                'user_id' => $user->id,
                'status' => 'invited',
            ]);

            return [
                'user' => $user,
                'invitation' => $invitation,
            ];
        });

        $activationUrl = config('app.frontend_url', 'http://localhost:5173')
            .'/rider/activate?token='.$plainToken;

        Mail::to($result['user']->email)->queue(
            new RiderInvitationMail($result['user'], $activationUrl)
        );

        return response()->json([
            'success' => true,
            'message' => 'Rider invitation sent by email.',
            'data' => [
                'application_id' => $riderApplication->id,
                'status' => $riderApplication->fresh()->status,
                'expires_at' => $result['invitation']->expires_at,
            ],
        ]);
    }

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
            ]);
            $user->forceFill(['is_active' => false])->save();

            // 2. Give riders shopping access and their rider permissions.
            $user->assignRole(['buyer', 'rider']);

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
        .'/rider/activate?token='.$result['token'];

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

            $user->forceFill([
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ])->save();

            $user->payoutDestination()->create([
                'payout_method' => $validated['payout_method'],
                'mobile_phone' => $validated['mobile_phone'] ?? null,
                'bank_bic' => $validated['bank_bic'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
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
