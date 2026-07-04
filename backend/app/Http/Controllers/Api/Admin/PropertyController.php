<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    // List all properties
    public function index()
    {
        $properties = Property::with([
                'host:id,first_name,last_name,email,phone',
                'images',
                'governorate:id,name',
                'city:id,name',
            ])
            ->latest()
            ->get();

        // Add host rating to each property
        $properties = $properties->map(function ($property) {
            $reviews = Review::where('reviewee_id', $property->host_id)
                ->where('type', 'tenant_to_host')
                ->get();
            $property->host_rating = $reviews->count() > 0
                ? round($reviews->avg('rating'), 1)
                : null;
            $property->host_reviews_count = $reviews->count();
            return $property;
        });

        return response()->json($properties);
    }

    // List pending properties only
    public function pending()
    {
        $properties = Property::with([
                'host:id,first_name,last_name,email,phone',
                'images',
                'governorate:id,name',
                'city:id,name',
            ])
            ->where('status', 'pending')
            ->latest()
            ->get();

        // Add host rating
        $properties = $properties->map(function ($property) {
            $reviews = Review::where('reviewee_id', $property->host_id)
                ->where('type', 'tenant_to_host')
                ->get();
            $property->host_rating = $reviews->count() > 0
                ? round($reviews->avg('rating'), 1)
                : null;
            $property->host_reviews_count = $reviews->count();
            return $property;
        });

        return response()->json($properties);
    }

    // Show a single property with full details
    public function show($id)
    {
        $property = Property::with([
            'host:id,first_name,last_name,email,phone,national_id',
            'images',
            'governorate:id,name',
            'city:id,name',
            'neighborhood:id,name',
        ])->findOrFail($id);

        // Add host rating
        $reviews = Review::where('reviewee_id', $property->host_id)
            ->where('type', 'tenant_to_host')
            ->get();
        $property->host_rating = $reviews->count() > 0
            ? round($reviews->avg('rating'), 1)
            : null;
        $property->host_reviews_count = $reviews->count();

        return response()->json($property);
    }

    // Accept a property
    public function accept($id) {
        $property = Property::findOrFail($id);
        if (!in_array($property->status, ['pending', 'rejected'])) {
              return response()->json([
             'message' => 'يمكن قبول العقارات المعلقة أو المرفوضة فقط.',
             ], 403);
    }
        $property->update([
            'status'           => 'accepted',
            'availability'     => 'available',
            'rejection_reason' => null,
        ]);
        // Notify host
        NotificationService::send(
            $property->host_id,
            'تمت الموافقة على العقار',
            'تمت الموافقة على عقارك "' . $property->title . '" وهو متاح الآن.',
            'property_approved',
            $property->id
        );

        return response()->json([
            'message'  => 'تم قبول العقار وهو متاح الآن.',
            'property' => $property,
        ]);
    }

    // Reject a property
    public function reject(Request $request, $id) {
        $property = Property::findOrFail($id);
        if (!in_array($property->status, ['pending', 'accepted'])) {
            return response()->json([
                'message' => 'يمكن رفض العقارات المعلقة أو المقبولة فقط.',
                ], 403);
        }
        // Cannot reject if booked (active contract)
        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'لا يمكن رفض عقار له عقد نشط.',
            ], 403);
        }
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        // Cancel all pending bookings and notify tenants
        $pendingBookings = $property->bookings()->where('status', 'pending')->get();
        foreach ($pendingBookings as $booking) {
            $booking->update(['status' => 'cancelled']);
            NotificationService::send(
                $booking->tenant_id,
                'تم إلغاء الحجز',
                'تم إلغاء طلب الحجز لـ "' . $property->title . '". تم تعليق العقار مؤقتاً من قبل الإدارة.',
                'booking_cancelled',
                $booking->id
            );
        }
        $property->update([
            'status'           => 'rejected',
            'availability'     => 'not_available',
            'rejection_reason' => $data['rejection_reason'],
        ]);
        // Notify host
        NotificationService::send(
            $property->host_id,
            'تم رفض العقار',
            'تم رفض عقارك "' . $property->title . '". السبب: ' . $data['rejection_reason'],
            'property_rejected',
            $property->id
        );
        return response()->json([
            'message'  => 'تم رفض العقار.',
            'property' => $property,
        ]);
    }

    // Archive a violating property
    public function destroy($id)
    {
        $property = Property::findOrFail($id);

        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'لا يمكن حذف عقار محجوز.',
            ], 403);
        }

        $property->delete();

        return response()->json([
            'message' => 'تم أرشفة العقار بنجاح.',
        ]);
    }
}
