<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Neighborhood;

class LocationController extends Controller
{
    // Get all governorates
    public function governorates()
    {
        return response()->json(Governorate::all());
    }

    // Get cities by governorate
    public function cities($governorateId)
    {
        $cities = City::where('governorate_id', $governorateId)->get();
        return response()->json($cities);
    }
    
    // Get neighborhoods by city
    public function neighborhoods($cityId)
    {
        $neighborhoods = Neighborhood::where('city_id', $cityId)->get();
        return response()->json($neighborhoods);
    }
}