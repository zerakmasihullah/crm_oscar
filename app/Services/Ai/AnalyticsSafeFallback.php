<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\AiResourceCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deterministic analytics queries derived from the AI resource catalog.
 * User-scoped resources always filter by the actor's user id column.
 */
class AnalyticsSafeFallback
{
    public function __construct(
        public AnalyticsAccess $access = new AnalyticsAccess,
        public SqlGuard $guard = new SqlGuard,
    ) {}

    public function isLoginPrompt(?string $prompt): bool
    {
        $prompt = strtolower(trim((string) $prompt));
        if ($prompt === '') {
            return false;
        }

        if (
            (str_contains($prompt, 'user') || str_contains($prompt, 'people') || str_contains($prompt, 'account'))
            && ! str_contains($prompt, 'login')
            && ! str_contains($prompt, 'logged')
            && ! str_contains($prompt, 'signin')
            && ! str_contains($prompt, 'sign-in')
            && ! str_contains($prompt, 'sign in')
            && ! str_contains($prompt, 'authentication')
        ) {
            return false;
        }

        return str_contains($prompt, 'login')
            || str_contains($prompt, 'logins')
            || str_contains($prompt, 'logged in')
            || str_contains($prompt, 'sign-in')
            || str_contains($prompt, 'signin')
            || str_contains($prompt, 'sign in')
            || str_contains($prompt, 'authentication log')
            || str_contains($prompt, 'auth log')
            || (str_contains($prompt, 'auth') && (str_contains($prompt, 'event') || str_contains($prompt, 'activity')));
    }

    /**
     * @param  list<string>|null  $allowedTables
     * @return list<array{
     *     title: string,
     *     viz: string,
     *     sql: string,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     row_count: int
     * }>
     */
    public function loginOnly(?array $allowedTables = null): array
    {
        if (! $this->tableAllowed('authentication_logs', $allowedTables)) {
            return [];
        }

        $connection = $this->connectionName();
        if (! $this->hasTable($connection, 'authentication_logs')) {
            return [];
        }

        return array_values(array_filter($this->authenticationLogSections($connection)));
    }

    /**
     * @param  list<string>|null  $allowedTables
     * @return list<array{
     *     title: string,
     *     viz: string,
     *     sql: string,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     row_count: int
     * }>
     */
    public function build(?string $prompt = null, ?array $allowedTables = null, ?User $user = null): array
    {
        if ($this->isLoginPrompt($prompt)) {
            $login = $this->loginOnly($allowedTables);
            if ($login !== []) {
                return $login;
            }
        }

        $connection = $this->connectionName();
        $resources = $this->matchingCatalogResources($prompt, $allowedTables, $user);

        $sections = [];
        foreach ($resources as $resource) {
            foreach ($this->sectionsForResource($connection, $resource, $user) as $section) {
                $sections[] = $section;
            }
        }

        return array_values(array_filter(
            $sections,
            fn ($item) => is_array($item) && $this->sqlAllowed((string) ($item['sql'] ?? ''), $allowedTables),
        ));
    }

    /**
     * @param  list<string>|null  $allowedTables
     * @return list<array<string, mixed>>
     */
    protected function matchingCatalogResources(?string $prompt, ?array $allowedTables, ?User $user): array
    {
        $prompt = strtolower(trim((string) $prompt));
        $matched = [];
        $allViewable = [];

        foreach (AiResourceCatalog::enabled() as $key => $resource) {
            if (! is_array($resource)) {
                continue;
            }
            $resource['key'] = $key;

            if ($user instanceof User) {
                $permission = AiResourceCatalog::permissionFor($resource, 'view');
                if ($permission === null || $permission === '' || ! $user->can($permission)) {
                    continue;
                }
            }

            $table = AiResourceCatalog::tableName($resource);
            if ($table === null || ! $this->tableAllowed($table, $allowedTables)) {
                continue;
            }

            $allViewable[] = $resource;

            $label = strtolower((string) ($resource['label'] ?? $key));
            $haystack = $key.' '.$label;
            if ($prompt !== '' && (str_contains($prompt, $key) || str_contains($prompt, $label)
                || ($label !== '' && str_contains($prompt, strtok($label, ' '))))) {
                $matched[] = $resource;
            }
        }

        if ($matched !== []) {
            return $matched;
        }

        // Generic overview: first few viewable catalog resources.
        return array_slice($allViewable, 0, 3);
    }

