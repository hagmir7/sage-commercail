<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    private const GUARD = 'web';
    private const PROTECTED_ROLE = 'Super Admin';

    public function index(): JsonResponse
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->with('permissions:id,name,guard_name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => $this->serializeRole($role));

        return response()->json(['status' => 'success', 'data' => $roles]);
    }

    public function show(string $role): JsonResponse
    {
        $model = $this->findRole($role)->load('permissions');
        return response()->json(['status' => 'success', 'data' => $this->serializeRole($model)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', self::GUARD)],
        ]);

        if (strcasecmp(trim($data['name']), self::PROTECTED_ROLE) === 0) {
            throw ValidationException::withMessages(['name' => 'Ce nom de rôle est réservé.']);
        }

        $role = Role::create(['name' => trim($data['name']), 'guard_name' => self::GUARD]);
        return response()->json(['status' => 'success', 'message' => 'Rôle créé.', 'data' => $this->serializeRole($role)], 201);
    }

    public function update(Request $request, string $role): JsonResponse
    {
        $model = $this->findRole($role);
        $this->assertEditable($request, $model);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->where('guard_name', self::GUARD)->ignore($model->id)],
        ]);
        if (strcasecmp(trim($data['name']), self::PROTECTED_ROLE) === 0) {
            throw ValidationException::withMessages(['name' => 'Ce nom de rôle est réservé.']);
        }
        $model->update(['name' => trim($data['name'])]);
        return response()->json(['status' => 'success', 'message' => 'Rôle renommé.', 'data' => $this->serializeRole($model->load('permissions'))]);
    }

    public function destroy(Request $request, string $role): JsonResponse
    {
        $model = $this->findRole($role);
        $this->assertEditable($request, $model);
        $model->delete();
        return response()->json(['status' => 'success', 'message' => 'Rôle supprimé.']);
    }

    public function syncPermissions(Request $request, string $role): JsonResponse
    {
        $model = $this->findRole($role);
        $this->assertEditable($request, $model);

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', self::GUARD)],
        ]);

        // Only permissions defined by the Logilink config may be assigned from this screen.
        $allowedNames = collect(config('app_permissions', []))
            ->flatMap(fn ($category, $key) => collect(array_keys($category['permissions'] ?? []))
                ->map(fn ($action) => "{$key}.{$action}"))
            ->all();

        $requested = $data['permissions'];
        if (array_diff($requested, $allowedNames)) {
            throw ValidationException::withMessages(['permissions' => 'Une ou plusieurs permissions ne sont pas configurées.']);
        }

        // Prevent non-superadmins from granting permission(s) they do not already have.
        if (! $request->user()->hasRole(self::PROTECTED_ROLE, self::GUARD)) {
            $owned = $request->user()->getAllPermissions()->pluck('name')->all();
            if (array_diff($requested, $owned)) {
                abort(403, 'Vous ne pouvez pas attribuer des permissions que vous ne possédez pas.');
            }
        }

        DB::transaction(function () use ($model, $requested) {
            $permissions = Permission::query()
                ->where('guard_name', self::GUARD)
                ->whereIn('name', $requested)
                ->get();
            $model->syncPermissions($permissions);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Autorisations enregistrées.',
            'data' => $this->serializeRole($model->load('permissions')),
        ]);
    }

    private function findRole(string $name): Role
    {
        return Role::query()->where('guard_name', self::GUARD)->where('name', $name)->firstOrFail();
    }

    private function assertEditable(Request $request, Role $role): void
    {
        // Never alter/delete the protected role through this admin page.
        abort_if($role->name === self::PROTECTED_ROLE, 403, 'Le rôle Super Admin est protégé.');
    }

    private function serializeRole(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $role->name,
            'guard_name' => $role->guard_name,
            'protected' => $role->name === self::PROTECTED_ROLE,
            'permissions' => $role->permissions->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()->all(),
        ];
    }
}
