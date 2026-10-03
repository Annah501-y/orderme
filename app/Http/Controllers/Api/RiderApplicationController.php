<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitRiderApplicationRequest;
use App\Models\RiderApplication;
use Illuminate\Http\JsonResponse;

class RiderApplicationController extends Controller
{
    public function store(SubmitRiderApplicationRequest $request): JsonResponse
    {
        $application = RiderApplication::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Your rider application was sent. Wait for an invitation from OrderMe.',
            'data' => [
                'application_id' => $application->id,
                'name' => $application->name,
                'email' => $application->email,
                'status' => $application->status,
            ],
        ], 202);
    }
}
