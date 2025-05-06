<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserPermissionController extends Controller
{
    public function assignRoles(Request $request, User $user)
    {
        $validator = Validator::make(
            $request->all(),
            ['roles' => 'required|array']
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'errors' => $validator->errors()
            ], 422);
        }

        $user->syncRoles($request->roles);
        return response()->json(['message' => 'Roles assigned successfully']);
    }

    public function assignPermissions(Request $request, $roleName)
    {

        $validator = Validator::make($request->all(), ['permissions' => 'required|array']);
        if ($validator->fails()) {
            return response()->json([
                'status' => "error",
                'error' => $validator->errors()
            ]);
        }


        try {
            $role = Role::findByName($roleName, 'web');
            $role->syncPermissions([]);
            $role->syncPermissions($request->permissions);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return response()->json([
                'status' => 'error',
                'errors' => ['permission' => $e->getMessage()]
            ], 422);
        }

        return response()->json(['message' => 'Permissions assigned successfully']);
    }
    

    public function getUserRolesAndPermissions($id)
    {
        $user = User::find($id);
        return response()->json([
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name')
        ]);
    }


    public function getAuthUserRolesAndPermissions()
    {
        try {
            $user = auth()->user();

            return response()->json([
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'errors' => ['permission' => $e->getMessage()]
            ], 422);
        }
    }
}
