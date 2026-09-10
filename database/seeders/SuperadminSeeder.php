<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Seed the default superadmin user.
     */
    public function run(): void
    {
        $superadmin = User::firstOrCreate(
            ['email' => 'admin@datasciencetool.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $superadmin->assignRole('superadmin');
    }
}
