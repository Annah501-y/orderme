<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FeedbackComment;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerFeedbackController extends Controller
{
    public function storeReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ]);

        $buyerOwnsOrder = Order::query()
            ->whereKey($validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->exists();

        $productWasOrdered = OrderItem::query()
            ->where('order_id', $validated['order_id'])
            ->where('product_id', $validated['product_id'])
            ->exists();

        if (! $buyerOwnsOrder || ! $productWasOrdered) {
            return response()->json([
                'success' => false,
                'message' => 'You can review only a product from your own order.',
            ], 403);
        }

        if (ProductReview::query()
            ->where('product_id', $validated['product_id'])
            ->where('user_id', $request->user()->id)
            ->where('order_id', $validated['order_id'])
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this product for this order.',
            ], 422);
        }

        $review = ProductReview::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your review was submitted for approval.',
            'data' => $review,
        ], 201);
    }

    public function storeComment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:general,faq'],
            'faq_id' => ['required_if:type,faq', 'nullable', 'integer'],
            'faq_question' => ['nullable', 'string', 'max:255'],
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $feedback = FeedbackComment::query()->create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your comment was submitted for review.',
            'data' => $feedback,
        ], 201);
    }
    public function reviewsForModeration(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => ProductReview::query()
            ->with(['user:id,name,email', 'product:id,name'])
            ->latest()
            ->paginate(20),
    ]);
}

public function updateReviewStatus(
    Request $request,
    ProductReview $review
): JsonResponse {
    $validated = $request->validate([
        'status' => ['required', 'in:approved,rejected'],
    ]);

    $review->update(['status' => $validated['status']]);

    return response()->json([
        'success' => true,
        'message' => 'Review moderation status updated.',
        'data' => $review->fresh(),
    ]);
}

public function commentsForModeration(): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => FeedbackComment::query()
            ->with('user:id,name,email')
            ->latest()
            ->paginate(20),
    ]);
}

public function updateCommentStatus(
    Request $request,
    FeedbackComment $feedbackComment
): JsonResponse {
    $validated = $request->validate([
        'status' => ['required', 'in:approved,rejected'],
        'admin_response' => ['nullable', 'string', 'max:5000'],
    ]);

    $feedbackComment->update($validated);

    return response()->json([
        'success' => true,
        'message' => 'Comment moderation status updated.',
        'data' => $feedbackComment->fresh(),
    ]);
}
public function approvedReviews(Product $product): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => ProductReview::query()
            ->where('product_id', $product->id)
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->paginate(10),
    ]);
}

public function approvedFaqComments(int $faqId): JsonResponse
{
    return response()->json([
        'success' => true,
        'data' => FeedbackComment::query()
            ->where('type', 'faq')
            ->where('faq_id', $faqId)
            ->where('status', 'approved')
            ->with('user:id,name')
            ->latest()
            ->paginate(10),
    ]);
}
}