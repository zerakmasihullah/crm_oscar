<?php

namespace App\Http\Controllers\Api\Concerns;

trait PaginatesJson
{
    /**
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, mixed>  $paginator
     * @return array{data: mixed, meta: array<string, mixed>}
     */
    protected function paginated(mixed $paginator): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }
}