    /**
     * @param  array<string, mixed>  $resource
     * @return list<array{title: string, viz: string, sql: string, columns: list<string>, rows: list<array<string, mixed>>, row_count: int}|null>
     */
    protected function sectionsForResource(string $connection, array $resource, ?User $user): array
    {
        $table = AiResourceCatalog::tableName($resource);
        if ($table === null || ! $this->hasTable($connection, $table)) {
            return [];
        }

        $label = (string) ($resource['label'] ?? $table);
        $scope = $this->scopeSqlFragment($resource, $user);
        $where = $scope !== '' ? ' WHERE '.$scope : '';
        $and = $scope !== '' ? ' AND '.$scope : '';

        $cols = $this->columns($connection, $table);
        $sections = [];

        $sections[] = $this->runSafe(
            $connection,
            $user,
            "Total {$label}",
            'kpi',
            "SELECT COUNT(*) AS total FROM {$table}{$where} LIMIT 1",
        );

        if (in_array('status', $cols, true)) {
            $sections[] = $this->runSafe(
                $connection,
                $user,
                "{$label} by status",
                'bar',
                "SELECT status AS label, COUNT(*) AS total FROM {$table}{$where} GROUP BY status ORDER BY total DESC LIMIT 100",
            );
        }

        if (in_array('is_active', $cols, true)) {
            $sections[] = $this->runSafe(
                $connection,
                $user,
                "{$label} active vs inactive",
                'pie',
                "SELECT CASE WHEN is_active = 1 THEN 'Active' ELSE 'Inactive' END AS label, COUNT(*) AS total FROM {$table}{$where} GROUP BY is_active LIMIT 100",
            );
        }

        if (in_array('created_at', $cols, true)) {
            $sections[] = $this->runSafe(
                $connection,
                $user,
                "{$label} by month",
                'line',
                $this->monthlyBucketSql($connection, $table, 'created_at', $and),
            );
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function scopeSqlFragment(array $resource, ?User $user): string
    {
        if (! $user instanceof User || ! AiResourceCatalog::isUserScoped($resource)) {
            return '';
        }

        $column = AiResourceCatalog::userIdColumn($resource);

        return $column.' = '.(int) $user->id;
    }

    /**
     * @param  list<string>|null  $allowedTables
     */
    protected function tableAllowed(string $table, ?array $allowedTables): bool
    {
        if ($allowedTables === null) {
            return true;
        }

        return in_array(strtolower($table), array_map('strtolower', $allowedTables), true);
    }

    /**
     * @param  list<string>|null  $allowedTables
     */
    protected function sqlAllowed(string $sql, ?array $allowedTables): bool
    {
        if ($allowedTables === null || $sql === '') {
            return true;
        }

        if (preg_match_all('/\b(?:FROM|JOIN)\s+[`"]?([a-zA-Z0-9_]+)[`"]?/i', $sql, $matches) === false) {
            return true;
        }

        $allowedLookup = array_fill_keys(array_map('strtolower', $allowedTables), true);
        foreach ($matches[1] as $table) {
            if (! isset($allowedLookup[strtolower($table)])) {
                return false;
            }
        }

        return true;
    }

    protected function connectionName(): string
    {
        $connection = config('ai.analytics.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : (string) config('database.default');
    }

    /**
     * @return list<array{title: string, viz: string, sql: string, columns: list<string>, rows: list<array<string, mixed>>, row_count: int}|null>
     */
    protected function authenticationLogSections(string $connection): array
    {
        $cols = $this->columns($connection, 'authentication_logs');
        $hasEvent = in_array('event', $cols, true);
        $sections = [];

        if ($hasEvent) {
            $sections[] = $this->run(
                $connection,
                'Successful logins',
                'kpi',
                "SELECT COUNT(*) AS total FROM authentication_logs WHERE event = 'login_success' LIMIT 1",
            );
            $sections[] = $this->run(
                $connection,
                'Failed logins',
                'kpi',
                "SELECT COUNT(*) AS total FROM authentication_logs WHERE event = 'login_failed' LIMIT 1",
            );
        } else {
            $sections[] = $this->run(
                $connection,
                'Total auth events',
                'kpi',
                'SELECT COUNT(*) AS total FROM authentication_logs LIMIT 1',
            );
        }

        return $sections;
    }

    /**
     * Run SQL through SqlGuard + optional scope assert when a user is present.
     *
     * @return array{
     *     title: string,
     *     viz: string,
     *     sql: string,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     row_count: int
     * }|null
     */
    protected function runSafe(string $connection, ?User $user, string $title, string $viz, string $sql): ?array
    {
        try {
            $safe = $this->guard->sanitize($sql, (int) config('ai.analytics.max_rows', 100));
            $deniedTables = config('ai.analytics.deny_tables', []);
            $deniedTables = is_array($deniedTables) ? $deniedTables : [];
            $this->guard->assertNoDeniedTables($safe, $deniedTables);

            if ($user instanceof User) {
                $this->access->assertSqlRespectsScopes($safe, $user);
            }

            return $this->run($connection, $title, $viz, $safe);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *     title: string,
     *     viz: string,
     *     sql: string,
     *     columns: list<string>,
     *     rows: list<array<string, mixed>>,
     *     row_count: int
     * }|null
     */
    protected function run(string $connection, string $title, string $viz, string $sql): ?array
    {
        try {
            $rows = array_map(fn ($row) => (array) $row, DB::connection($connection)->select($sql));
            $columns = $rows === [] ? [] : array_keys($rows[0]);

            return [
                'title' => $title,
                'viz' => $viz,
                'sql' => $sql,
                'columns' => $columns,
                'rows' => $rows,
                'row_count' => count($rows),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    protected function hasTable(string $connection, string $table): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    protected function columns(string $connection, string $table): array
    {
        try {
            return Schema::connection($connection)->getColumnListing($table);
        } catch (Throwable) {
            return [];
        }
    }

    protected function monthlyBucketSql(string $connection, string $table, string $column, string $andScope = ''): string
    {
        $driver = $this->driver($connection);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "SELECT DATE_FORMAT({$column}, '%Y-%m') AS label, COUNT(*) AS total FROM {$table} WHERE 1=1{$andScope} GROUP BY DATE_FORMAT({$column}, '%Y-%m') ORDER BY label DESC LIMIT 12";
        }

        return "SELECT strftime('%Y-%m', {$column}) AS label, COUNT(*) AS total FROM {$table} WHERE 1=1{$andScope} GROUP BY label ORDER BY label DESC LIMIT 12";
    }

    protected function driver(string $connection): string
    {
        try {
            return Schema::connection($connection)->getConnection()->getDriverName();
        } catch (Throwable) {
            return 'mysql';
        }
    }
}
