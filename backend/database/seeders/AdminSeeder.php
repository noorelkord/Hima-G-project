<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@hima.app');
        $password = env('ADMIN_PASSWORD');

        $admin = User::firstOrNew(['email' => $email]);

        $admin->fill([
            'first_name'  => 'Admin',
            'second_name' => 'System',
            'third_name'  => 'Hima',
            'last_name'   => 'Platform',
            'national_id' => '111111111',
            'phone'       => '0599000000',
        ]);

        if (!$admin->exists || $password) {
            $admin->password = Hash::make($password ?: 'password123');
        }

        $admin->save();

        $admin->markEmailAsVerified();

        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}
