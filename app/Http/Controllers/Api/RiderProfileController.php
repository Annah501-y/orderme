<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class RiderProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('rider')) {
            abort(403, 'Only rider accounts can access this profile.');
        }

        $rider = Rider::query()
            ->with('user:id,name,email,phone,profile_photo')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $rider->user,
                'rider' => [
                    'id' => $rider->id,
                    'vehicle_type' => $rider->vehicle_type,
                    'license_number' => $rider->license_number,
                    'license_status' => $rider->license_status,
                    'license_review_note' => $rider->license_review_note,
                    'has_license_document' => (bool) $rider->license_document_path,
                ],
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('rider')) {
            abort(403, 'Only rider accounts can update this profile.');
        }

        $rider = Rider::where('user_id', $request->user()->id)->firstOrFail();
        // Require a document on first submission while allowing existing riders to update details.
        $documentRules = $rider->license_document_path
            ? ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240']
            : ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'regex:/^[67]\d{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'vehicle_type' => ['required', 'in:bodaboda,bajaji'],
            'license_number' => ['required', 'string', 'max:64'],
            'license_document' => $documentRules,
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
        ]);

        if ($request->hasFile('license_document')) {
            // Keep identity documents private and replace the previous upload only after storing the new one.
            $oldDocument = $rider->license_document_path;
            $newDocument = $request->file('license_document')->store('rider-licenses/'.$rider->id, 'local');
            $rider->license_document_path = $newDocument;

            if ($oldDocument) {
                Storage::disk('local')->delete($oldDocument);
            }
        }

        $rider->fill([
            'vehicle_type' => $validated['vehicle_type'],
            'license_number' => $validated['license_number'],
            'license_status' => 'pending',
            'license_reviewed_at' => null,
            'license_review_note' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Your rider profile was submitted for licence review.',
            'data' => [
                'license_status' => $rider->license_status,
                'vehicle_type' => $rider->vehicle_type,
            ],
        ]);
    }
}
