<?php

namespace Tests\Unit;

use App\Support\InertiaPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class InertiaPaginationTest extends TestCase
{
    public function test_it_normalizes_a_length_aware_paginator(): void
    {
        $paginator = new LengthAwarePaginator(
            items: [['id' => 1], ['id' => 2]],
            total: 25,
            perPage: 10,
            currentPage: 2,
            options: ['path' => 'http://localhost/items'],
        );

        $payload = InertiaPagination::make($paginator);

        $this->assertSame([['id' => 1], ['id' => 2]], $payload['data']);
        $this->assertSame(2, $payload['meta']['current_page']);
        $this->assertSame(3, $payload['meta']['last_page']);
        $this->assertSame(10, $payload['meta']['per_page']);
        $this->assertSame(25, $payload['meta']['total']);
        $this->assertSame(11, $payload['meta']['from']);
        $this->assertSame(12, $payload['meta']['to']);
        $this->assertNotEmpty($payload['links']);
        $this->assertArrayHasKey('url', $payload['links'][0]);
        $this->assertArrayHasKey('label', $payload['links'][0]);
        $this->assertArrayHasKey('active', $payload['links'][0]);
    }
}
