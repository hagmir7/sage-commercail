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
        if (!User::where("email", "admin@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'admin',
                'full_name' => "Hassan Agmir",
                'email' => 'admin@admin.com',
                'phone' => '0648382674',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
            ]);
            $user->assignRole("supper_admin");
            $user->assignRole("commercial");
        }

        if (!User::where("email", "preparation@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'adil',
                'full_name' => "Adil Adil",
                'email' => 'preparation@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 1
            ]);
            $user->assignRole("preparation");
            $user->assignRole("controleur");
        }

        if (!User::where("email", "bayou@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'bayou',
                'full_name' => "Ahmend El Bayou",
                'email' => 'bayou@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 1,

            ]);
            $user->assignRole("fabrication");
        }


        if (!User::where("email", "montage@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'said',
                'full_name' => "Said Montage",
                'email' => 'montage@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 1
            ]);
            $user->assignRole("montage");
        }

        if (!User::where("email", "fatima@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'fati',
                'full_name' => "Fatima Serie",
                'email' => 'fatima@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 2
            ]);
            $user->assignRole("preparation");
        }

        if (!User::where("email", "peinture@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'nordin',
                'full_name' => "Nordin Serie",
                'email' => 'peinture@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 2
            ]);
            $user->assignRole("fabrication");
        }


        if (!User::where("email", "p1@admin.com")->first()) {
           $user = User::factory()->create([
                'name' => 'p1',
                'full_name' => "Ayoub",
                'email' => 'p1@admin.com',
                'phone' => '1234567890',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 1
            ]);
            $user->assignRole("preparation_trailer");
        }

        if (!User::where("email", "p2@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'p2',
                'full_name' => "El ghayat Mohamed",
                'email' => 'p2@admin.com',
                'phone' => '24322342345',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 1
            ]);
            $user->assignRole("preparation_cuisine");
        }

        if (!User::where("email", "p3@admin.com")->first()) {
            $user = User::factory()->create([
                'name' => 'p3',
                'full_name' => "Abd Razzaq",
                'email' => 'p3@admin.com',
                'phone' => '24322342345',
                'password' => Hash::make("password"),
                'email_verified_at' => now(),
                'company_id' => 2
            ]);
            $user->assignRole("preparation_cuisine");
        }
    }
}
