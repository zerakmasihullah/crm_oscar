<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiResourceCatalog;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Restricts analytics schema + SQL to tables the current user may view.
 * Never advertise unauthorized tables to the model or the end user.
 */
class AnalyticsAccess
{
    /**
     * @return list<string>
     */
    public function allowedTables(User $user): array
    {
        $allowed = [];

        foreach (AiResourceCatalog::enabled() as $key => $resource) {
            if (! is_array($resource)) {
                continue;
            }

            $resource['key'] = $key;

            if (! $this->canView($user, $resource)) {
                continue;
            }

            $table = AiResourceCatalog::tableName($resource);
            if ($table !== null) {
                $allowed[] = $table;
            }

            foreach (AiResourceCatalog::relatedTables($resource) as $related) {
                $allowed[] = $related;
            }
        }

        foreach ($this->extraTablePermissions() as $table => $permissions) {
            if ($this->userHasAny($user, $permissions)) {
                $allowed[] = $table;
            }
        }

        $disabled = AiResourceCatalog::disabledTables();
        $allowed = array_values(array_filter(
            array_unique(array_map('strtolower', $allowed)),
            fn (string $table) => ! in_array($table, $disabled, true),
        ));
        sort($allowed);

        return $allowed;
    }

    /**
     * @return list<array{table: string, user_id_column: string}>
     */
    public function scopeRules(User $user): array
    {
        return AiResourceCatalog::scopeRulesForUser($user);
    }

    public function canAccessTable(User $user, string $table): bool
    {
        return in_array(strtolower($table), $this->allowedTables($user), true);
    }

    /**
     * @param  list<string>  $allowedTables
     */
    public function assertSqlUsesOnlyAllowedTables(string $sql, array $allowedTables): void
    {
        $allowedLookup = array_fill_keys(array_map('strtolower', $allowedTables), true);
        $referenced = $this->extractTableNames($sql);

        foreach ($referenced as $table) {
            if (! isset($allowedLookup[strtolower($table)])) {
                throw new InvalidArgumentException(
                    'You do not have access to analyze that data.',
                );
            }
        }
    }

    /**
     * Enforce row scopes without trusting a simple substring.
     * Strips string literals, requires equality to the actor id, rejects OR-loosened filters.
     */
    public function assertSqlRespectsScopes(string $sql, User $user): void
    {
        $rules = $this->scopeRules($user);
        if ($rules === []) {
            return;
        }

        $stripped = $this->stripStringLiterals($sql);
        $uid = (string) $user->id;

        foreach ($rules as $rule) {
            $table = $rule['table'];
            $column = $rule['user_id_column'];
            if (! preg_match('/\b'.preg_quote($table, '/').'\b/i', $stripped)) {
                continue;
            }

            $hasScope = preg_match(
                '/\b'.preg_quote($column, '/').'\b\s*=\s*'.preg_quote($uid, '/').'\b/i',
                $stripped,
            ) === 1;

            if (! $hasScope) {
                throw new InvalidArgumentException(
                    'You do not have access to analyze that data.',
                );
            }

            // OR can loosen a correct equality into a cross-user leak.
            if (preg_match('/\bOR\b/i', $stripped) === 1) {
                throw new InvalidArgumentException(
                    'You do not have access to analyze that data.',
                );
            }
        }
    }

    public function scopeInstructions(User $user): string
    {
        $rules = $this->scopeRules($user);
        if ($rules === []) {
            return '';
        }

        $lines = ['Row-scope rules (mandatory):'];
        foreach ($rules as $rule) {
            $lines[] = "- When querying {$rule['table']}, always filter {$rule['user_id_column']} = {$user->id} (this user's own rows only). Never use OR with that filter.";
        }

        return implode("\n", $lines);
    }

    public function publicAccessDeniedMessage(): string
    {
        return 'You do not have access to analyze that data.';
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function canView(User $user, array $resource): bool
    {
        $permission = AiResourceCatalog::permissionFor($resource, 'view');
        if ($permission === null || $permission === '') {
            return false;
        }

        return $user->can($permission);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function extraTablePermissions(): array
    {
        $configured = config('ai.analytics.table_permissions', []);
        if (! is_array($configured)) {
            return [];
        }

        $out = [];
        foreach ($configured as $table => $permissions) {
            if (! is_string($table) || $table === '') {
                continue;
            }
            $perms = is_array($permissions)
                ? array_values(array_filter($permissions, fn ($p) => is_string($p) && $p !== ''))
                : [];
            $out[$table] = $perms;
        }

        return $out;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function userHasAny(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function extractTableNames(string $sql): array
    {
        $tables = [];
        $pattern = '/\b(?:FROM|JOIN|UPDATE|INTO)\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i';
        if (preg_match_all($pattern, $sql, $matches) !== false) {
            foreach ($matches[1] as $name) {
                $tables[] = $name;
            }
        }

        return array_values(array_unique($tables));
    }

    protected function stripStringLiterals(string $sql): string
    {
        $withoutSingle = preg_replace("/'(?:''|[^'])*'/", "''", $sql) ?? $sql;
        $withoutDouble = preg_replace('/"(?:\\\\.|[^"\\\\])*"/', '""', $withoutSingle) ?? $withoutSingle;

        return $withoutDouble;
    }
}
