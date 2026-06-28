<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Contract;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use App\Services\ContractPdfService;

class BookingController extends Controller
{
    // List all booking requests for host's properties
    public function index(Request $request)
    {
        $bookings = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })
            ->with('property:id,title,governorate_id,city_id,neighborhood_id,street', 'tenant:id,first_name,last_name,email,phone')
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Accept a booking request
    public function accept(Request $request, $id)
    {
        $booking = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })
            ->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be accepted.',
            ], 403);
        }

        // Optional discount
        $minPrice = ceil($booking->price * 0.50); // لا يقل عن 50% من السعر الأصلي
        $data = $request->validate([
            'discounted_price' => 'nullable|numeric|min:' . $minPrice . '|max:' . $booking->price,
        ]);

        // Apply discount if provided
        $finalPrice = isset($data['discounted_price'])
            ? $data['discounted_price']
            : $booking->price;

        // Update booking price if discounted
        if (isset($data['discounted_price'])) {
            $booking->update(['price' => $finalPrice]);
        }

        // Accept the booking
        $booking->update(['status' => 'accepted']);

        // Update property availability to booked
        $booking->property->update(['availability' => 'booked']);

        // Reject all other pending bookings for the same property
        $rejectedBookings = Booking::where('property_id', $booking->property_id)
            ->where('id', '!=', $booking->id)
            ->where('status', 'pending')
            ->get();

        foreach ($rejectedBookings as $rejected) {
            $rejected->update(['status' => 'rejected']);

            // Notify other tenants their booking was rejected
            NotificationService::send(
                $rejected->tenant_id,
                'تم رفض طلب الحجز',
                'تم رفض طلب الحجز لـ "' . $booking->property->title . '" لأن العقار تم حجزه من قبل مستأجر آخر.',
                'booking_rejected',
                $rejected->id
            );
        }

        // Calculate expiry reminder date
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

        // Auto-create contract with final price
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
        // Generate PDF
        $pdfPath = ContractPdfService::generate($contract);
        $contract->update(['pdf_path' => $pdfPath]);
        // Notify tenant - contract activated
        NotificationService::send(
            $booking->tenant_id,
            'تم تفعيل العقد',
            'عقد الإيجار الخاص بك لـ "' . $booking->property->title . '" أصبح نشطاً الآن.',
            'contract_activated',
            $contract->id
        );

        // Notify host - contract activated
        NotificationService::send(
            $request->user()->id,
            'تم تفعيل العقد',
            'تم تفعيل عقد إيجار لـ "' . $booking->property->title . '".',
            'contract_activated',
            $contract->id
        );

        // Notify tenant their booking was accepted
        NotificationService::send(
            $booking->tenant_id,
            'تم قبول طلب الحجز',
            'تم قبول طلب الحجز لـ "' . $booking->property->title . '". عقدك أصبح نشطاً الآن.',
            'booking_accepted',
            $booking->id
        );

        return response()->json([
            'message'     => 'Booking accepted and contract created.',
            'booking'     => $booking,
            'contract'    => [
                'id'         => $contract->id,
                'booking_id' => $contract->booking_id,
                'start_date' => $contract->start_date,
                'end_date'   => $contract->end_date,
                'price'      => $contract->price,
                'status'     => $contract->status,
                'pdf_path'   => $contract->pdf_path,
                'tenant' => [
                    'id'          => $contract->tenant->id,
                    'first_name'  => $contract->tenant->first_name,
                    'second_name' => $contract->tenant->second_name,
                    'third_name'  => $contract->tenant->third_name,
                    'last_name'   => $contract->tenant->last_name,
                    'national_id' => $contract->tenant->national_id,
                    'phone'       => $contract->tenant->phone,
                ],
                'host' => [
                    'id'          => $contract->host->id,
                    'first_name'  => $contract->host->first_name,
                    'second_name' => $contract->host->second_name,
                    'third_name'  => $contract->host->third_name,
                    'last_name'   => $contract->host->last_name,
                    'national_id' => $contract->host->national_id,
                    'phone'       => $contract->host->phone,
                ],
            ],
            'final_price' => $finalPrice,
        ]);
    }

    // Reject a booking request
    public function reject(Request $request, $id)
    {
        $booking = Booking::whereHas('property', function ($query) use ($request) {
            $query->where('host_id', $request->user()->id);
        })
            ->findOrFail($id);

        if ($booking->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending bookings can be rejected.',
            ], 403);
        }

        $booking->update(['status' => 'rejected']);

        // Notify tenant
        NotificationService::send(
            $booking->tenant_id,
            'تم رفض طلب الحجز',
            'تم رفض طلب الحجز لـ "' . $booking->property->title . '" من قبل المضيف.',
            'booking_rejected',
            $booking->id
        );

        return response()->json([
            'message' => 'Booking rejected.',
            'booking' => $booking,
        ]);
    }
}
