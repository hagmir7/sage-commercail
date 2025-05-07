<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $roles = [
            [
                "name"  => "supper_admin",
                'guard_name' => "web"
            ],

            [
                "name"  => "admin",
                'guard_name' => "web"
            ],


            [
                "name"  => "préparateur",
                'guard_name' => "web"
            ],

            [
                "name"  => "opérateur",
                'guard_name' => "web"
            ],

            [
                "name"  => "livraison",
                'guard_name' => "web"
            ],

            [
                "name"  => "caisse",
                'guard_name' => "web"
            ],

            [
                "name"  => "commercial",
                'guard_name' => "web"
            ],
            [
                "name"  => "directeur commercial",
                'guard_name' => "web"
            ],
        ];


        foreach ($roles as $role) {
            Role::create($role);
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


        foreach ($permissions as $permission) {
            Permission::create($permission);
        }
    }
}
