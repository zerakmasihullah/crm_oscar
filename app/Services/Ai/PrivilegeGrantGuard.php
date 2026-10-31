<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiResourceCatalog;
use InvalidArgumentException;
use App\Models\Role;

/**
 * Prevents privilege amplification when assigning roles or permissions.
 * Catalog-driven via privilege_fields / protected_names; unrestricted roles from config.
 */
class PrivilegeGrantGuard
{
    public function isUnrestricted(User $actor): bool
    {
        $unrestricted = config('ai.security.unrestricted_roles', ['Owner']);
        if (! is_array($unrestricted) || $unrestricted === []) {
            return false;
        }

        foreach ($unrestricted as $roleName) {
            if (! is_string($roleName) || $roleName === '') {
                continue;
            }
            if ($actor->hasRole($roleName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>|mixed  $permissions
     */
    public function assertPermissionsAssignable(User $actor, mixed $permissions): void
    {
        if (! is_array($permissions)) {
            return;
        }

        if ($this->isUnrestricted($actor)) {
            return;
        }

        $names = array_values(array_filter($permissions, fn ($p) => is_string($p) && $p !== ''));
        if ($names === []) {
            return;
        }

        $actorPerms = $actor->getAllPermissions()->pluck('name')->all();
        $actorLookup = array_fill_keys($actorPerms, true);

        foreach ($names as $permission) {
            if (! isset($actorLookup[$permission])) {
                throw new InvalidArgumentException(
                    "You cannot grant permission [{$permission}] because you do not have it.",
                );
            }
        }
    }

    /**
     * @param  list<string>|mixed  $roleNames
     */
    public function assertRolesAssignable(User $actor, mixed $roleNames): void
    {
        if (! is_array($roleNames)) {
            return;
        }

        if ($this->isUnrestricted($actor)) {
            return;
        }

        $names = array_values(array_filter($roleNames, fn ($r) => is_string($r) && $r !== ''));
        if ($names === []) {
            return;
        }

        $protected = AiResourceCatalog::allProtectedNames();
        $actorPerms = $actor->getAllPermissions()->pluck('name')->all();
        $actorLookup = array_fill_keys($actorPerms, true);

        foreach ($names as $roleName) {
            if (in_array($roleName, $protected, true) && ! $actor->hasRole($roleName)) {
                throw new InvalidArgumentException(
                    "You cannot assign the protected role [{$roleName}].",
                );
            }

            $role = Role::query()->where('name', $roleName)->first();
            if ($role === null) {
                throw new InvalidArgumentException("Unknown role [{$roleName}].");
            }
            $rolePerms = $role->getAllPermissions()->pluck('name')->all();

            foreach ($rolePerms as $permission) {
                if (! isset($actorLookup[$permission])) {
                    throw new InvalidArgumentException(
                        "You cannot assign role [{$roleName}] because it includes permissions you do not have.",
                    );
                }
            }
        }
    }

    /**
     * @param  list<string>  $protectedNames
     */
    public function assertCanMutateProtectedNamedRow(
        User $actor,
        string $currentName,
        array $protectedNames,
        ?string $newName = null,
        bool $syncingPrivileges = false,
    ): void {
        if ($protectedNames === [] || ! in_array($currentName, $protectedNames, true)) {
            return;
        }

        if ($this->isUnrestricted($actor) && $actor->hasRole($currentName)) {
            if ($newName !== null && $newName !== $currentName) {
                throw new InvalidArgumentException('This role cannot be renamed.');
            }

            return;
        }

        if ($newName !== null && $newName !== $currentName) {
            throw new InvalidArgumentException('This role cannot be renamed.');
        }

        if ($syncingPrivileges && ! $this->isUnrestricted($actor)) {
            throw new InvalidArgumentException(
                "You cannot change privileges on the protected role [{$currentName}].",
            );
        }
    }

    /**
     * Apply privilege_fields checks from a catalog resource against validated payload.
     *
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $data
     */
    public function assertPayload(User $actor, array $resource, array $data, ?string $existingName = null): void
    {
        $fields = AiResourceCatalog::privilegeFields($resource);
        $protected = AiResourceCatalog::protectedNames($resource);

        if ($existingName !== null && $protected !== []) {
            $syncing = false;
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $syncing = true;
                    break;
                }
            }
            $this->assertCanMutateProtectedNamedRow(
                $actor,
                $existingName,
                $protected,
                isset($data['name']) ? (string) $data['name'] : null,
                $syncing,
            );
        }

        if (isset($data['name']) && is_string($data['name']) && $protected !== []) {
            // Creating a row with a protected name is only allowed for unrestricted holders of that name.
            if (in_array($data['name'], $protected, true) && ! $actor->hasRole($data['name'])) {
                throw new InvalidArgumentException(
                    "You cannot create or use the protected name [{$data['name']}].",
                );
            }
        }

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'permissions') {
                $this->assertPermissionsAssignable($actor, $data[$field]);
            } elseif ($field === 'roles') {
                $this->assertRolesAssignable($actor, $data[$field]);
            } else {
                // Generic: treat as permission name list.
                $this->assertPermissionsAssignable($actor, $data[$field]);
            }
        }
    }
}
