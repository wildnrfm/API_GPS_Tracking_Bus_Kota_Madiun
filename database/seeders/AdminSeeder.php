<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder {
    public function run(): void {
        User::updateOrCreate(
            ['email' => 'admin@diskominfo.kotamadiun.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin1234'),
                'role' => 'admin',
                'api_token' => null,
            ]
        );

        echo "✓ Admin user created: admin@diskominfo.kotamadiun.com (password: admin1234)\n";
    }
}
