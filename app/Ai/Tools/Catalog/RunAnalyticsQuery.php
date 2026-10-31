<?php

namespace App\Ai\Tools\Catalog;

use App\Models\User;
use App\Services\Ai\AiAnalyticsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class RunAnalyticsQuery implements Tool
{
    public function __construct(
        public ?User $user = null,
        public ?AiAnalyticsService $analytics = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Run a read-only analytics query from a natural-language question. Returns SQL used plus a markdown table of results. Never writes to the database. Client-supplied SQL is not accepted.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? Auth::user();
        if (! $user instanceof User) {
            return 'Error: Not authenticated.';
        }

        $question = trim((string) ($request['question'] ?? ''));
        if ($question === '') {
            return 'Error: question is required.';
        }

        try {
            // Author-generated SQL only — never trust client/tool-supplied SQL.
            $result = ($this->analytics ?? app(AiAnalyticsService::class))->run(
                $user,
                $question,
                null,
            );

            return $result['markdown'];
        } catch (Throwable $e) {
            $message = $e->getMessage();
            if (
                str_contains(strtolower($message), 'access')
                || str_contains(strtolower($message), 'permission')
            ) {
                return 'Error: You do not have access to analyze that data.';
            }

            return 'Error: Analytics query failed for data you can access.';
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->max(2000)->required(),
        ];
    }
}
