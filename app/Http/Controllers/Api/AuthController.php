<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;



class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $role = match ($request->account_type) {
            'buyer' => UserRole::BUYER,
            'seller' => UserRole::SELLER,
        };
    
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $role,
        ]);
    
        $token = $user->createToken('orderme-app')->plainTextToken;
    
        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }
    
    public function Login(LoginRequest $request): JsonResponse
    {
        $user = user::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['success' => 'false', 'message' => 'Invalid emailor password'], 401);
        }
        $token = $user->createToken('orderme-app')->plainTextToken;
        return response()->json([
            'success' => true,
            'message' => 'Login Successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }
    public function Logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();
        return response()->json(['success' => 'true', 'message' => 'Logged out successfully.']);
    }
    public function me(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['user' => request()->user()],
        ]);
    }
}
