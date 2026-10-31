<?php

namespace App\Ai\Tools\Catalog;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Spatie\Permission\Models\Permission;
use Stringable;

class ListPermissions implements Tool
{
    public function __construct(public ?User $user = null) {}

    public function description(): Stringable|string
    {
        return 'List Spatie permission names available when creating or updating roles. Optional query filters by name substring. Use before CreateRecord on resource=roles.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? Auth::user();
        if (! $user instanceof User) {
            return 'Error: Not authenticated.';
        }

        if (! $user->can('roles.view') && ! $user->can('roles.create') && ! $user->can('roles.edit')) {
            return 'Error: You do not have permission to view permissions.';
        }

        $query = Permission::query()->orderBy('name');
        $term = trim((string) ($request['query'] ?? ''));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where('name', 'like', $like);
        }

        $names = $query->limit(200)->pluck('name')->all();
        if ($names === []) {
            return 'No permissions matched.';
        }

        return "Permissions ({$this->countLabel(count($names))}):\n- ".implode("\n- ", $names);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->max(120),
        ];
    }

    protected function countLabel(int $count): string
    {
        return $count.' shown';
    }
}
