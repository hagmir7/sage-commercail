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
            ['name' => 'Transféré',   'color' => '#FF6B35'], // Vibrant orange
            ['name' => 'Reçu',        'color' => '#10B981'], // Emerald green
            ['name' => 'Fabrication', 'color' => '#3B82F6'], // Blue
            ['name' => 'Fabriqué',    'color' => '#06B6D4'], // Cyan
            ['name' => 'Montage',     'color' => '#8B5CF6'], // Purple
            ['name' => 'Monté',       'color' => '#A855F7'], // Violet
            ['name' => 'Préparation', 'color' => '#059669'], // Teal
            ['name' => 'Préparé',     'color' => '#14B8A6'], // Light teal
            ['name' => 'Contrôle',    'color' => '#F59E0B'], // Amber
            ['name' => 'Contrôlé',    'color' => '#EF4444'], // Red
            ['name' => 'Validé',      'color' => '#22C55E'], // Green
            ['name' => 'Livraison',   'color' => '#6366F1'], // Indigo
            ['name' => 'Chargement',  'color' => '#7C3AED'], // Deep purple
            ['name' => 'Livré',       'color' => '#15803D'], // Dark green
        ];

        foreach ($statuses as $status) {
            Status::create($status);
        }
    }
}
