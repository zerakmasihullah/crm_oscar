<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class MisPermissionRegistrar
{
    /** @return list<string> */
    public function definedPermissionNames(): array
    {
        $names = [];

        foreach (array_keys(config('mis_permissions.modules', [])) as $module) {
            $verbs = config("mis_permissions.modules.{$module}.verbs");
            if (! is_array($verbs)) {
                $verbs = config('mis_permissions.verbs', []);
            }

            foreach ($verbs as $verb) {
                $names[] = "{$module}.{$verb}";
            }

            $extra = config("mis_permissions.modules.{$module}.extra", []);

            foreach ($extra as $permission) {
                $names[] = "{$module}.{$permission}";
            }
        }

        return array_values(array_unique($names));
    }

    /** @return list<Permission> */
    public function sync(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $defined = $this->definedPermissionNames();
        $permissions = [];

        foreach ($defined as $name) {
            $permissions[] = Permission::findOrCreate($name);
        }

        Permission::query()
            ->whereNotIn('name', $defined)
            ->orderBy('id')
            ->each(function (Permission $permission): void {
                $permission->roles()->detach();
                $permission->users()->detach();
                $permission->delete();
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissions;
    }
}
