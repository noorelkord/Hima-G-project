<?php

namespace App\Http\Controllers\Api\Host;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PropertyImageController extends Controller
{
    // Upload images for a property
    public function store(Request $request, $propertyId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $request->validate([
            'images'   => 'required|array|max:10',
            'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $uploaded = [];

        foreach ($request->file('images') as $image) {
            $path = $image->store('property_images', 'public');

            // First image is main by default
            $isMain = $property->images()->count() === 0 && count($uploaded) === 0;

            $propertyImage = PropertyImage::create([
                'property_id' => $property->id,
                'image_path'  => $path,
                'is_main'     => $isMain,
            ]);

            $uploaded[] = [
                'id'       => $propertyImage->id,
                'url'      => asset('storage/' . $path),
                'is_main'  => $isMain,
            ];
        }

        return response()->json([
            'message' => 'Images uploaded successfully.',
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
            'message' => 'Main image updated.',
        ]);
    }

    // Delete an image
    public function destroy(Request $request, $propertyId, $imageId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $image = PropertyImage::where('property_id', $property->id)
            ->findOrFail($imageId);

        // Delete from storage
        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        // If deleted image was main, set first remaining as main
        $firstImage = PropertyImage::where('property_id', $property->id)->first();
        if ($firstImage) {
            $firstImage->update(['is_main' => true]);
        }

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }

    // List all images for a property
    public function index(Request $request, $propertyId)
    {
        $property = Property::where('host_id', $request->user()->id)
            ->findOrFail($propertyId);

        $images = PropertyImage::where('property_id', $property->id)
            ->get()
            ->map(function ($image) {
                return [
                    'id'      => $image->id,
                    'url'     => asset('storage/' . $image->image_path),
                    'is_main' => $image->is_main,
                ];
            });

        return response()->json($images);
    }
}