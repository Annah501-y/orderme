<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Register a new buyer account. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        $user->assignRole('buyer');

        if ($request->validated('account_type') === 'seller') {
            $user->assignRole('seller');
        }

        $token = $user
            ->createToken('orderme-app')
            ->plainTextToken;

        return ApiResponse::success(
            message: 'Your OrderMe account has been created successfully.',
            data: [
                'user' => $user->load(['roles', 'sellerProfile', 'rider']),
                'account' => $this->accountContext($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            status: 201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['The email address is incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active yet. Complete the invitation process first.'],
            ]);
        }

        if (! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $token = $user
            ->createToken('orderme-app')
            ->plainTextToken;

        return ApiResponse::success(
            message: 'Login successful.',
            data: [
                'user' => $user->load(['roles', 'sellerProfile', 'rider']),
                'account' => $this->accountContext($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        );
    }

    /**
     * Revoke the token currently being used.
     */
    public function logout(): JsonResponse
    {
        $user = request()->user();

        $user->currentAccessToken()?->delete();

        return ApiResponse::success(
            message: 'You have been logged out successfully.'
        );
    }

    /**
     * Revoke all tokens belonging to the authenticated user.
     */
    public function logoutAll(): JsonResponse
    {
        $user = request()->user();

        $user->tokens()->delete();

        return ApiResponse::success(
            message: 'You have been logged out from all devices.'
        );
    }

    /**
     * Return the currently authenticated user.
     */
    public function me(): JsonResponse
    {
        $user = request()->user()->load(['roles', 'sellerProfile', 'rider']);

        return ApiResponse::success(
            message: 'Authenticated user retrieved successfully.',
            data: [
                'user' => $user,
                'account' => $this->accountContext($user),
            ]
        );
    }

    /**
     * Provide account information for role-aware dashboard navigation.
     *
     * @return array{seller_application_status: string, seller_dashboard_available: bool, rider_dashboard_available: bool, phone_required: bool}
     */
    private function accountContext(User $user): array
    {
        return [
            'seller_application_status' => $user->sellerProfile?->status ?? 'not_applied',
            'seller_dashboard_available' => $user->isApprovedSeller(),
            'rider_dashboard_available' => $user->is_active
                && $user->hasRole('rider')
                && $user->rider !== null,
            'phone_required' => $user->phone === null,
        ];
    }
}
