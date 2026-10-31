<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AiResourceCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $resources = config('ai_resources', []);

        return is_array($resources) ? $resources : [];
    }

    /**
     * Catalog entries AI is allowed to use (org Data access switches).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function enabled(): array
    {
        $out = [];

        foreach (self::all() as $key => $resource) {
            if (! is_string($key) || ! is_array($resource) || ! self::isEnabled($key)) {
                continue;
            }
            $out[$key] = $resource;
        }

        return $out;
    }

    public static function isEnabled(string $key): bool
    {
        return AiSettings::resourceEnabled($key);
    }

    public static function actionEnabled(string $key, string $action): bool
    {
        return AiSettings::resourceActionEnabled($key, $action);
    }

    /**
     * Primary tables for resources turned off in Settings → AI → Data access.
     *
     * @return list<string>
     */
    public static function disabledTables(): array
    {
        $tables = [];

        foreach (self::all() as $key => $resource) {
            if (! is_string($key) || ! is_array($resource) || self::isEnabled($key)) {
                continue;
            }

            $table = self::tableName($resource);
            if ($table !== null && $table !== '') {
                $tables[] = strtolower($table);
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $resources = self::all();

        if (! isset($resources[$key]) || ! is_array($resources[$key])) {
            throw new InvalidArgumentException("Unknown AI resource [{$key}].");
        }

        if (! self::isEnabled($key)) {
            throw new InvalidArgumentException("AI access to [{$key}] is disabled.");
        }

        $resource = $resources[$key];
        $resource['key'] = $key;

        return $resource;
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return class-string<Model>
     */
    public static function modelClass(array $resource): string
    {
        $model = $resource['model'] ?? null;

        if (! is_string($model) || ! is_subclass_of($model, Model::class)) {
            throw new InvalidArgumentException('AI resource is missing a valid Eloquent model.');
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    public static function permissionFor(array $resource, string $verb): ?string
    {
        $map = $resource['permissions'] ?? null;
        if (is_array($map) && isset($map[$verb]) && is_string($map[$verb])) {
            return $map[$verb];
        }

        $prefix = $resource['permission'] ?? null;
        if (! is_string($prefix) || $prefix === '') {
            return null;
        }

        return $prefix.'.'.$verb;
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<string>
     */
    public static function sensitiveAttributes(array $resource): array
    {
        $sensitive = $resource['sensitive'] ?? [];

        return is_array($sensitive)
            ? array_values(array_filter($sensitive, fn ($v) => is_string($v)))
            : [];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<string>
     */
    public static function displayAttributes(array $resource): array
    {
        $display = $resource['display'] ?? ['id'];

        return is_array($display)
            ? array_values(array_filter($display, fn ($v) => is_string($v)))
            : ['id'];
    }

    /**
     * Whether a mutating verb requires human Approve/Reject before the tool runs.
     * Default when unset: delete only.
     *
     * @param  array<string, mixed>  $resource
     */
    public static function requiresApproval(array $resource, string $verb): bool
    {
        $verb = $verb === 'edit' ? 'update' : $verb;

        $configured = $resource['require_approval'] ?? ['delete'];

        if ($configured === true) {
            return in_array($verb, ['create', 'update', 'delete'], true);
        }

        if ($configured === false) {
            return false;
        }

        if (! is_array($configured)) {
            return $verb === 'delete';
        }

        $verbs = array_values(array_filter($configured, fn ($v) => is_string($v) && $v !== ''));
        $normalized = array_map(
            fn (string $v) => $v === 'edit' ? 'update' : $v,
            $verbs,
        );

        return in_array($verb, $normalized, true);
    }

    /**
     * Fields that grant authority and must pass PrivilegeGrantGuard.
     *
     * @param  array<string, mixed>  $resource
     * @return list<string>
     */
    public static function privilegeFields(array $resource): array
    {
        $fields = $resource['privilege_fields'] ?? [];

        return is_array($fields)
            ? array_values(array_filter($fields, fn ($v) => is_string($v) && $v !== ''))
            : [];
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<string>
     */
    public static function protectedNames(array $resource): array
    {
        $names = $resource['protected_names'] ?? [];

        return is_array($names)
            ? array_values(array_filter($names, fn ($v) => is_string($v) && $v !== ''))
            : [];
    }

    /**
     * Union of protected_names across the entire catalog.
     *
     * @return list<string>
     */
    public static function allProtectedNames(): array
    {
        $names = [];
        foreach (self::all() as $resource) {
            if (! is_array($resource)) {
                continue;
            }
            foreach (self::protectedNames($resource) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    public static function isUserScoped(array $resource): bool
    {
        return ($resource['user_scoped'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    public static function userIdColumn(array $resource): string
    {
        $column = $resource['user_id_column'] ?? 'user_id';

        return is_string($column) && $column !== '' ? $column : 'user_id';
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<string>
     */
    public static function relatedTables(array $resource): array
    {
        $tables = $resource['related_tables'] ?? [];

        return is_array($tables)
            ? array_values(array_filter($tables, fn ($v) => is_string($v) && $v !== ''))
            : [];
    }

    /**
     * Resolve analytics table name (optional catalog override, else model table).
     *
     * @param  array<string, mixed>  $resource
     */
    public static function tableName(array $resource): ?string
    {
        $override = $resource['table'] ?? null;
        if (is_string($override) && $override !== '') {
            return $override;
        }

        try {
            $modelClass = self::modelClass($resource);
            /** @var Model $model */
            $model = new $modelClass;

            return $model->getTable();
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Row-scope rules for analytics for tables the user may view.
     *
     * @return list<array{table: string, user_id_column: string}>
     */
    public static function scopeRulesForUser(User $user): array
    {
        $rules = [];

        foreach (self::enabled() as $key => $resource) {
            if (! is_array($resource) || ! self::isUserScoped($resource)) {
                continue;
            }

            $resource['key'] = $key;
            $permission = self::permissionFor($resource, 'view');
            if ($permission === null || $permission === '' || ! $user->can($permission)) {
                continue;
            }

            $table = self::tableName($resource);
            if ($table === null) {
                continue;
            }

            $rules[] = [
                'table' => $table,
                'user_id_column' => self::userIdColumn($resource),
            ];
        }

        return $rules;
    }
}
