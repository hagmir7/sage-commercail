<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('depots')->insert([
            ['code' => 'DEPOT 1', 'company_id' => 1],
            ['code' => 'DEPOT 2', 'company_id' => 1],
            ['code' => 'DEPOT 3', 'company_id' => 1],
            ['code' => 'DEPOT 4', 'company_id' => 1],
            ['code' => 'DEPOT 5', 'company_id' => 1],
            ['code' => 'DEPOT 6', 'company_id' => 1],
            ['code' => 'DEPOT 7', 'company_id' => 1],
            ['code' => 'DEPOT 8', 'company_id' => 1],
            ['code' => 'DEPOT 9', 'company_id' => 1],
            ['code' => 'DEPOT 10', 'company_id' => 1],
            ['code' => 'FABRICA', 'company_id' => 1],
            ['code' => 'TRANSFERT EXTERNE', 'company_id' => 1],
            ['code' => 'SERIE MOBLE', 'company_id' => 1],
            ['code' => 'DEPOT SM 1', 'company_id' => 2],
        ]);
    }
}
