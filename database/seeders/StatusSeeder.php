<?php

namespace Database\Seeders;

use App\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Transféré',   'color' => '#f39c12'], // Orange
            ['name' => 'Reçu',        'color' => '#27ae60'], // Green
            ['name' => 'Fabrication', 'color' => '#2980b9'], // Blue
            ['name' => 'Fabriqué',    'color' => '#3498db'], // Light Blue
            ['name' => 'Montage',     'color' => '#9b59b6'], // Purple
            ['name' => 'Monté',       'color' => '#8e44ad'], // Dark Purple
            ['name' => 'Préparation', 'color' => '#16a085'], // Teal
            ['name' => 'Préparé',     'color' => '#1abc9c'], // Aqua
            ['name' => 'Contrôle',    'color' => '#d35400'], // Dark Orange
            ['name' => 'Contrôlé',    'color' => '#e67e22'], // Bright Orange
            ['name' => 'Validé',      'color' => '#2ecc71'], // Emerald
            ['name' => 'Prêt',        'color' => '#34495e'], // Navy Blue
            ['name' => 'À livrer',    'color' => '#e74c3c'], // Red
            ['name' => 'Livré',       'color' => '#2c3e50'], // Dark Blue
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }
    }
}
