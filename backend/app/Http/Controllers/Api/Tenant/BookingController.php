<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\NotificationService;
class BookingController extends Controller
{
    // List all bookings for the tenant
    public function index(Request $request)
    {
        $bookings = Booking::where('tenant_id', $request->user()->id)
            ->with('property:id,title,type,price,governorate_id,city_id,neighborhood_id,street')
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Submit a booking request
    public function store(Request $request)
    {
        // تحقق من اكتمال البيانات
        if (!$request->user()->isTenantReady()) {
            return response()->json([
                'message'  => 'يرجى إكمال ملفك الشخصي قبل الحجز.',
                'redirect' => 'profile/complete',
            ], 403);
        }
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after:start_date',
        ]);

        $property = Property::findOrFail($data['property_id']);

        // Only available properties can be booked
        if ($property->status !== 'accepted' || $property->availability !== 'available') {
            return response()->json([
                'message' => 'هذا العقار غير متاح للحجز.',
            ], 403);
        }

        // Prevent duplicate pending booking by same tenant
        $exists = Booking::where('tenant_id', $request->user()->id)
            ->where('property_id', $data['property_id'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'لديك بالفعل طلب حجز معلق لهذا العقار.',
            ], 403);
        }

        $booking = Booking::create([
            'tenant_id'   => $request->user()->id,
            'property_id' => $data['property_id'],
            'start_date'  => $data['start_date'],
            'end_date'    => $data['end_date'],
            'price'       => $property->price,
            'status'      => 'pending',
        ]);
        // Notify host
        NotificationService::send(
            $property->host_id,
            'طلب حجز جديد',
            'لديك طلب حجز جديد لـ "' . $property->title . '".',
            'new_booking',
            $booking->id
        );
        return response()->json([
            'message' => 'تم إرسال طلب الحجز بنجاح.',
            'booking' => $booking,
        ], 201);
    }

    // View a single booking
    public function show(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user()->id)
            ->with('property:id,title,type,price,governorate_id,city_id,neighborhood_id,street')
            ->findOrFail($id);

        return response()->json($booking);
    }

    // Edit a pending booking
    public function update(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user()->id)
            ->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'يمكن تعديل الحجوزات المعلقة فقط.',
            ], 403);
        }

        $data = $request->validate([
    'start_date' => 'sometimes|date|after_or_equal:today',
    'end_date'   => 'sometimes|date|after_or_equal:today',
]);

// Use existing values if not provided in request
$startDate = $data['start_date'] ?? $booking->start_date->format('Y-m-d');
$endDate   = $data['end_date']   ?? $booking->end_date->format('Y-m-d');

// Validate that end_date is always after start_date
if ($endDate <= $startDate) {
    return response()->json([
        'message' => 'يجب أن يكون تاريخ الانتهاء بعد تاريخ البداية.',
        'errors'  => [
            'end_date' => ['يجب أن يكون تاريخ الانتهاء بعد تاريخ البداية.'],
        ],
    ], 422);
}

$booking->update($data);


        // Notify host
        $property = $booking->property;
        NotificationService::send(
            $property->host_id,
            'تم تعديل الحجز',
            'قام مستأجر بتعديل طلب الحجز لـ "' . $property->title . '".',
            'booking_edited',
            $booking->id
        );
        return response()->json([
            'message' => 'تم تحديث الحجز بنجاح.',
            'booking' => $booking,
        ]);
    }

    // Cancel a pending booking
    public function cancel(Request $request, $id)
    {
        $booking = Booking::where('tenant_id', $request->user()->id)
            ->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'يمكن إلغاء الحجوزات المعلقة فقط.',
            ], 403);
        }

        $booking->update(['status' => 'cancelled']);
        // Notify host
        $property = $booking->property;
        NotificationService::send(
            $property->host_id,
            'تم إلغاء الحجز',
            'قام مستأجر بإلغاء طلب الحجز لـ "' . $property->title . '".',
            'booking_cancelled',
            $booking->id
        );
        return response()->json([
            'message' => 'تم إلغاء الحجز بنجاح.',
        ]);
    }
}