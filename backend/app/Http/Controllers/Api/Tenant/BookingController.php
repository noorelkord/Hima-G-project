<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\Request;
use App\Services\NotificationService;
use Carbon\Carbon;

class BookingController extends Controller
{
    // ─────────────────────────────────────────────
    // دالة مشتركة لحساب المدة والإجمالي
    // نفس منطق ContractPdfService بالضبط
    // ─────────────────────────────────────────────
    private static function calcDurationAndTotal(string $startDate, string $endDate, float $price): array
    {
        $start = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);
        $diff  = $start->diff($end);

        $years  = $diff->y;
        $months = $diff->m;
        $days   = $diff->d;

        $totalMonths = ($years * 12) + $months;
        $dailyRate   = $price / 30;
        $total       = round(($totalMonths * $price) + ($days * $dailyRate), 2);

        return [
            'years'         => $years,
            'months'        => $months,
            'days'          => $days,
            'total_months'  => $totalMonths,
            'daily_rate'    => round($dailyRate, 2),
            'total'         => $total,
            'duration_text' => self::formatDuration($years, $months, $days),
        ];
    }

    // ─────────────────────────────────────────────
    // تنسيق المدة بالعربي — نفس formatDuration() في PHP
    // ─────────────────────────────────────────────
    private static function formatDuration(int $years, int $months, int $days): string
    {
        $parts = [];

        if ($years > 0) {
            $parts[] = $years === 1 ? 'سنة' : $years . ' سنوات';
        }
        if ($months > 0) {
            $parts[] = $months === 1 ? 'شهر' : $months . ' أشهر';
        }
        if ($days > 0) {
            $parts[] = $days === 1 ? 'يوم واحد' : $days . ' يوم';
        }
        if (empty($parts)) {
            return 'يوم واحد';
        }

        return implode(' و', $parts);
    }

    // ─────────────────────────────────────────────
    // حساب تقديري — بدون إنشاء حجز
    // يستخدم property.price (سعر الإعلان)
    // ─────────────────────────────────────────────
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date'  => 'required|date|after_or_equal:today',
            'end_date'    => 'required|date|after:start_date',
        ]);

        $property = Property::findOrFail($data['property_id']);

        $calc = self::calcDurationAndTotal(
            $data['start_date'],
            $data['end_date'],
            (float) $property->price
        );

        return response()->json([
            'price'         => (float) $property->price,
            'duration_text' => $calc['duration_text'],
            'total'         => $calc['total'],
            'daily_rate'    => $calc['daily_rate'],
            'years'         => $calc['years'],
            'months'        => $calc['months'],
            'days'          => $calc['days'],
        ]);
    }

    // List all bookings for the tenant
    public function index(Request $request)
    {
        $bookings = Booking::where('tenant_id', $request->user()->id)
            ->with([
                'property:id,title,type,price,governorate_id,city_id,neighborhood_id,street',
                'property.governorate:id,name',
                'property.city:id,name',
                'property.neighborhood:id,name',
                'property.images',
            ])
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Submit a booking request
    public function store(Request $request)
    {
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

        if ($property->status !== 'accepted' || $property->availability !== 'available') {
            return response()->json([
                'message' => 'هذا العقار غير متاح للحجز.',
            ], 403);
        }

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
            ->with([
                'property:id,title,type,price,host_id,governorate_id,city_id,neighborhood_id,street',
                'property.governorate:id,name',
                'property.city:id,name',
                'property.neighborhood:id,name',
                'property.host:id,first_name,last_name,phone',
                'property.images',
            ])
            ->findOrFail($id);

        // ✅ حساب المدة والإجمالي بسعر الحجز المتفق عليه
        $calc = self::calcDurationAndTotal(
            $booking->start_date,
            $booking->end_date,
            (float) $booking->price
        );
        $booking->duration_text = $calc['duration_text'];
        $booking->total         = $calc['total'];
        $booking->daily_rate    = $calc['daily_rate'];

        // ✅ تقييم المضيف
        if ($booking->property && $booking->property->host_id) {
            $reviews = Review::where('reviewee_id', $booking->property->host_id)
                ->where('type', 'tenant_to_host')
                ->get();
            $booking->property->host_rating        = $reviews->count() > 0
                ? round($reviews->avg('rating'), 1)
                : null;
            $booking->property->host_reviews_count = $reviews->count();
        }

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

        $startDate = $data['start_date'] ?? $booking->start_date->format('Y-m-d');
        $endDate   = $data['end_date']   ?? $booking->end_date->format('Y-m-d');

        if ($endDate <= $startDate) {
            return response()->json([
                'message' => 'يجب أن يكون تاريخ الانتهاء بعد تاريخ البداية.',
                'errors'  => [
                    'end_date' => ['يجب أن يكون تاريخ الانتهاء بعد تاريخ البداية.'],
                ],
            ], 422);
        }

        $booking->update($data);

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
