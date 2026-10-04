<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayoutDestinationRequest;
use App\Services\BeemSmsService;
use App\Services\PayoutOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PayoutDestinationController extends Controller
{
    /** Return the signed-in seller or rider's own payout settings. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole(['seller', 'rider'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers and riders can access payout settings.',
            ], 403);
        }

        $destination = $user->payoutDestination;

        return response()->json([
            'success' => true,
            'data' => $destination ? [
                'payout_method' => $destination->payout_method,
                'mobile_phone' => $destination->mobile_phone,
                'bank_bic' => $destination->bank_bic,
                'bank_account_number' => $destination->bank_account_number,
                'bank_account_name' => $destination->bank_account_name,
                'verified_at' => $destination->verified_at,
            ] : null,
        ]);
    }

    public function store(
        StorePayoutDestinationRequest $request,
        PayoutOrchestrator $payoutOrchestrator
    ): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $destination = $user->payoutDestination()->firstOrNew();
        $isNew = ! $destination->exists;

        $destination->payout_method = $validated['payout_method'];
        $destination->mobile_phone = $validated['mobile_phone'] ?? null;
        $destination->bank_bic = $validated['bank_bic'] ?? null;
        $destination->bank_account_number = $validated['bank_account_number'] ?? null;
        $destination->bank_account_name = $validated['bank_account_name'] ?? null;

        // Changing payout details requires verification again.
        $destination->verified_at = null;
        $destination->verification_code_hash = null;
        $destination->verification_sent_at = null;
        $destination->verification_expires_at = null;
        $destination->verification_attempts = 0;
        $destination->save();

        // Bank recipients are validated by ClickPesa's name lookup at payout time.
        // Saving a bank account can therefore release completed earnings immediately.
        if ($destination->payout_method === 'bank') {
            $payoutOrchestrator->queueEligibleForUser($user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payout destination saved. Verification is still required.',
            'data' => [
                'payout_method' => $destination->payout_method,
                'verified_at' => $destination->verified_at,
            ],
        ], $isNew ? 201 : 200);
    }

    public function sendVerification(
        Request $request,
        BeemSmsService $sms
    ): JsonResponse {
        $user = $request->user();

        if (! $user->hasRole(['seller', 'rider'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only sellers and riders can verify payout destinations.',
            ], 403);
        }

        $destination = $user->payoutDestination;

        if (! $destination || $destination->payout_method !== 'mobile_money') {
            return response()->json([
                'success' => false,
                'message' => 'Save a mobile money payout destination first.',
            ], 422);
        }

        if (
            $destination->verification_sent_at
            && $destination->verification_sent_at->gt(now()->subSeconds(60))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Wait 60 seconds before requesting another code.',
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        $destination->forceFill([
            'verification_code_hash' => Hash::make($code),
            'verification_sent_at' => now(),
            'verification_expires_at' => now()->addMinutes(10),
            'verification_attempts' => 0,
        ])->save();

        try {
            $sms->send(
                $destination->mobile_phone,
                "Your OrderMe payout verification code is {$code}. It expires in 10 minutes."
            );
        } catch (\RuntimeException $exception) {
            $destination->forceFill([
                'verification_code_hash' => null,
                'verification_sent_at' => null,
                'verification_expires_at' => null,
            ])->save();

            return response()->json([
                'success' => false,
                'message' => 'Could not send the verification code. Please try again later.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent.',
        ]);
    }

    public function verify(
        Request $request,
        PayoutOrchestrator $payoutOrchestrator
    ): JsonResponse {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        $result = DB::transaction(function () use ($user, $validated): array {
            $destination = $user->payoutDestination()
                ->lockForUpdate()
                ->first();

            if (! $destination || $destination->payout_method !== 'mobile_money') {
                return ['status' => 'not_found'];
            }

            if (! $destination->verification_code_hash) {
                return ['status' => 'no_code'];
            }

            if ($destination->verification_attempts >= 5) {
                return ['status' => 'too_many_attempts'];
            }

            if (
                ! $destination->verification_expires_at
                || $destination->verification_expires_at->isPast()
            ) {
                $destination->forceFill([
                    'verification_code_hash' => null,
                    'verification_sent_at' => null,
                    'verification_expires_at' => null,
                ])->save();

                return ['status' => 'expired'];
            }

            if (! Hash::check($validated['code'], $destination->verification_code_hash)) {
                $destination->increment('verification_attempts');

                return ['status' => 'incorrect'];
            }

            $destination->forceFill([
                'verified_at' => now(),
                'verification_code_hash' => null,
                'verification_sent_at' => null,
                'verification_expires_at' => null,
                'verification_attempts' => 0,
            ])->save();

            return ['status' => 'verified'];
        });

        if ($result['status'] === 'verified') {
            $payoutOrchestrator->queueEligibleForUser($user);
        }

        return match ($result['status']) {
            'verified' => response()->json([
                'success' => true,
                'message' => 'Payout destination verified.',
            ]),
            'incorrect' => response()->json([
                'success' => false,
                'message' => 'The verification code is incorrect.',
            ], 422),
            'too_many_attempts' => response()->json([
                'success' => false,
                'message' => 'Too many attempts. Request a new verification code.',
            ], 429),
            'expired' => response()->json([
                'success' => false,
                'message' => 'The verification code expired. Request a new one.',
            ], 422),
            'not_found' => response()->json([
                'success' => false,
                'message' => 'A mobile money payout destination was not found.',
            ], 422),
            default => response()->json([
                'success' => false,
                'message' => 'Request a verification code first.',
            ], 422),
        };
    }
}
