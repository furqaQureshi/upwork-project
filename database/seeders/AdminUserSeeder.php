<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@unsell.test'],
            [
                'name' => 'Marketplace Admin',
                'phone' => '9999999999',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'email_verified_at' => now(),
                'is_admin' => true,
                'password' => Hash::make('Admin@12345'),
            ]
        );
    }
}
