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

class ListResources implements Tool
{
    public function __construct(
        public ?User $user = null,
        public ?AiResourceCrudService $crud = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'List agent-operable resources (sections) in this app and which CRUD actions the current user may perform. Call this when unsure which resource key to use.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? Auth::user();
        if (! $user instanceof User) {
            return 'Error: Not authenticated.';
        }

        $rows = ($this->crud ?? app(AiResourceCrudService::class))->listResources($user);
        if ($rows === []) {
            return 'No AI resources are registered.';
        }

        $lines = ['Available resources:'];
        foreach ($rows as $row) {
            $caps = [];
            if ($row['can_view']) {
                $caps[] = 'view';
            }
            if ($row['can_create']) {
                $caps[] = 'create';
            }
            if ($row['can_edit']) {
                $caps[] = 'edit';
            }
            if ($row['can_delete']) {
                $caps[] = 'delete';
            }
            $capText = $caps === [] ? 'none' : implode(',', $caps);
            $lines[] = "- {$row['key']} ({$row['label']}): {$capText}";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
