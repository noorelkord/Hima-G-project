<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Review;
use App\Services\NotificationService;
use App\Models\ReviewWindow;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Submit a review
    public function store(Request $request)
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();

        $data = $request->validate([
            'contract_id' => 'required|exists:contracts,id',
            'rating'      => 'required|integer|min:1|max:5',
            'comment'     => 'nullable|string|max:1000',
        ]);

        $contract = Contract::with('property')->findOrFail($data['contract_id']);

        // Contract must be ended or cancelled
        if ($contract->status === 'active') {
            return response()->json([
                'message' => 'يمكنك التقييم فقط بعد انتهاء العقد أو إلغائه.',
            ], 403);
        }

        // Only parties of the contract can review
        if ($role === 'tenant' && $contract->tenant_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }
        if ($role === 'host' && $contract->host_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        // Check if review window is open
        $window = ReviewWindow::where('contract_id', $contract->id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->first();

        if (!$window) {
            return response()->json([
                'message' => 'انتهت فترة التقييم أو لم تبدأ بعد.',
            ], 403);
        }

        // Determine review type and reviewee
        if ($role === 'tenant') {
            $type       = 'tenant_to_host';
            $revieweeId = $contract->host_id;
            $propertyId = $contract->property_id;
        } else {
            $type       = 'host_to_tenant';
            $revieweeId = $contract->tenant_id;
            $propertyId = null;
        }

        // Prevent duplicate reviews
        $exists = Review::where('contract_id', $data['contract_id'])
            ->where('reviewer_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'لقد قيّمت هذا العقد مسبقاً.',
            ], 403);
        }

        $review = Review::create([
            'contract_id'  => $data['contract_id'],
            'reviewer_id'  => $user->id,
            'reviewee_id'  => $revieweeId,
            'property_id'  => $propertyId,
            'rating'       => $data['rating'],
            'comment'      => $data['comment'] ?? null,
            'type'         => $type,
        ]);

        // Close the review window
        $window->update(['status' => 'closed']);

        // Notify reviewee
        NotificationService::send(
            $revieweeId,
            'تقييم جديد',
            'تلقيت تقييماً جديداً بـ ' . $data['rating'] . ' نجوم لـ "' . $contract->property->title . '".',
            'review_received',
            $review->id
        );

        return response()->json([
            'message' => 'تم إرسال التقييم بنجاح.',
            'review'  => $review,
        ], 201);
    }

    // List reviews for a property
    public function propertyReviews($propertyId)
    {
        $reviews = Review::where('property_id', $propertyId)
            ->where('type', 'tenant_to_host')
            ->with('reviewer:id,first_name,last_name')
            ->latest()
            ->get();

        return response()->json($reviews);
    }

    // List reviews for a user
    public function userReviews($userId)
    {
        $reviews = Review::where('reviewee_id', $userId)
            ->with('reviewer:id,first_name,last_name')
            ->latest()
            ->get();

        return response()->json($reviews);
    }
}
