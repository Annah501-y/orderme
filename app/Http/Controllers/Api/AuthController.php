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
    /**
     * Register a new buyer or seller account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => $request->validated('password'),
        ]);

        $user->assignRole($request->validated('account_type'));

        $token = $user
            ->createToken('orderme-app')
            ->plainTextToken;

        return ApiResponse::success(
            message: 'Your OrderMe account has been created successfully.',
            data: [
                'user' => $user->load('roles'),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
            status: 201
        );
    }

    /**
     * Authenticate an existing user.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))
            ->first();

        if (
            !$user ||
            !Hash::check(
                $request->validated('password'),
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'email' => [
                    'The email address or password is incorrect.',
                ],
            ]);
        }

        $token = $user
            ->createToken('orderme-app')
            ->plainTextToken;

        return ApiResponse::success(
            message: 'Login successful.',
            data: [
                'user' => $user->load('roles'),
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
        $user = request()->user()->load('roles');

        return ApiResponse::success(
            message: 'Authenticated user retrieved successfully.',
            data: [
                'user' => $user,
            ]
        );
    }
}