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
        User::factory()->create([
            'name' => 'ADMIN',
            'full_name' => "Hassan Agmir",
            'email' => 'admin@admin.com',
            'phone' => '0648382674',
            'password' => Hash::make("password"),
            'email_verified_at' => now(),
        ]);
    }
}
