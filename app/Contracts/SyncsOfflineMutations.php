<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Http\Request;

interface SyncsOfflineMutations
{
    /**
     * @param  array<string, mixed>  $body
     * @return array{status: string, data?: mixed, errors?: array<string, mixed>, message?: string}
     */
    public function handle(User $user, string $action, array $body, Request $request): array;

    /**
     * @return array{items: list<array<string, mixed>>, cursor: string|null}
     */
    public function pull(User $user, ?string $since, int $limit = 100): array;
}
