<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Property;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // List all favorites
    public function index(Request $request)
    {
        $favorites = Favorite::where('tenant_id', $request->user()->id)
            ->with('property:id,title,type,price,availability,status,governorate_id,city_id,neighborhood_id,street')
            ->latest()
            ->get();

        return response()->json($favorites);
    }

    // Add to favorites
    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => 'required|exists:properties,id',
        ]);

        $property = Property::findOrFail($data['property_id']);

        // Check if already favorited
        $exists = Favorite::where('tenant_id', $request->user()->id)
            ->where('property_id', $data['property_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Property already in favorites.',
            ], 403);
        }

        $favorite = Favorite::create([
            'tenant_id'   => $request->user()->id,
            'property_id' => $data['property_id'],
        ]);

        return response()->json([
            'message'  => 'Property added to favorites.',
            'favorite' => $favorite,
        ], 201);
    }

    // Remove from favorites
    public function destroy(Request $request, $propertyId)
    {
        $favorite = Favorite::where('tenant_id', $request->user()->id)
            ->where('property_id', $propertyId)
            ->firstOrFail();

        $favorite->delete();

        return response()->json([
            'message' => 'Property removed from favorites.',
        ]);
    }
}