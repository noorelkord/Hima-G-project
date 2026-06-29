<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\Request;
use App\Services\NotificationService;
class PropertyController extends Controller
{
    // List all properties belonging to the host
    public function index(Request $request)
    {
        $properties = Property::where('host_id', $request->user()->id)
            ->with(['images', 'mainImage', 'governorate', 'city', 'neighborhood'])
            ->latest()
            ->get();

        return response()->json($properties);
    }

    // Submit a new property
    public function store(Request $request)
    {
        // تحقق من اكتمال البيانات
        if (!$request->user()->isHostReady()) {
            return response()->json([
                'message'  => 'يرجى إكمال ملفك الشخصي قبل إضافة عقار.',
                'redirect' => 'profile/complete',
            ], 403);
        }
        $data = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'required|in:apartment,villa,land,chalet,commercial,parking',
            'governorate_id'  => 'required|exists:governorates,id',
            'city_id'         => 'required|exists:cities,id',
            'neighborhood_id' => 'nullable|exists:neighborhoods,id',
            'street'          => 'nullable|string|max:255',
            'price'           => 'required|numeric|min:0',
            'area_m2'         => 'nullable|numeric|min:0',
            'rooms'           => 'nullable|integer|min:0',
            'damage_status'   => 'required|in:intact,partial,renovated',
            'has_water'       => 'boolean',
            'has_electricity' => 'boolean',
            'is_ready'        => 'boolean',
        ]);

        $property = Property::create([
            ...$data,
            'host_id'      => $request->user()->id,
            'status'       => 'pending',
            'availability' => 'not_available',
        ]);
        // Notify all admins
        $admins = \App\Models\User::role('admin')->get();
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin->id,
                'عقار جديد مُقدَّم',
                'تم تقديم عقار جديد "' . $property->title . '" ويتطلب مراجعتك.',
                'new_property_submitted',
                $property->id
            );
        }
        return response()->json([
            'message'  => 'تم تقديم العقار بنجاح. بانتظار موافقة الإدارة.',
            'property' => $property,
        ], 201);
    }

    // View a single property
    public function show(Request $request, $id)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->with(['images', 'mainImage', 'governorate', 'city', 'neighborhood'])
            ->findOrFail($id);

        return response()->json($property);
    }

    // Edit a property
    public function update(Request $request, $id)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($id);

        // Cannot edit if booked
        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'لا يمكن تعديل عقار محجوز.',
            ], 403);
        }

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'type'            => 'sometimes|in:apartment,villa,land,chalet,commercial,parking',
            'governorate_id'  => 'sometimes|exists:governorates,id',
            'city_id'         => 'sometimes|exists:cities,id',
            'neighborhood_id' => 'sometimes|nullable|exists:neighborhoods,id',
            'street'          => 'nullable|string|max:255',
            'price'           => 'sometimes|numeric|min:0',
            'area_m2'         => 'nullable|numeric|min:0',
            'rooms'           => 'nullable|integer|min:0',
            'damage_status'   => 'sometimes|in:intact,partial,renovated',
            'has_water'       => 'boolean',
            'has_electricity' => 'boolean',
            'is_ready'        => 'boolean',
        ]);

        // Update essential fields to include location fields
        $essentialFields = ['title', 'type', 'governorate_id', 'city_id', 'neighborhood_id', 'price', 'damage_status'];

        $hasEssentialChange = collect($essentialFields)->some(fn($f) => isset($data[$f]));

        if ($hasEssentialChange && in_array($property->status, ['accepted', 'rejected'])) {
            $data['status']           = 'pending';
            $data['availability']     = 'not_available';
            $data['rejection_reason'] = null;

            // Cancel all pending bookings and notify tenants
            $pendingBookings = $property->bookings()->where('status', 'pending')->get();
            foreach ($pendingBookings as $booking) {
                $booking->update(['status' => 'cancelled']);
                 NotificationService::send(
                $booking->tenant_id,
                'تم إلغاء الحجز',
                'تم إلغاء طلب الحجز لـ "' . $property->title . '" بسبب تحديث تفاصيل العقار وهو الآن قيد المراجعة.',
                'booking_cancelled',
                  $booking->id
        );
    }
}

$property->update($data);
        
        // Notify admins only if essential data changed
        if ($hasEssentialChange) {
            $admins = \App\Models\User::role('admin')->get();
            foreach ($admins as $admin) {
                NotificationService::send(
                    $admin->id,
                    'تم تعديل العقار',
                    'تم تعديل العقار "' . $property->title . '" وقد يتطلب مراجعتك.',
                    'property_modified',
                    $property->id
                );
            }
        }
        return response()->json([
            'message'  => 'تم تحديث العقار بنجاح.',
            'property' => $property,
        ]);
    }

    // Toggle availability (available <-> not_available)
    public function toggleAvailability(Request $request, $id)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($id);

        if ($property->status !== 'accepted') {
            return response()->json([
                'message' => 'يجب قبول العقار قبل تغيير الإتاحة.',
            ], 403);
        }

        if ($property->availability === 'booked') {
            return response()->json([
                'message' => 'لا يمكن تغيير إتاحة عقار محجوز.',
            ], 403);
        }

        $property->availability = $property->availability === 'available'
            ? 'not_available'
            : 'available';

        $property->save();

        return response()->json([
            'message'      => 'تم تحديث الإتاحة.',
            'availability' => $property->availability,
        ]);
    }

    // Archive (soft delete) a property
    public function destroy(Request $request, $id)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($id);

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
