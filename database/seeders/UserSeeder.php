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
        User::create([
            'name' => "admin",
            'full_name' => "Hassan Agmir",
            'phone' => "0648382674",
            'email' => "admin@admin.com",
            'password' => Hash::make('password')
        ]);
    }
}
