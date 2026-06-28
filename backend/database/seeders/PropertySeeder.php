<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Governorate;
use App\Models\Neighborhood;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

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

            Property::updateOrCreate(
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
        }
    }
}
