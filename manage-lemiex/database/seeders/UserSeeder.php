<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AuthProvider;
use App\Models\UserProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email'             => 'admin@wood.local',
                'username'          => 'admin',
                'role_id'           => 1, // Admin
                'status'            => 'Active',
                'email_verified_at' => now(),
                'api_key'           => Str::random(40),
                'password'          => 'admin123',
                'profile'           => [
                    'first_name' => 'Admin',
                    'last_name'  => 'Wood',
                ],
            ],
            [
                'email'             => 'seller@wood.local',
                'username'          => 'seller',
                'role_id'           => 2, // Seller
                'status'            => 'Active',
                'email_verified_at' => now(),
                'api_key'           => Str::random(40),
                'password'          => 'seller123',
                'profile'           => [
                    'first_name' => 'Seller',
                    'last_name'  => 'Wood',
                ],
            ],
        ];

        foreach ($users as $data) {
            $password = $data['password'];
            $profile  = $data['profile'];
            unset($data['password'], $data['profile']);

            $user = User::firstOrCreate(['email' => $data['email']], $data);

            AuthProvider::firstOrCreate(
                ['user_id' => $user->id, 'provider' => 'local'],
                ['password' => Hash::make($password)]
            );

            UserProfile::firstOrCreate(
                ['user_id' => $user->id],
                array_merge(['user_id' => $user->id], $profile)
            );
        }
    }
}
