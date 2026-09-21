<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AdminSettingsController extends Controller
{
    /**
     * Get the authenticated admin's settings.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,

                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,

                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Update admin profile information.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . $user->id,
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Admin profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,

                'profile_photo' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,

                'is_active' => $user->is_active,
            ],
        ]);
    }

    /**
     * Update admin profile photo.
     */
    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'profile_photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Delete old profile photo
        |--------------------------------------------------------------------------
        */

        if ($user->profile_photo) {
            Storage::disk('public')->delete(
                $user->profile_photo
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Store new profile photo
        |--------------------------------------------------------------------------
        */

        $path = $request->file('profile_photo')->store(
            'admin-profiles',
            'public'
        );

        /*
        |--------------------------------------------------------------------------
        | Update user
        |--------------------------------------------------------------------------
        */

        $user->update([
            'profile_photo' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile photo updated successfully.',
            'data' => [
                'profile_photo' => asset(
                    'storage/' . $path
                ),
            ],
        ]);
    }

    /**
     * Change admin password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'current_password' => [
                'required',
                'current_password',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(8),
            ],
        ]);

        $user->update([
            'password' => Hash::make(
                $validated['password']
            ),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Logout the admin from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully.',
        ]);
    }
}