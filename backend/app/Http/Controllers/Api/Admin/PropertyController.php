<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    // List all properties
    public function index()
    {
        $properties = Property::with('host:id,first_name,last_name,email')
            ->latest()
            ->get();

        return response()->json($properties);
    }

    // List pending properties only
    public function pending()
    {
        $properties = Property::with('host:id,first_name,last_name,email')
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json($properties);
    }

    // Accept a property
    public function accept($id) {
        $property = Property::findOrFail($id);
        if (!in_array($property->status, ['pending', 'rejected'])) {
              return response()->json([
             'message' => 'Only pending or rejected properties can be accepted.',
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
            'Property Approved',
            'Your property "' . $property->title . '" has been approved and is now live.',
            'property_approved',
            $property->id
        );

        return response()->json([
            'message'  => 'Property accepted and is now live.',
            'property' => $property,
        ]);
    }

    // Reject a property
    public function reject(Request $request, $id) {
        $property = Property::findOrFail($id);
        if (!in_array($property->status, ['pending', 'accepted'])) {
            return response()->json([
                'message' => 'Only pending or accepted properties can be rejected.',
                ], 403);
        }
        // Cannot reject if booked (active contract)
        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'Cannot reject a property with an active contract.',
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
                'Booking Cancelled',
                'Your booking request for "' . $property->title . '" has been cancelled. 
                The property has been temporarily suspended by the administration.',
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
            'Property Rejected',
            'Your property "' . $property->title . '" has been rejected. Reason: ' . $data['rejection_reason'],
            'property_rejected',
            $property->id
        );
        return response()->json([
            'message'  => 'Property rejected.',
            'property' => $property,
        ]);
    }

    // Archive a violating property
    public function destroy($id)
    {
        $property = Property::findOrFail($id);

        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'Cannot delete a booked property.',
            ], 403);
        }

        $property->delete();

        return response()->json([
            'message' => 'Property archived successfully.',
        ]);
    }
}
