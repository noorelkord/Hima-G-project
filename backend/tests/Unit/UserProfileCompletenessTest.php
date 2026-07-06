<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserProfileCompletenessTest extends TestCase
{
    public function test_profile_is_incomplete_when_required_names_are_blank(): void
    {
        $user = new User([
            'second_name' => '   ',
            'third_name' => 'Ahmad',
            'last_name' => 'Saleh',
            'national_id' => '123456789',
            'phone' => '+970599123456',
        ]);

        $this->assertFalse($user->isProfileComplete());
    }

    public function test_profile_is_incomplete_when_identity_fields_are_malformed(): void
    {
        $user = new User([
            'second_name' => 'Ali',
            'third_name' => 'Ahmad',
            'last_name' => 'Saleh',
            'national_id' => '123',
            'phone' => '0599123456',
        ]);

        $this->assertFalse($user->isProfileComplete());
    }

    public function test_profile_is_complete_when_all_required_fields_are_valid(): void
    {
        $user = new User([
            'second_name' => 'Ali',
            'third_name' => 'Ahmad',
            'last_name' => 'Saleh',
            'national_id' => '123456789',
            'phone' => '+970599123456',
        ]);

        $this->assertTrue($user->isProfileComplete());
    }
}
