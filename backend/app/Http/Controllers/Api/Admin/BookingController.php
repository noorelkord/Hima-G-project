<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // ─────────────────────────────────────────────
    // نفس منطق ContractPdfService
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
        ];
    }

    private static function formatDuration(int $years, int $months, int $days): string
    {
        $parts = [];
        if ($years > 0)  $parts[] = $years === 1  ? 'سنة'      : $years  . ' سنوات';
        if ($months > 0) $parts[] = $months === 1 ? 'شهر'      : $months . ' أشهر';
        if ($days > 0)   $parts[] = $days === 1   ? 'يوم واحد' : $days   . ' يوم';
        return empty($parts) ? 'يوم واحد' : implode(' و', $parts);
    }

    // List all bookings (hiding tenant PII)
    public function index()
    {
        $bookings = Booking::with([
            'property:id,title,type,price,governorate_id,city_id,neighborhood_id,street',
            'property.images',
            'property.governorate:id,name',
            'property.city:id,name',
            'tenant:id,first_name',
        ])
        ->latest()
        ->get()
        ->map(function ($booking) {
            return [
                'id'         => $booking->id,
                'status'     => $booking->status,
                'price'      => $booking->price,
                'start_date' => $booking->start_date,
                'end_date'   => $booking->end_date,
                'created_at' => $booking->created_at,
                'property'   => $booking->property,
                'tenant'     => [
                    'id'         => $booking->tenant->id,
                    'first_name' => $booking->tenant->first_name,
                ],
            ];
        });

        return response()->json($bookings);
    }

    // View single booking
    public function show($id)
    {
        $booking = Booking::with([
            'property:id,title,type,price,host_id,governorate_id,city_id,neighborhood_id,street',
            'property.images',
            'property.governorate:id,name',
            'property.city:id,name',
            'property.host:id,first_name,last_name,phone,national_id',
            'tenant:id,first_name',
        ])->findOrFail($id);

        // ✅ إضافة duration_text فقط للأدمن (بدون total لأن السعر مخفي)
        $calc = self::calcDurationAndTotal(
            $booking->start_date,
            $booking->end_date,
            (float) $booking->price
        );

        return response()->json([
            'id'            => $booking->id,
            'status'        => $booking->status,
            'price'         => $booking->price,
            'start_date'    => $booking->start_date,
            'end_date'      => $booking->end_date,
            'created_at'    => $booking->created_at,
            'duration_text' => $calc['duration_text'],
            'property'      => $booking->property,
            'tenant'        => [
                'id'         => $booking->tenant->id,
                'first_name' => $booking->tenant->first_name,
            ],
        ]);
    }

    // Archive (soft delete) a single booking
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete();
        return response()->json(['message' => 'تم أرشفة الحجز بنجاح.']);
    }

    // Archive stale pending bookings (older than 48 hours)
    public function archiveStale()
    {
        $cutoff = Carbon::now()->subHours(48);
        $staleBookings = Booking::where('status', 'pending')->where('created_at', '<', $cutoff)->get();
        $count = $staleBookings->count();
        foreach ($staleBookings as $booking) { $booking->delete(); }
        return response()->json(['message' => "تم أرشفة {$count} حجز معلق قديم.", 'count' => $count]);
    }
}
