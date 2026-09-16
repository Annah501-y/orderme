<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    /**
     * Display all users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('roles')
            ->latest();

        // Search users
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter users by Spatie role
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        $users = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully.',
            'data' => $users,
        ]);
    }


    /**
     * Update a user's basic information.
     */
    public function update(
        Request $request,
        User $user
    ): JsonResponse {
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
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],
        ]);

        $user->update($validated);

        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }


    /**
     * Activate or deactivate a user.
     */
    public function updateStatus(
        Request $request,
        User $user
    ): JsonResponse {
        $validated = $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
        ]);

        $user->update([
            'is_active' => $validated['is_active'],
        ]);

        return response()->json([
            'success' => true,
            'message' => $user->is_active
                ? 'User activated successfully.'
                : 'User deactivated successfully.',
            'data' => $user,
        ]);
    }
}