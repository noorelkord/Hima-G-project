<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    // List all bookings (hiding tenant PII)
    public function index()
    {
        $bookings = Booking::with([
            'property:id,title,type,price,governorate_id,city_id,neighborhood_id,street',
            'property.images',
            'property.governorate:id,name',
            'property.city:id,name',
            'tenant:id,first_name',  // only first name, no PII
        ])
        ->latest()
        ->get()
        ->map(function ($booking) {
            return [
                'id'          => $booking->id,
                'status'      => $booking->status,
                'price'       => $booking->price,
                'start_date'  => $booking->start_date,
                'end_date'    => $booking->end_date,
                'created_at'  => $booking->created_at,
                'property'    => $booking->property,
                'tenant'      => [
                    'id'         => $booking->tenant->id,
                    'first_name' => $booking->tenant->first_name,
                    // hiding email, phone, national_id (PII)
                ],
            ];
        });
        return response()->json($bookings);
    }
  // View single booking
    public function show($id)
    {
        $booking = Booking::with([
            'property:id,title,type,price,governorate_id,city_id,neighborhood_id,street',
            'property.images',
            'property.governorate:id,name',
            'property.city:id,name',
            'tenant:id,first_name',
        ])->findOrFail($id);

        return response()->json([
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
        ]);
    }
    // Archive (soft delete) a single booking
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);
        $booking->delete(); // soft delete

        return response()->json([
            'message' => 'تم أرشفة الحجز بنجاح.',
        ]);
    }

    // Archive stale pending bookings (older than 48 hours)
    public function archiveStale()
    {
        $cutoff = Carbon::now()->subHours(48);

        $staleBookings = Booking::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = $staleBookings->count();

        foreach ($staleBookings as $booking) {
            $booking->delete(); // soft delete
        }

        return response()->json([
            'message' => "تم أرشفة {$count} حجز معلق قديم.",
            'count'   => $count,
        ]);
    }
}
