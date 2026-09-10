<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->load([
            'roles',
            'addresses',
            'sellerProfile',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'profile_photo_url' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validated();

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            $validated['profile_photo'] = $request->file('profile_photo')
                ->store('profiles/' . $user->id, 'public');
        }

        $user->update($validated);

        $user->load([
            'roles',
            'addresses',
            'sellerProfile',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => $user,
                'profile_photo_url' => $user->profile_photo
                    ? asset('storage/' . $user->profile_photo)
                    : null,
            ],
        ]);
    }
}