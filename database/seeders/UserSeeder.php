<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!User::where("email", "admin@admin.com")->first()) {
            User::factory()->create([
                'name' => 'ADMIN',
                'full_name' => "Hassan Agmir",
                'email' => 'admin@admin.com',
                'phone' => '0648382674',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
            ]);
        }

        if (!User::where("email", "preparation@admin.com")->first()) {
            User::factory()->create([
                'name' => 'adil',
                'full_name' => "Adil Adil",
                'email' => 'preparation@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
            ]);
        }



    }
}
