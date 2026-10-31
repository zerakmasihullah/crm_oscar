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

class CreateRecord implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function __construct(
        public ?User $user = null,
        public ?AiResourceCrudService $crud = null,
    ) {}

    public function description(): Stringable|string
    {
        return 'Create a record in a resource. Pass resource key plus fields object (e.g. field_reports: title/body/location/status; users: name/email/password/roles[]; roles: name/permissions[]). Returns an Open link when available. High-impact resources may require Approve.';
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

        $fields = $request['fields'] ?? null;
        if (! is_array($fields)) {
            $fields = collect($request->all())
                ->except(['resource', 'fields'])
                ->all();
        }

        if ($fields === []) {
            return 'Error: fields are required.';
        }

        try {
            return ($this->crud ?? app(AiResourceCrudService::class))->createRecord($user, $resource, $fields);
        } catch (Throwable $e) {
            return 'Error: '.$e->getMessage();
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->min(1)->required(),
            'fields' => $schema->object()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $key = (string) ($request['resource'] ?? '');
        if ($key === '' || ! AiResourceCatalog::has($key)) {
            return Approval::required('Create this record? Confirm before continuing.');
        }

        $resource = AiResourceCatalog::get($key);
        if (! AiResourceCatalog::requiresApproval($resource, 'create')) {
            return false;
        }

        $label = (string) ($resource['label'] ?? $key);

        return Approval::required("Create {$label}? Review the fields before approving.");
    }
}
