<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $definitions = config('app_permissions', []);
        $registered = Permission::query()->where('guard_name', 'web')->get()->keyBy('name');
        $items = [];

        foreach ($definitions as $categoryKey => $category) {
            foreach (($category['permissions'] ?? []) as $action => $label) {
                $name = "{$categoryKey}.{$action}";
                $permission = $registered->get($name);
                if (! $permission) continue;

                $items[] = [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => $label,
                    'category' => $categoryKey,
                    'category_label' => $category['label'] ?? $categoryKey,
                ];
            }
        }

        return response()->json(['status' => 'success', 'data' => $items]);
    }
}
