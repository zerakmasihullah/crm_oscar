<?php

namespace App\Ai\Tools\Catalog;

use App\Models\User;
use App\Services\Ai\AiResourceCrudService;
use App\Support\AiResourceCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class UpdateRecord implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public ?User $user = null,
        public ?AiResourceCrudService $crud = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Update a record by resource key and id. Pass only fields to change inside fields object. High-impact resources may require Approve.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = $this->user ?? Auth::user();
        if (! $user instanceof User) {
            return 'Error: Not authenticated.';
        }

        $resource = (string) ($request['resource'] ?? '');
        $id = (int) ($request['id'] ?? 0);
        if ($resource === '' || $id < 1) {
            return 'Error: resource and id are required.';
        }

        $fields = $request['fields'] ?? null;
        if (! is_array($fields)) {
            $fields = collect($request->all())
                ->except(['resource', 'id', 'fields'])
                ->all();
        }

        try {
            return ($this->crud ?? app(AiResourceCrudService::class))->updateRecord($user, $resource, $id, $fields);
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->min(1)->required(),
            'id' => $schema->integer()->min(1)->required(),
            'fields' => $schema->object()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $key = (string) ($request['resource'] ?? '');
        $id = $request['id'] ?? '?';
        if ($key === '' || ! AiResourceCatalog::has($key)) {
            return Approval::required("Update {$key} #{$id}? Confirm before continuing.");
        }

        $resource = AiResourceCatalog::get($key);
        if (! AiResourceCatalog::requiresApproval($resource, 'update')) {
            return false;
        }

        $label = (string) ($resource['label'] ?? $key);

        return Approval::required("Update {$label} #{$id}? Review the changes before approving.");
    }
}
