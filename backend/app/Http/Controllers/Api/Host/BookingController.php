<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\Review;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use App\Services\ContractPdfService;
use Carbon\Carbon;

class BookingController extends Controller
{
    // ─────────────────────────────────────────────
    // نفس منطق ContractPdfService و TenantBookingController
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
            'duration_text' => self::formatDuration($years, $months, $days),
            'total'         => $total,
            'daily_rate'    => round($dailyRate, 2),
        ];
    }

    private static function formatDuration(int $years, int $months, int $days): string
    {
        $parts = [];
        if ($years > 0)  $parts[] = $years === 1  ? 'سنة'       : $years  . ' سنوات';
        if ($months > 0) $parts[] = $months === 1 ? 'شهر'       : $months . ' أشهر';
        if ($days > 0)   $parts[] = $days === 1   ? 'يوم واحد'  : $days   . ' يوم';
        return empty($parts) ? 'يوم واحد' : implode(' و', $parts);
    }

    // List all booking requests for host's properties
    public function index(Request $request)
    {
        $bookings = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })
            ->with([
                'property:id,title,governorate_id,city_id,neighborhood_id,street',
                'property.images',
                'property.governorate:id,name',
                'property.city:id,name',
                'tenant:id,first_name,last_name,email,phone',
            ])
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Show a single booking request with full details
    public function show(Request $request, $id)
    {
        $booking = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })
            ->with([
                'property:id,title,type,price,governorate_id,city_id,neighborhood_id,street,rooms,area_m2,damage_status,has_water,has_electricity,is_ready,description',
                'property.images',
                'property.governorate:id,name',
                'property.city:id,name',
                'property.neighborhood:id,name',
                'tenant:id,first_name,last_name,national_id,phone,email',
            ])
            ->findOrFail($id);

        // ✅ إضافة duration_text و total بنفس منطق ContractPdfService
        $calc = self::calcDurationAndTotal(
            $booking->start_date,
            $booking->end_date,
            (float) $booking->price
        );
        $booking->duration_text = $calc['duration_text'];
        $booking->total         = $calc['total'];
        $booking->daily_rate    = $calc['daily_rate'];

        // Get tenant reviews
        $tenantReviews = Review::where('reviewee_id', $booking->tenant_id)
            ->where('type', 'host_to_tenant')
            ->with('reviewer:id,first_name,last_name')
            ->latest()
            ->get();

        return response()->json([
            'booking'        => $booking,
            'tenant_reviews' => $tenantReviews,
        ]);
    }

    // Accept a booking request
    public function accept(Request $request, $id)
    {
        $booking = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'يمكن قبول الحجوزات المعلقة فقط.'], 403);
        }

        $minPrice = ceil($booking->price * 0.50);
        $data = $request->validate([
            'discounted_price' => 'nullable|numeric|min:' . $minPrice . '|max:' . $booking->price,
        ]);

        $finalPrice = isset($data['discounted_price']) ? $data['discounted_price'] : $booking->price;

        if (isset($data['discounted_price'])) {
            $booking->update(['price' => $finalPrice]);
        }

        $booking->update(['status' => 'accepted']);
        $booking->property->update(['availability' => 'booked']);

        $rejectedBookings = Booking::where('property_id', $booking->property_id)
            ->where('id', '!=', $booking->id)
            ->where('status', 'pending')
            ->get();

        foreach ($rejectedBookings as $rejected) {
            $rejected->update(['status' => 'rejected']);
            NotificationService::send(
                $rejected->tenant_id,
                'تم رفض طلب الحجز',
                'تم رفض طلب الحجز لـ "' . $booking->property->title . '" لأن العقار تم حجزه من قبل مستأجر آخر.',
                'booking_rejected',
                $rejected->id
            );
        }

        $startDate      = \Carbon\Carbon::parse($booking->start_date);
        $endDate        = \Carbon\Carbon::parse($booking->end_date);
        $duration       = $startDate->diffInDays($endDate);
        $longDuration   = config('contracts.long_duration_days');
        $mediumDuration = config('contracts.medium_duration_days');
        $longReminder   = config('contracts.long_reminder_days');
        $mediumReminder = config('contracts.medium_reminder_days');

        if ($duration > $longDuration) {
            $expiryReminderDate = $endDate->copy()->subDays($longReminder);
        } elseif ($duration >= $mediumDuration) {
            $expiryReminderDate = $endDate->copy()->subDays($mediumReminder);
        } else {
            $expiryReminderDate = null;
        }

        $contract = Contract::create([
            'booking_id'           => $booking->id,
            'tenant_id'            => $booking->tenant_id,
            'host_id'              => $request->user()->id,
            'property_id'          => $booking->property_id,
            'start_date'           => $booking->start_date,
            'end_date'             => $booking->end_date,
            'price'                => $finalPrice,
            'status'               => 'active',
            'expiry_reminder_date' => $expiryReminderDate,
            'expiry_reminder_sent' => false,
        ]);

        $pdfPath = ContractPdfService::generate($contract);
        $contract->update(['pdf_path' => $pdfPath]);

        NotificationService::send($booking->tenant_id, 'تم تفعيل العقد', 'عقد الإيجار الخاص بك لـ "' . $booking->property->title . '" أصبح نشطاً الآن.', 'contract_activated', $contract->id);
        NotificationService::send($request->user()->id, 'تم تفعيل العقد', 'تم تفعيل عقد إيجار لـ "' . $booking->property->title . '".', 'contract_activated', $contract->id);
        NotificationService::send($booking->tenant_id, 'تم قبول طلب الحجز', 'تم قبول طلب الحجز لـ "' . $booking->property->title . '". عقدك أصبح نشطاً الآن.', 'booking_accepted', $booking->id);

        return response()->json([
            'message'     => 'تم قبول الحجز وإنشاء العقد.',
            'booking'     => $booking,
            'contract'    => [
                'id'         => $contract->id,
                'booking_id' => $contract->booking_id,
                'start_date' => $contract->start_date->format('Y-m-d'),
                'end_date'   => $contract->end_date->format('Y-m-d'),
                'price'      => $contract->price,
                'status'     => $contract->status,
                'pdf_path'   => $contract->pdf_path,
                'tenant' => ['id' => $contract->tenant->id, 'first_name' => $contract->tenant->first_name, 'second_name' => $contract->tenant->second_name, 'third_name' => $contract->tenant->third_name, 'last_name' => $contract->tenant->last_name, 'national_id' => $contract->tenant->national_id, 'phone' => $contract->tenant->phone],
                'host'   => ['id' => $contract->host->id,   'first_name' => $contract->host->first_name,   'second_name' => $contract->host->second_name,   'third_name' => $contract->host->third_name,   'last_name' => $contract->host->last_name,   'national_id' => $contract->host->national_id,   'phone' => $contract->host->phone],
            ],
            'final_price' => $finalPrice,
        ]);
    }

    // Reject a booking request
    public function reject(Request $request, $id)
    {
        $booking = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json(['message' => 'يمكن رفض الحجوزات المعلقة فقط.'], 403);
        }

        $booking->update(['status' => 'rejected']);

        NotificationService::send($booking->tenant_id, 'تم رفض طلب الحجز', 'تم رفض طلب الحجز لـ "' . $booking->property->title . '" من قبل المضيف.', 'booking_rejected', $booking->id);

        return response()->json(['message' => 'تم رفض الحجز.', 'booking' => $booking]);
    }
}
