<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            CompanySeeder::class,
            DepotSeeder::class,
            StatusSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
        ]);
    }
}
