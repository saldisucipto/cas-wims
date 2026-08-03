<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'role' => 'Administrator',
                'email' => 'admin@wims.local',
                'password' => Hash::make('admin123'),
            ]
        );

        User::query()->updateOrCreate(
            ['username' => 'leader'],
            [
                'name' => 'Outbound Leader',
                'role' => 'Leader',
                'email' => 'leader@wims.local',
                'password' => Hash::make('leader123'),
            ]
        );
    }
}
