<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Normalize a LengthAwarePaginator for Inertia list pages.
 *
 * Prefer this (or rely on MisPagination's client-side normalize) so every
 * table gets a consistent `{ data, links, meta }` payload.
 *
 * @phpstan-type PaginationMeta array{
 *     current_page: int,
 *     last_page: int,
 *     per_page: int,
 *     total: int,
 *     from: int|null,
 *     to: int|null
 * }
 */
final class InertiaPagination
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{data: mixed, links: list<array{url: string|null, label: string, active: bool}>, meta: PaginationMeta}
     */
    public static function make(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'links' => $paginator->linkCollection()->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
