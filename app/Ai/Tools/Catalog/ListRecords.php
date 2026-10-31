<?php

namespace App\Ai\Tools\Catalog;

use App\Models\User;
use App\Services\Ai\AiResourceCrudService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class ListRecords implements Tool
{
    public function __construct(
        public ?User $user = null,
        public ?AiResourceCrudService $crud = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'List records for a resource key (e.g. field_reports, users, roles). Optional query search, filter object of attribute=>value, and limit 1–20. Each row may include an Open link.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? Auth::user();
        if (! $user instanceof User) {
            return 'Error: Not authenticated.';
        }

        $resource = (string) ($request['resource'] ?? '');
        if ($resource === '') {
            return 'Error: resource is required.';
        }

        try {
            return ($this->crud ?? app(AiResourceCrudService::class))->listRecords($user, $resource, [
                'query' => $request['query'] ?? null,
                'filter' => is_array($request['filter'] ?? null) ? $request['filter'] : [],
                'limit' => $request['limit'] ?? 10,
            ]);
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->min(1)->required(),
            'query' => $schema->string()->max(200),
            'filter' => $schema->object(),
            'limit' => $schema->integer()->min(1)->max(20),
        ];
    }
}
