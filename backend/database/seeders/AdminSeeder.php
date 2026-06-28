<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'first_name'  => 'Admin',
            'second_name' => 'System',
            'third_name'  => 'Hima',
            'last_name'   => 'Platform',
            'national_id' => '111111111',
            'email'       => 'admin@hima.app',
            'password'    => bcrypt('password123'),
            'phone'       => '0599000000',
        ]);

        $admin->markEmailAsVerified();
        $admin->assignRole('admin');
    }
}