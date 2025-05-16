<?php

namespace Database\Seeders;

use App\Models\ActionType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

       $actions = [
            "Transfert",
            "Impression",
            "Préparation",
            "Fabrication",
            "Montage",
            "Validation",
            "Livraison",
            "Confirmation de livraison",
        ];


        foreach ($actions as $action) {
            ActionType::create([
                'name' => $action
            ]);
        }
    }
}
