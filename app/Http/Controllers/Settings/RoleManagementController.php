<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRoleRequest;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Services\Ai\PrivilegeGrantGuard;
use App\Services\MisPermissionRegistrar;
use App\Support\AiResourceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class RoleManagementController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        private MisPermissionRegistrar $permissions,
        private PrivilegeGrantGuard $privilegeGrantGuard,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'roles.view');

        $this->permissions->sync();

        return Inertia::render('settings/Roles/Index', [
            'roles' => Role::query()
                ->with('permissions:id,name')
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => $this->matrixPermissions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'roles.create');

        $this->permissions->sync();

        return Inertia::render('settings/Roles/Create', [
            'permissions' => $this->matrixPermissions(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorizePermission($request, 'roles.create');

        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];

        /** @var \App\Models\User $actor */
        $actor = $request->user();
        $this->privilegeGrantGuard->assertPermissionsAssignable($actor, $permissions);
        if (isset($validated['name'])) {
            $protected = AiResourceCatalog::allProtectedNames();
            if (in_array($validated['name'], $protected, true) && ! $actor->hasRole($validated['name'])) {
                return back()->withErrors(['name' => 'You cannot create a protected role name.']);
            }
        }

        $role = Role::findOrCreate($validated['name']);
        $role->syncPermissions($permissions);

        return redirect()
            ->route('settings.roles.index')
            ->with('success', __('Role created.'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorizePermission($request, 'roles.edit');

        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];

        /** @var \App\Models\User $actor */
        $actor = $request->user();
        $protected = AiResourceCatalog::allProtectedNames();

        try {
            $this->privilegeGrantGuard->assertCanMutateProtectedNamedRow(
                $actor,
                $role->name,
                $protected,
                $validated['name'] ?? null,
                true,
            );
            $this->privilegeGrantGuard->assertPermissionsAssignable($actor, $permissions);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['permissions' => $e->getMessage()]);
        }

        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($permissions);

        return back()->with('success', __('Role updated.'));
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizePermission($request, 'roles.delete');

        if (in_array($role->name, AiResourceCatalog::allProtectedNames(), true)) {
            return back()->with('error', __('This role cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            return back()->with(
                'error',
                __('Cannot delete a role that is assigned to users.'),
            );
        }

        $role->delete();

        return back()->with('success', __('Role moved to trash.'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'roles.delete');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $roles = Role::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $deleted = 0;

        foreach ($roles as $role) {
            if (in_array($role->name, AiResourceCatalog::allProtectedNames(), true)) {
                continue;
            }

            if ($role->users()->exists()) {
                continue;
            }

            $role->delete();
            $deleted++;
        }

        return back()->with(
            'success',
            __('Deleted :count role(s).', ['count' => $deleted]),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Permission>
     */
    protected function matrixPermissions()
    {
        $defined = $this->permissions->definedPermissionNames();

        return Permission::query()
            ->whereIn('name', $defined)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
