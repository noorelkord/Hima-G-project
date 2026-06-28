<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $host = User::firstOrCreate(
            ['email' => 'host@hima.app'],
            [
                'first_name' => 'محمود',
                'second_name' => 'أحمد',
                'third_name' => 'سالم',
                'last_name' => 'يوسف',
                'national_id' => '222222222',
                'password' => 'password123',
                'phone' => '0599123456',
            ]
        );

        if (!$host->hasVerifiedEmail()) {
            $host->markEmailAsVerified();
        }

        if (!$host->hasRole('host')) {
            $host->assignRole('host');
        }

        $properties = [
            [
                'title' => 'شقة عائلية قريبة من البحر',
                'description' => 'شقة جاهزة للسكن في موقع هادئ وقريب من الخدمات الأساسية.',
                'governorate' => 'محافظة غزة',
                'city' => 'مدينة غزة',
                'neighborhood' => 'الرمال الشمالي',
                'street' => 'شارع البحر',
                'type' => 'apartment',
                'price' => 350,
                'area_m2' => 120,
                'rooms' => 3,
                'damage_status' => 'renovated',
                'has_water' => true,
                'has_electricity' => true,
                'is_ready' => true,
                'image' => 'apartment.svg',
            ],
            [
                'title' => 'بيت مستقل في بيت لاهيا',
                'description' => 'بيت واسع مناسب للعائلات، يحتوي على مدخل مستقل ومساحة خارجية.',
                'governorate' => 'محافظة شمال غزة',
                'city' => 'بيت لاهيا',
                'neighborhood' => 'مشروع بيت لاهيا',
                'street' => 'شارع المدرسة',
                'type' => 'villa',
                'price' => 420,
                'area_m2' => 180,
                'rooms' => 4,
                'damage_status' => 'partial',
                'has_water' => true,
                'has_electricity' => true,
                'is_ready' => false,
                'image' => 'villa.svg',
            ],
            [
                'title' => 'شقة صغيرة في دير البلح',
                'description' => 'شقة اقتصادية مناسبة لفرد أو عائلة صغيرة، قريبة من السوق.',
                'governorate' => 'محافظة الوسطى',
                'city' => 'دير البلح',
                'neighborhood' => 'وسط البلد',
                'street' => 'شارع السوق',
                'type' => 'apartment',
                'price' => 230,
                'area_m2' => 85,
                'rooms' => 2,
                'damage_status' => 'intact',
                'has_water' => true,
                'has_electricity' => true,
                'is_ready' => true,
                'image' => 'small-apartment.webp',
            ],
            [
                'title' => 'محل تجاري في خانيونس',
                'description' => 'مساحة تجارية في موقع حيوي تصلح لمكتب أو متجر صغير.',
                'governorate' => 'محافظة خانيونس',
                'city' => 'خانيونس',
                'neighborhood' => 'مركز المدينة',
                'street' => 'شارع جمال عبد الناصر',
                'type' => 'commercial',
                'price' => 500,
                'area_m2' => 65,
                'rooms' => 1,
                'damage_status' => 'renovated',
                'has_water' => true,
                'has_electricity' => true,
                'is_ready' => true,
                'image' => 'commercial.svg',
            ],
        ];

        foreach ($properties as $propertyData) {
            $governorate = Governorate::where('name', $propertyData['governorate'])->first();
            $city = City::where('name', $propertyData['city'])
                ->when($governorate, fn ($query) => $query->where('governorate_id', $governorate->id))
                ->first();
            $neighborhood = Neighborhood::where('name', $propertyData['neighborhood'])
                ->when($city, fn ($query) => $query->where('city_id', $city->id))
                ->first();

            $property = Property::updateOrCreate(
                ['title' => $propertyData['title'], 'host_id' => $host->id],
                [
                    'host_id' => $host->id,
                    'governorate_id' => $governorate?->id,
                    'city_id' => $city?->id,
                    'neighborhood_id' => $neighborhood?->id,
                    'street' => $propertyData['street'],
                    'description' => $propertyData['description'],
                    'type' => $propertyData['type'],
                    'price' => $propertyData['price'],
                    'area_m2' => $propertyData['area_m2'],
                    'rooms' => $propertyData['rooms'],
                    'damage_status' => $propertyData['damage_status'],
                    'has_water' => $propertyData['has_water'],
                    'has_electricity' => $propertyData['has_electricity'],
                    'is_ready' => $propertyData['is_ready'],
                    'status' => 'accepted',
                    'availability' => 'available',
                ]
            );

            $this->attachSeedImage($property, $propertyData['image']);
        }
    }

    private function attachSeedImage(Property $property, string $fileName): void
    {
        $source = database_path('seeders/assets/property-images/' . $fileName);
        $target = 'property_images/seed-' . $property->id . '-' . $fileName;

        PropertyImage::where('property_id', $property->id)
            ->where('image_path', 'like', 'property_images/seed-' . $property->id . '-%')
            ->where('image_path', '!=', $target)
            ->get()
            ->each(function (PropertyImage $image) {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            });

        if (File::exists($source) && !Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, File::get($source));
        }

        PropertyImage::updateOrCreate(
            [
                'property_id' => $property->id,
                'image_path' => $target,
            ],
            [
                'is_main' => true,
            ]
        );

        PropertyImage::where('property_id', $property->id)
            ->where('image_path', '!=', $target)
            ->update(['is_main' => false]);
    }
}
