<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        $roles = [
            ["name" => "supper_admin", 'guard_name' => "web"],
            ["name" => "admin", 'guard_name' => "web"],
            ["name" => "preparateur", 'guard_name' => "web"],
            ["name" => "preparation_cuisine", 'guard_name' => "web"],
            ["name" => "preparation_trailer", 'guard_name' => "web"],
            ["name" => "fabrication", 'guard_name' => "web"],
            ["name" => "montage", 'guard_name' => "web"],
            ["name" => "magasinier", 'guard_name' => "web"],
            ["name" => "commercial", 'guard_name' => "web"],
            ["name" => "expedition", 'guard_name' => "web"],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate($roleData);
        }

        $permissions = [
            ['name' => 'view:users', 'guard_name' => 'web'],
            ['name' => 'create:users', 'guard_name' => 'web'],
            ['name' => 'edit:users', 'guard_name' => 'web'],
            ['name' => 'delete:users', 'guard_name' => 'web'],
            ['name' => 'create:roles', 'guard_name' => 'web'],
            ['name' => 'view:roles', 'guard_name' => 'web'],
            ['name' => 'delete:roles', 'guard_name' => 'web'],
            ['name' => 'edit:roles', 'guard_name' => 'web'],
        ];


        foreach ($permissions as $permissionData) {

            Permission::firstOrCreate($permissionData);
        }

        $supperAdminRole = Role::where('name', 'supper_admin')->first();
        $allPermissions = Permission::all();
        $supperAdminRole->syncPermissions($allPermissions);
    }
}
