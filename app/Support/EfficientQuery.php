<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Query helpers tuned for high-volume MIS list pages.
 */
final class EfficientQuery
{
    /**
     * Apply searchable filters without leading-wildcard scans when possible.
     *
     * - Exact match for emails / IPs / codes when listed in $exactColumns
     * - Prefix `term%` for indexed string columns (uses B-tree)
     * - Contains `%term%` only for explicitly opted-in soft columns
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $prefixColumns
     * @param  list<string>  $containsColumns
     * @param  list<string>  $exactColumns
     */
    public static function applySearch(
        Builder $query,
        string $term,
        array $prefixColumns = [],
        array $containsColumns = [],
        array $exactColumns = [],
    ): Builder {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $escaped = addcslashes($term, '%_\\');

        return $query->where(function (Builder $builder) use ($escaped, $term, $prefixColumns, $containsColumns, $exactColumns): void {
            $first = true;

            foreach ($exactColumns as $column) {
                $method = $first ? 'where' : 'orWhere';
                $builder->{$method}($column, $term);
                $first = false;
            }

            foreach ($prefixColumns as $column) {
                $method = $first ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', "{$escaped}%");
                $first = false;
            }

            foreach ($containsColumns as $column) {
                $method = $first ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', "%{$escaped}%");
                $first = false;
            }
        });
    }

    /**
     * Prefix match related user name/email (avoids full-table LIKE on users when possible).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function orWhereUserMatches(Builder $query, string $term, string $relation = 'user'): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $query;
        }

        $escaped = addcslashes($term, '%_\\');

        return $query->orWhereHas($relation, function (Builder $userQuery) use ($escaped, $term): void {
            $userQuery->where(function (Builder $inner) use ($escaped, $term): void {
                $inner->where('email', $term)
                    ->orWhere('email', 'like', "{$escaped}%")
                    ->orWhere('name', 'like', "{$escaped}%");
            });
        });
    }

    /**
     * Single-pass conditional aggregates for status dashboards.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $statusMap  alias => status value
     * @return array{total: int}&array<string, int>
     */
    public static function statusCounts(Builder $query, string $statusColumn, array $statusMap): array
    {
        self::assertSafeIdentifier($statusColumn);

        $bindings = [];
        $selects = ['COUNT(*) as aggregate_total'];

        foreach ($statusMap as $alias => $status) {
            self::assertSafeIdentifier($alias);
            $selects[] = "SUM(CASE WHEN {$statusColumn} = ? THEN 1 ELSE 0 END) as {$alias}";
            $bindings[] = $status;
        }

        /** @var object|null $row */
        $row = (clone $query)->toBase()->selectRaw(implode(', ', $selects), $bindings)->first();

        $result = ['total' => (int) ($row->aggregate_total ?? 0)];

        foreach (array_keys($statusMap) as $alias) {
            $result[$alias] = (int) ($row->{$alias} ?? 0);
        }

        return $result;
    }

    /**
     * Single-pass numeric aggregates (sum/avg/count) for filtered log dashboards.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string>  $expressions  alias => SQL expression (no user input)
     * @return array<string, float|int>
     */
    public static function aggregates(Builder $query, array $expressions): array
    {
        $selects = [];

        foreach ($expressions as $alias => $expression) {
            self::assertSafeIdentifier($alias);
            $selects[] = "{$expression} as {$alias}";
        }

        /** @var object|null $row */
        $row = (clone $query)->toBase()->selectRaw(implode(', ', $selects))->first();

        $result = [];

        foreach (array_keys($expressions) as $alias) {
            $value = $row->{$alias} ?? 0;
            $result[$alias] = is_numeric($value) ? $value + 0 : 0;
        }

        return $result;
    }

    private static function assertSafeIdentifier(string $value): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value)) {
            throw new InvalidArgumentException("Unsafe SQL identifier: {$value}");
        }
    }
}
