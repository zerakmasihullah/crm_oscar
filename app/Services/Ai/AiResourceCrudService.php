<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiChatLinks;
use App\Support\AiResourceCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use App\Models\Role;
use Throwable;

class AiResourceCrudService
{
    /**
     * @return list<array{key: string, label: string, can_view: bool, can_create: bool, can_edit: bool, can_delete: bool}>
     */
    public function listResources(User $user): array
    {
        $out = [];

        foreach (AiResourceCatalog::enabled() as $key => $resource) {
            if (! is_array($resource)) {
                continue;
            }
            $resource['key'] = $key;
            $out[] = [
                'key' => $key,
                'label' => (string) ($resource['label'] ?? $key),
                'can_view' => $this->allows($user, $resource, 'view'),
                'can_create' => $this->allows($user, $resource, 'create'),
                'can_edit' => $this->allows($user, $resource, 'edit'),
                'can_delete' => $this->allows($user, $resource, 'delete'),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listRecords(User $user, string $resourceKey, array $filters = []): string
    {
        $resource = AiResourceCatalog::get($resourceKey);
        $this->assertAllows($user, $resource, 'view');

        $limit = max(1, min(20, (int) ($filters['limit'] ?? 10)));
        $query = $this->baseQuery($user, $resource);

        if (filled($filters['query'] ?? null)) {
            $this->applySearch($query, $resource, (string) $filters['query']);
        }

        if (isset($filters['filter']) && is_array($filters['filter'])) {
            $this->applyAttributeFilters($query, $resource, $filters['filter']);
        }

        $this->applyDefaultOrder($query, $resource);

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            return "No {$resource['label']} records matched.";
        }

        $lines = ['Found '.$rows->count().' '.$resource['label'].' record(s):'];
        foreach ($rows as $row) {
            $lines[] = '- '.$this->formatLine($resource, $row);
        }

        return implode("\n", $lines);
    }

    public function getRecord(User $user, string $resourceKey, int $id): string
    {
        $resource = AiResourceCatalog::get($resourceKey);
        $this->assertAllows($user, $resource, 'view');

        $record = $this->findOwned($user, $resource, $id);
        if ($record === null) {
            return "Error: {$resource['label']} #{$id} was not found.";
        }

        $payload = $this->visibleAttributes($resource, $record);
        $extra = $this->extraDetails($resource, $record);

        $lines = [
            "{$resource['label']} details:",
            $this->formatLine($resource, $record),
            'Data: '.json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        if ($extra !== []) {
            $lines[] = 'Extra: '.json_encode($extra, JSON_UNESCAPED_UNICODE);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createRecord(User $user, string $resourceKey, array $input): string
    {
        $resource = AiResourceCatalog::get($resourceKey);
        $this->assertAllows($user, $resource, 'create');

        $rules = $this->rulesFor($resource, 'create', null);
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return 'Error: '.$validator->errors()->first();
        }

        $data = $validator->validated();

        try {
            app(PrivilegeGrantGuard::class)->assertPayload($user, $resource, $data);
            $record = match ($resource['handler'] ?? 'eloquent') {
                'users' => $this->createUser($data),
                'roles' => $this->createRole($data),
                default => $this->createEloquent($user, $resource, $data),
            };
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }

        return "Created {$resource['label']}. ".$this->formatLine($resource, $record);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateRecord(User $user, string $resourceKey, int $id, array $input): string
    {
        $resource = AiResourceCatalog::get($resourceKey);
        $this->assertAllows($user, $resource, 'edit');

        $record = $this->findOwned($user, $resource, $id);
        if ($record === null) {
            return "Error: {$resource['label']} #{$id} was not found.";
        }

        unset($input['id'], $input['resource']);

        $rules = $this->rulesFor($resource, 'update', $record);
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return 'Error: '.$validator->errors()->first();
        }

        $data = $validator->validated();
        if ($data === []) {
            return 'Error: Provide at least one field to update.';
        }

        $existingName = isset($record->name) ? (string) $record->name : null;

        try {
            app(PrivilegeGrantGuard::class)->assertPayload($user, $resource, $data, $existingName);
            $record = match ($resource['handler'] ?? 'eloquent') {
                'users' => $this->updateUser($user, $record, $data),
                'roles' => $this->updateRole($user, $record, $data),
                default => $this->updateEloquent($resource, $record, $data),
            };
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }

        return "Updated {$resource['label']}. ".$this->formatLine($resource, $record);
    }

    public function deleteRecord(User $user, string $resourceKey, int $id): string
    {
        $resource = AiResourceCatalog::get($resourceKey);
        $this->assertAllows($user, $resource, 'delete');

        $record = $this->findOwned($user, $resource, $id);
        if ($record === null) {
            return "Error: {$resource['label']} #{$id} was not found.";
        }

        $label = $this->titleOf($resource, $record);
        $protected = AiResourceCatalog::protectedNames($resource);
        if ($protected !== [] && isset($record->name) && in_array((string) $record->name, $protected, true)) {
            return 'Error: This record is protected and cannot be deleted.';
        }

        try {
            match ($resource['handler'] ?? 'eloquent') {
                'users' => $this->deleteUser($user, $record),
                'roles' => $this->deleteRole($record),
                default => $record->delete(),
            };
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }

        return "Deleted {$resource['label']} #{$id}".($label !== '' ? " ({$label})" : '').'.';
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function allows(User $user, array $resource, string $verb): bool
    {
        $key = is_string($resource['key'] ?? null) ? (string) $resource['key'] : null;
        if ($key !== null && ! AiResourceCatalog::actionEnabled($key, $verb)) {
            return false;
        }

        $permission = AiResourceCatalog::permissionFor($resource, $verb);

        if ($permission === null) {
            return false;
        }

        return $user->can($permission);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function assertAllows(User $user, array $resource, string $verb): void
    {
        $key = is_string($resource['key'] ?? null) ? (string) $resource['key'] : null;
        $label = (string) ($resource['label'] ?? $key ?? 'resource');

        if ($key !== null && ! AiResourceCatalog::isEnabled($key)) {
            throw new InvalidArgumentException("AI access to {$label} is disabled.");
        }

        if ($key !== null && ! AiResourceCatalog::actionEnabled($key, $verb)) {
            throw new InvalidArgumentException("AI is not allowed to {$verb} {$label}.");
        }

        if (! $this->allows($user, $resource, $verb)) {
            throw new InvalidArgumentException("You do not have permission to {$verb} {$label}.");
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function baseQuery(User $user, array $resource): Builder
    {
        $modelClass = AiResourceCatalog::modelClass($resource);
        $query = $modelClass::query();

        if (($resource['user_scoped'] ?? false) === true) {
            $column = AiResourceCatalog::userIdColumn($resource);
            $query->where($column, $user->id);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function findOwned(User $user, array $resource, int $id): ?Model
    {
        return $this->baseQuery($user, $resource)->whereKey($id)->first();
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function applySearch(Builder $query, array $resource, string $term): void
    {
        $columns = $resource['search'] ?? [];
        if (! is_array($columns) || $columns === []) {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $query->where(function (Builder $q) use ($columns, $like): void {
            foreach ($columns as $index => $column) {
                if (! is_string($column) || $column === '') {
                    continue;
                }
                if ($index === 0) {
                    $q->where($column, 'like', $like);
                } else {
                    $q->orWhere($column, 'like', $like);
                }
            }
        });
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $filters
     */
    protected function applyAttributeFilters(Builder $query, array $resource, array $filters): void
    {
        $allowed = array_merge(
            AiResourceCatalog::displayAttributes($resource),
            is_array($resource['fillable'] ?? null) ? $resource['fillable'] : [],
            is_array($resource['search'] ?? null) ? $resource['search'] : [],
        );
        $allowed = array_unique($allowed);

        foreach ($filters as $key => $value) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $query->where($key, $value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function applyDefaultOrder(Builder $query, array $resource): void
    {
        $order = $resource['default_order'] ?? null;
        if (! is_array($order) || count($order) < 1) {
            $query->latest('id');

            return;
        }

        $column = (string) $order[0];
        $direction = strtolower((string) ($order[1] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($column, $direction);
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    protected function rulesFor(array $resource, string $action, ?Model $record): array
    {
        $rules = $resource['rules'][$action] ?? [];
        if (! is_array($rules)) {
            return [];
        }

        if ($action === 'update' && ($resource['handler'] ?? '') === 'users' && $record instanceof User) {
            $rules['email'] = ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($record->id)];
        }

        if ($action === 'update' && ($resource['handler'] ?? '') === 'roles' && $record instanceof Role) {
            $rules['name'] = ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($record->id)];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $data
     */
    protected function createEloquent(User $user, array $resource, array $data): Model
    {
        $modelClass = AiResourceCatalog::modelClass($resource);
        $fillable = is_array($resource['fillable'] ?? null) ? $resource['fillable'] : [];
        $payload = array_intersect_key($data, array_flip($fillable));

        if (($resource['user_scoped'] ?? false) === true) {
            $column = (string) ($resource['user_id_column'] ?? 'user_id');
            $payload[$column] = $user->id;
        }

        if (in_array('client_uuid', $resource['create_extras'] ?? [], true)) {
            $payload['client_uuid'] = (string) Str::uuid();
        }

        if (in_array('client_updated_at', $resource['create_extras'] ?? [], true)) {
            $payload['client_updated_at'] = now();
        }

        if (array_key_exists('status', $fillable) && ! isset($payload['status'])) {
            $payload['status'] = 'draft';
        }

        return $modelClass::query()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $resource
     * @param  array<string, mixed>  $data
     */
    protected function updateEloquent(array $resource, Model $record, array $data): Model
    {
        $fillable = is_array($resource['fillable'] ?? null) ? $resource['fillable'] : [];
        $payload = array_intersect_key($data, array_flip($fillable));

        if (in_array('client_updated_at', $record->getFillable(), true)
            || array_key_exists('client_updated_at', $record->getAttributes())) {
            $payload['client_updated_at'] = now();
        }

        $record->update($payload);

        return $record->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createUser(array $data): User
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if (is_array($roles) && $roles !== []) {
            $user->syncRoles($roles);
        }

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function updateUser(User $actor, Model $record, array $data): User
    {
        /** @var User $user */
        $user = $record;

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
            unset($data['roles']);
        }

        if (array_key_exists('is_active', $data)) {
            if ($user->id === $actor->id && ! $data['is_active']) {
                throw new InvalidArgumentException('You cannot disable your own account.');
            }
            if ($data['is_active']) {
                $user->enable($actor);
            } else {
                $user->disable($actor);
            }
            unset($data['is_active']);
        }

        if ($data !== []) {
            $user->update($data);
        }

        return $user->refresh();
    }

    protected function deleteUser(User $actor, Model $record): void
    {
        /** @var User $user */
        $user = $record;

        if ($user->id === $actor->id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }

        $user->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createRole(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        $role = Role::findOrCreate($data['name']);
        if (is_array($permissions)) {
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function updateRole(User $actor, Model $record, array $data): Role
    {
        unset($actor);
        /** @var Role $role */
        $role = $record;

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        return $role->refresh();
    }

    protected function deleteRole(Model $record): void
    {
        /** @var Role $role */
        $role = $record;

        if ($role->users()->exists()) {
            throw new InvalidArgumentException('Cannot delete a role that is assigned to users.');
        }

        $role->delete();
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    protected function visibleAttributes(array $resource, Model $record): array
    {
        $sensitive = AiResourceCatalog::sensitiveAttributes($resource);
        $attrs = $record->attributesToArray();

        foreach ($sensitive as $key) {
            unset($attrs[$key]);
        }

        $display = AiResourceCatalog::displayAttributes($resource);
        if ($display !== []) {
            $attrs = array_intersect_key($attrs, array_flip($display));
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    protected function extraDetails(array $resource, Model $record): array
    {
        return match ($resource['handler'] ?? 'eloquent') {
            'users' => [
                'roles' => method_exists($record, 'getRoleNames')
                    ? $record->getRoleNames()->values()->all()
                    : [],
            ],
            'roles' => [
                'permissions' => method_exists($record, 'getPermissionNames')
                    ? $record->getPermissionNames()->values()->all()
                    : [],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function formatLine(array $resource, Model $record): string
    {
        $parts = [];
        foreach (AiResourceCatalog::displayAttributes($resource) as $attr) {
            if (! array_key_exists($attr, $record->getAttributes()) && $attr !== 'id') {
                continue;
            }
            $value = $record->getAttribute($attr);
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            if ($value === null) {
                continue;
            }
            $parts[] = $attr === 'id' ? '#'.$value : $attr.': '.$value;
        }

        $summary = implode(' · ', $parts);
        $link = $this->openLink($resource, $record);

        return $link !== null ? $summary.' — '.$link : $summary;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function openLink(array $resource, Model $record): ?string
    {
        $title = $this->titleOf($resource, $record);
        $label = $title !== '' ? 'Open "'.$title.'"' : 'Open';

        $editRoute = $resource['edit_route'] ?? null;
        if (is_string($editRoute) && $editRoute !== '') {
            try {
                return AiChatLinks::markdown($label, route($editRoute, $record, absolute: false));
            } catch (Throwable) {
                // fall through
            }
        }

        $indexRoute = $resource['index_route'] ?? null;
        if (is_string($indexRoute) && $indexRoute !== '') {
            try {
                return AiChatLinks::markdown($label, route($indexRoute, absolute: false));
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function titleOf(array $resource, Model $record): string
    {
        $attr = (string) ($resource['title_attribute'] ?? 'name');
        $value = $record->getAttribute($attr);

        return is_scalar($value) ? (string) $value : '';
    }
}
