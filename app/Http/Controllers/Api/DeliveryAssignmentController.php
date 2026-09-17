<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeliveryAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class DeliveryAssignmentController extends Controller
{
    public function __construct(
        protected DeliveryAssignmentService $assignmentService
    ) {}

    public function assign(
        Request $request,
        int $orderId
    ): JsonResponse {
        $user = $request->user();

        if (
            ! $user->hasRole('admin') &&
            ! $user->hasRole('seller')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to assign deliveries.',
            ], 403);
        }

        try {
            $deliveries = $this->assignmentService->assign($orderId);

            return response()->json([
                'success' => true,
                'message' => 'Delivery assigned successfully.',
                'data' => $deliveries,
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}