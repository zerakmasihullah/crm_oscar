<?php

namespace App\Services\Ai;

use App\Ai\Agents\SqlAuthorAgent;
use App\Models\User;
use App\Support\AiResourceCatalog;
use App\Support\AiSdkBridge;
use App\Support\AiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class AiAnalyticsService
{
    public function __construct(
        public SqlGuard $guard,
        public SqlDialectNormalizer $dialectNormalizer = new SqlDialectNormalizer,
        public AnalyticsAccess $access = new AnalyticsAccess,
    ) {}

    /**
     * @return array{sql: string, row_count: int, columns: list<string>, rows: list<array<string, mixed>>, markdown: string}
     */
    public function run(User $user, string $question, ?string $sql = null): array
    {
        if (! AiSettings::chatAllowAnalytics()) {
            throw new InvalidArgumentException('Analytics is disabled under Settings → AI.');
        }

        if (! $user->can('ai.analytics')) {
            throw new InvalidArgumentException('You do not have permission to run analytics.');
        }

        $this->assertReadonlyConnectionConfigured();

        $maxRows = (int) config('ai.analytics.max_rows', 200);
        $deniedTables = config('ai.analytics.deny_tables', []);
        $deniedTables = is_array($deniedTables) ? $deniedTables : [];
        $deniedTables = array_values(array_unique(array_merge(
            $deniedTables,
            AiResourceCatalog::disabledTables(),
        )));
        $allowedTables = $this->access->allowedTables($user);

        if ($allowedTables === []) {
            throw new InvalidArgumentException($this->access->publicAccessDeniedMessage());
        }

        $schema = $this->schemaDigest($deniedTables, $allowedTables);
        $scopeInstructions = $this->access->scopeInstructions($user);
        $driver = $this->driver();

        if ($sql === null || trim($sql) === '') {
            if (trim($question) === '') {
                throw new InvalidArgumentException('Provide a question or SQL.');
            }
            app(AiRiskGuard::class)->assertSafeUserText(
                $question,
                AiRiskGuard::SURFACE_ANALYTICS,
                (int) config('ai.limits.max_chat_message_chars', 4000),
            );
            $sql = $this->authorSql($question, $schema, $driver, null, $scopeInstructions);
        } else {
            // Explicit SQL still gets risk-scanned on the accompanying question when present.
            if (trim($question) !== '') {
                app(AiRiskGuard::class)->assertSafeUserText(
                    $question,
                    AiRiskGuard::SURFACE_ANALYTICS,
                    (int) config('ai.limits.max_chat_message_chars', 4000),
                );
            }
        }

        return $this->executeWithRepair(
            $user,
            $question,
            $sql,
            $schema,
            $driver,
            $maxRows,
            $deniedTables,
            $allowedTables,
            $scopeInstructions,
        );
    }

    /**
     * @param  list<string>  $deniedTables
     * @param  list<string>  $allowedTables
     * @return array{sql: string, row_count: int, columns: list<string>, rows: list<array<string, mixed>>, markdown: string}
     */
    protected function executeWithRepair(
        User $user,
        string $question,
        string $sql,
        string $schema,
        string $driver,
        int $maxRows,
        array $deniedTables,
        array $allowedTables,
        string $scopeInstructions = '',
        int $attempt = 0,
    ): array {
        $connection = $this->connectionName();
        $timeout = (int) config('ai.analytics.timeout_seconds', 15);

        try {
            if ($connection !== '' && Schema::connection($connection)->getConnection()->getDriverName() === 'mysql') {
                DB::connection($connection)->statement('SET SESSION MAX_EXECUTION_TIME='.($timeout * 1000));
            }
        } catch (Throwable) {
            // Driver may not support MAX_EXECUTION_TIME.
        }

        try {
            $sql = $this->dialectNormalizer->normalize($sql, $driver);
            $safe = $this->guard->sanitize($sql, $maxRows);
            $this->guard->assertNoDeniedTables($safe, $deniedTables);
            $deniedColumns = $this->deniedColumns();
            $this->guard->assertNoDeniedColumns($safe, $deniedColumns);
            $this->access->assertSqlUsesOnlyAllowedTables($safe, $allowedTables);
            $this->access->assertSqlRespectsScopes($safe, $user);
            $rows = DB::connection($connection)->select($safe);
        } catch (Throwable $e) {
            if ($attempt < 2 && $this->isRecoverableSqlError($e)) {
                $failedSql = isset($safe) ? $safe : $sql;
                $repaired = $this->authorSql(
                    $question,
                    $schema,
                    $driver,
                    "Failed SQL:\n{$failedSql}\nError: ".$this->publicSqlError($e)."\nUse ONLY schema tables/columns. Never invent columns or unauthorized tables.",
                    $scopeInstructions,
                );

                return $this->executeWithRepair(
                    $user,
                    $question,
                    $repaired,
                    $schema,
                    $driver,
                    $maxRows,
                    $deniedTables,
                    $allowedTables,
                    $scopeInstructions,
                    $attempt + 1,
                );
            }

            throw new InvalidArgumentException($this->publicSqlError($e), 0, $e);
        }

        $arrayRows = array_map(fn ($row) => (array) $row, $rows);
        $columns = $arrayRows === [] ? [] : array_keys($arrayRows[0]);

        if ($this->isUnavailableSentinel($columns, $arrayRows)) {
            $error = new RuntimeException(
                'That question cannot be answered with the data you can access.',
            );

            if ($attempt < 2) {
                $repaired = $this->authorSql(
                    $question,
                    $schema,
                    $driver,
                    "Previous SQL returned unavailable/missing-tables sentinel:\n{$safe}\nRewrite using only tables listed in the schema.",
                    $scopeInstructions,
                );

                return $this->executeWithRepair(
                    $user,
                    $question,
                    $repaired,
                    $schema,
                    $driver,
                    $maxRows,
                    $deniedTables,
                    $allowedTables,
                    $scopeInstructions,
                    $attempt + 1,
                );
            }

            throw $error;
        }

        $deniedColumns = $this->deniedColumns();
        if ($deniedColumns !== []) {
            $arrayRows = array_map(function (array $row) use ($deniedColumns): array {
                foreach ($deniedColumns as $col) {
                    unset($row[$col]);
                    // Also drop common alias variants that slipped through.
                    foreach (array_keys($row) as $key) {
                        if (strcasecmp((string) $key, $col) === 0) {
                            unset($row[$key]);
                        }
                    }
                }

                return $row;
            }, $arrayRows);
            $columns = $arrayRows === [] ? [] : array_keys($arrayRows[0]);
        }

        return [
            'sql' => $safe,
            'row_count' => count($arrayRows),
            'columns' => $columns,
            'rows' => $arrayRows,
            'markdown' => $this->toMarkdown($safe, $columns, $arrayRows),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    protected function isUnavailableSentinel(array $columns, array $rows): bool
    {
        if ($rows === [] || $columns === []) {
            return false;
        }

        $lower = array_map('strtolower', $columns);
        if (! in_array('error', $lower, true)) {
            return false;
        }

        foreach ($rows as $row) {
            foreach ($row as $key => $value) {
                if (strtolower((string) $key) === 'error' && strtolower(trim((string) $value)) === 'unavailable') {
                    return true;
                }
                if (is_string($value) && str_contains(strtolower($value), 'missing tables')) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isRecoverableSqlError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'unknown column')
            || str_contains($message, 'no such column')
            || str_contains($message, 'unknown table')
            || str_contains($message, 'no such table')
            || str_contains($message, 'doesn\'t exist')
            || str_contains($message, 'syntax error')
            || str_contains($message, 'not allowed for analytics')
            || str_contains($message, 'access to table')
            || str_contains($message, 'do not have access');
    }

    protected function publicSqlError(Throwable $e): string
    {
        $lower = strtolower($e->getMessage());

        if (
            str_contains($lower, 'do not have access')
            || str_contains($lower, 'not allowed for analytics')
            || str_contains($lower, 'access to table')
        ) {
            return $this->access->publicAccessDeniedMessage();
        }

        if (
            str_contains($lower, 'unknown column')
            || str_contains($lower, 'no such column')
            || str_contains($lower, 'unknown table')
            || str_contains($lower, 'no such table')
            || str_contains($lower, 'syntax error')
        ) {
            return 'The generated query was invalid for the data you can access.';
        }

        return 'Analytics query failed. Try a simpler question about data you can access.';
    }

    /**
     * @param  list<string>  $deniedTables
     * @param  list<string>|null  $allowedTables  When set, only these tables are included.
     */
    public function schemaDigest(array $deniedTables = [], ?array $allowedTables = null): string
    {
        $connection = $this->connectionName();
        $lines = [];

        try {
            $schema = Schema::connection($connection);
            $tables = $schema->getTableListing();
            $database = $this->connectionDatabase($connection);
        } catch (Throwable) {
            return '(schema unavailable)';
        }

        $deniedLookup = array_fill_keys(array_map('strtolower', $deniedTables), true);
        $allowedLookup = $allowedTables === null
            ? null
            : array_fill_keys(array_map('strtolower', $allowedTables), true);
        $deniedColumns = config('ai.analytics.deny_columns', []);
        $deniedColumns = is_array($deniedColumns) ? array_map('strtolower', $deniedColumns) : [];

        $priority = [
            'users',
            'authentication_logs',
            'field_reports',
            'ai_usage_logs',
            'roles',
            'model_has_roles',
            'permissions',
        ];

        // MySQL may return "database.table" across schemas — keep only this connection's DB
        // and always match / emit bare table names (SQL author must not qualify).
        $normalized = [];
        foreach ($tables as $table) {
            $bare = $this->bareTableName((string) $table, $database);
            if ($bare === null) {
                continue;
            }
            $normalized[$bare] = $bare;
        }
        $tables = array_values($normalized);

        usort($tables, function (string $a, string $b) use ($priority): int {
            $ai = array_search(strtolower($a), $priority, true);
            $bi = array_search(strtolower($b), $priority, true);
            $ai = $ai === false ? 1000 : $ai;
            $bi = $bi === false ? 1000 : $bi;

            return $ai <=> $bi ?: strcasecmp($a, $b);
        });

        foreach ($tables as $table) {
            $lower = strtolower($table);
            if (isset($deniedLookup[$lower])) {
                continue;
            }
            if ($allowedLookup !== null && ! isset($allowedLookup[$lower])) {
                continue;
            }

            try {
                $cols = Schema::connection($connection)->getColumnListing($table);
            } catch (Throwable) {
                continue;
            }

            $cols = array_values(array_filter(
                $cols,
                fn (string $c) => ! in_array(strtolower($c), $deniedColumns, true),
            ));

            $lines[] = $table.'('.implode(', ', $cols).')';
        }

        return $lines === [] ? '(no tables)' : implode("\n", $lines);
    }

    /**
     * Strip optional "database." / "schema." prefix. Returns null when the table belongs to another database.
     */
    public function bareTableName(string $table, ?string $database = null): ?string
    {
        $table = trim($table, " \t\n\r\0\x0B`\"");
        if ($table === '') {
            return null;
        }

        if (! str_contains($table, '.')) {
            return $table;
        }

        [$db, $bare] = explode('.', $table, 2);
        $db = trim($db, " \t\n\r\0\x0B`\"");
        $bare = trim($bare, " \t\n\r\0\x0B`\"");

        if ($bare === '') {
            return null;
        }

        if (! $this->isCurrentDatabasePrefix($db, $database)) {
            return null;
        }

        return $bare;
    }

    protected function isCurrentDatabasePrefix(string $prefix, ?string $database): bool
    {
        if ($database === null || $database === '') {
            return true;
        }

        if (strcasecmp($prefix, $database) === 0) {
            return true;
        }

        // SQLite lists tables as main.* while the configured database is ":memory:" or a file path.
        if (strcasecmp($prefix, 'main') === 0 && $this->driver() === 'sqlite') {
            return true;
        }

        return false;
    }

    protected function connectionDatabase(string $connection): ?string
    {
        try {
            $name = Schema::connection($connection)->getConnection()->getDatabaseName();

            return is_string($name) && $name !== '' ? $name : null;
        } catch (Throwable) {
            $configured = config('database.connections.'.$connection.'.database');

            return is_string($configured) && $configured !== '' ? $configured : null;
        }
    }

    protected function authorSql(
        string $question,
        string $schema,
        string $driver,
        ?string $repairHint = null,
        string $scopeInstructions = '',
    ): string {
        $resolved = AiSdkBridge::applyDefaultModel();
        $dialect = in_array($driver, ['mysql', 'mariadb'], true) ? 'mysql' : $driver;
        $agent = new SqlAuthorAgent($schema, $dialect, $repairHint, $scopeInstructions);

        $response = $agent->prompt(
            app(AiRiskGuard::class)->fenceUserContent('analytics_question', $question),
            provider: $resolved['provider'],
            model: $resolved['model'],
        );

        return trim((string) $response->text);
    }

    public function driver(): string
    {
        $connection = $this->connectionName();

        try {
            return Schema::connection($connection)->getConnection()->getDriverName();
        } catch (Throwable) {
            return (string) config('database.connections.'.$connection.'.driver', 'mysql');
        }
    }

    public function connectionName(): string
    {
        $this->assertReadonlyConnectionConfigured();

        $connection = config('ai.analytics.connection');

        return is_string($connection) && $connection !== ''
            ? $connection
            : (string) config('database.default');
    }

    protected function assertReadonlyConnectionConfigured(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        if (! (bool) config('ai.security.require_readonly_analytics_in_production', true)) {
            return;
        }

        $connection = config('ai.analytics.connection');
        if (! is_string($connection) || $connection === '') {
            throw new InvalidArgumentException(
                'Analytics requires DB_AI_READONLY_CONNECTION in production.',
            );
        }
    }

    /**
     * @return list<string>
     */
    protected function deniedColumns(): array
    {
        $denied = config('ai.analytics.deny_columns', []);
        $denied = is_array($denied) ? $denied : [];

        foreach (AiResourceCatalog::all() as $resource) {
            if (! is_array($resource)) {
                continue;
            }
            foreach (AiResourceCatalog::sensitiveAttributes($resource) as $col) {
                $denied[] = $col;
            }
        }

        return array_values(array_unique(array_filter(
            $denied,
            fn ($c) => is_string($c) && $c !== '',
        )));
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    protected function toMarkdown(string $sql, array $columns, array $rows): string
    {
        $lines = [
            'SQL:',
            '```sql',
            $sql,
            '```',
            'Rows: '.count($rows),
        ];

        if ($columns === []) {
            $lines[] = '(no rows)';

            return implode("\n", $lines);
        }

        $lines[] = '| '.implode(' | ', $columns).' |';
        $lines[] = '| '.implode(' | ', array_map(fn () => '---', $columns)).' |';

        foreach (array_slice($rows, 0, 50) as $row) {
            $cells = [];
            foreach ($columns as $col) {
                $value = $row[$col] ?? '';
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $cells[] = str_replace('|', '\\|', (string) $value);
            }
            $lines[] = '| '.implode(' | ', $cells).' |';
        }

        if (count($rows) > 50) {
            $lines[] = '… '.(count($rows) - 50).' more rows omitted';
        }

        return implode("\n", $lines);
    }
}
