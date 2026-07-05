<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Review;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    // Public listing with search & filtering
    public function index(Request $request)
    {
        $query = Property::public()
            ->with(['images', 'mainImage', 'governorate', 'city', 'neighborhood'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('governorate_id')) {
            $query->where('governorate_id', $request->governorate_id);
        }
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }
        if ($request->filled('neighborhood_id')) {
            $query->where('neighborhood_id', $request->neighborhood_id);
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        if ($request->filled('rooms')) {
            $query->where('rooms', $request->rooms);
        }
        if ($request->filled('min_area')) {
            $query->where('area_m2', '>=', $request->min_area);
        }
        if ($request->filled('max_area')) {
            $query->where('area_m2', '<=', $request->max_area);
        }
        if ($request->filled('damage_status')) {
            $query->where('damage_status', $request->damage_status);
        }
        if ($request->filled('has_water')) {
            $query->where('has_water', $request->boolean('has_water'));
        }
        if ($request->filled('has_electricity')) {
            $query->where('has_electricity', $request->boolean('has_electricity'));
        }
        if ($request->filled('is_ready')) {
            $query->where('is_ready', $request->boolean('is_ready'));
        }

        $properties = $query->get();

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

    // View a single public property
    public function show($id)
    {
        $property = Property::public()
            ->with([
                'host:id,first_name,last_name',
                'images',
                'mainImage',
                'governorate:id,name',
                'city:id,name',
                'neighborhood:id,name',
            ])
            ->findOrFail($id);

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

    // Generate WhatsApp contact link
    public function whatsappLink($id)
    {
        $property = Property::public()
            ->with('host:id,first_name,last_name,phone')
            ->findOrFail($id);

        if (!$property->host->phone) {
            return response()->json([
                'message' => 'المضيف لم يُدرج رقم هاتف.',
            ], 404);
        }

        $message = "مرحباً، وجدت عقارك على منصة حمى وأنا مهتم:\n\n"
            . "العقار: {$property->title}\n"
            . "الموقع: {$property->location}\n"
            . "السعر: {$property->price} شهرياً\n"
            . "النوع: {$property->type}\n\n"
            . "هل يمكننا مناقشة التفاصيل؟";

        $phone = preg_replace('/[^0-9+]/', '', $property->host->phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } elseif (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        } elseif (str_starts_with($phone, '0')) {
            $phone = '970' . substr($phone, 1);
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);
        $link  = 'https://wa.me/' . $phone . '?text=' . urlencode($message);

        return response()->json([
            'whatsapp_link' => $link,
            'host'          => $property->host->first_name . ' ' . $property->host->last_name,
            'phone'         => $property->host->phone,
        ]);
    }
}
