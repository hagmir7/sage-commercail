<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Seed all app permissions from the configuration.
     */
    public function run(): void
    {
        $guard = 'web';

        // Clear the permissions cache before seeding.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $categories = config('app_permissions', []);

        if (empty($categories)) {
            $this->command?->warn(
                'No permissions found in config/app_permissions.php'
            );

            return;
        }

        $created = 0;
        $existing = 0;

        foreach ($categories as $categoryKey => $category) {

            $categoryLabel = $category['label'] ?? $categoryKey;

            $permissions = $category['permissions'] ?? [];

            $this->command?->info(
                "Processing category: {$categoryLabel}"
            );

            foreach ($permissions as $action => $label) {

                // Example: mouvements.in.creer
                $permissionName = "{$categoryKey}.{$action}";

                $permission = Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => $guard,
                ]);

                if ($permission->wasRecentlyCreated) {
                    $created++;

                    $this->command?->line(
                        "  + Created: {$permissionName}"
                    );
                } else {
                    $existing++;
                }
            }
        }

        // Refresh cache after creating permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $total = Permission::where('guard_name', $guard)->count();

        $this->command?->newLine();

        $this->command?->info(
            'Permissions seeding completed successfully!'
        );

        $this->command?->table(
            ['Description', 'Total'],
            [
                ['Categories processed', count($categories)],
                ['New permissions', $created],
                ['Existing permissions', $existing],
                ['Total permissions in database', $total],
            ]
        );

        Log::info('app permissions seeded', [
            'categories' => count($categories),
            'created' => $created,
            'existing' => $existing,
            'total' => $total,
        ]);
    }
}
