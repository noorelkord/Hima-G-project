<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;

class PropertyImageController extends Controller
{
    // Upload images for a property
    public function store(Request $request, $propertyId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $request->validate([
            'images'   => 'required|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $uploaded = [];

        foreach ($request->file('images') as $image) {
            $uploaded[] = PropertyImage::createForProperty($property, $image)->toApiArray();
        }

        return response()->json([
            'message' => 'تم رفع الصور بنجاح.',
            'images'  => $uploaded,
        ], 201);
    }

    // Set an image as main
    public function setMain(Request $request, $propertyId, $imageId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        // Remove current main
        PropertyImage::where('property_id', $property->id)
            ->update(['is_main' => false]);

        // Set new main
        $image = PropertyImage::where('property_id', $property->id)
            ->findOrFail($imageId);
        $image->update(['is_main' => true]);

        return response()->json([
            'message' => 'تم تحديث الصورة الرئيسية.',
        ]);
    }

    // Delete an image
    public function destroy(Request $request, $propertyId, $imageId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $image = PropertyImage::where('property_id', $property->id)
            ->findOrFail($imageId);

        $image->deleteStoredFile();
        $image->delete();

        // If deleted image was main, set first remaining as main
        $firstImage = PropertyImage::where('property_id', $property->id)->first();
        if ($firstImage) {
            $firstImage->update(['is_main' => true]);
        }

        return response()->json([
            'message' => 'تم حذف الصورة بنجاح.',
        ]);
    }

    // List all images for a property
    public function index(Request $request, $propertyId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $images = PropertyImage::where('property_id', $property->id)
            ->get()
            ->map(fn ($image) => $image->toApiArray());

        return response()->json($images);
    }
}
