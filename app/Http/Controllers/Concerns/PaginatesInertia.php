<?php

namespace App\Http\Controllers\Concerns;

use App\Support\InertiaPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

trait PaginatesInertia
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{data: mixed, links: list<array{url: string|null, label: string, active: bool}>, meta: array<string, mixed>}
     */
    protected function inertiaPagination(LengthAwarePaginator $paginator): array
    {
        return InertiaPagination::make($paginator);
    }
}
