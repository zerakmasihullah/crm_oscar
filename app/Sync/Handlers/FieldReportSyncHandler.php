<?php

namespace App\Sync\Handlers;

use App\Contracts\SyncsOfflineMutations;
use App\Models\Attachment;
use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FieldReportSyncHandler implements SyncsOfflineMutations
{
    public function handle(User $user, string $action, array $body, Request $request): array
    {
        return match ($action) {
            'create', 'upsert' => $this->upsert($user, $body),
            'update' => $this->upsert($user, $body),
            'delete' => $this->delete($user, $body),
            default => [
                'status' => 'failed',
                'message' => "Unknown action [{$action}].",
            ],
        };
    }

    public function pull(User $user, ?string $since, int $limit = 100): array
    {
        $query = FieldReport::query()
            ->with('attachments')
            ->where('user_id', $user->id)
            ->orderBy('updated_at')
            ->limit($limit);

        if ($since) {
            $query->where('updated_at', '>', Carbon::parse($since));
        }

        $items = $query->get()->map(fn (FieldReport $report): array => $this->serialize($report))->all();

        $cursor = count($items) > 0 ? ($items[array_key_last($items)]['updated_at'] ?? null) : $since;

        return [
            'items' => $items,
            'cursor' => $cursor,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: string, data?: mixed, errors?: array<string, mixed>, message?: string}
     */
    protected function upsert(User $user, array $body): array
    {
        $validator = validator($body, [
            'client_uuid' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:draft,submitted'],
            'attachments_meta' => ['nullable', 'array'],
            'attachments_meta.*.id' => ['nullable'],
            'client_updated_at' => ['nullable', 'date'],
            'force_overwrite' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array{client_uuid: string, title: string, body?: string|null, location?: string|null, status?: string, attachments_meta?: array|null, client_updated_at?: string|null, force_overwrite?: bool} $data */
        $data = $validator->validated();

        $clientUpdatedAt = isset($data['client_updated_at'])
            ? Carbon::parse($data['client_updated_at'])
            : now();

        $existing = FieldReport::withTrashed()
            ->where('client_uuid', $data['client_uuid'])
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return [
                'status' => 'failed',
                'message' => 'client_uuid belongs to another user.',
            ];
        }

        $forceOverwrite = (bool) ($data['force_overwrite'] ?? false);

        if (
            $existing
            && ! $forceOverwrite
            && $existing->client_updated_at
            && $existing->client_updated_at->greaterThan($clientUpdatedAt)
        ) {
            return [
                'status' => 'conflict',
                'message' => 'Server copy is newer (last-write-wins).',
                'data' => $this->serialize($existing->fresh(['attachments']) ?? $existing),
            ];
        }

        $attachmentsMeta = $this->normalizeAttachmentsMeta($user, $data['attachments_meta'] ?? null);

        $payload = [
            'user_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'attachments_meta' => $attachmentsMeta,
            'client_updated_at' => $forceOverwrite ? now() : $clientUpdatedAt,
        ];

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($payload)->save();
            $report = $existing->fresh(['attachments']);
        } else {
            $report = FieldReport::query()->create([
                'client_uuid' => $data['client_uuid'],
                ...$payload,
            ])->load('attachments');
        }

        return [
            'status' => 'processed',
            'data' => $this->serialize($report),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: string, data?: mixed, message?: string}
     */
    protected function delete(User $user, array $body): array
    {
        $clientUuid = $body['client_uuid'] ?? null;

        if (! is_string($clientUuid) || ! Str::isUuid($clientUuid)) {
            return [
                'status' => 'failed',
                'message' => 'client_uuid is required.',
            ];
        }

        $report = FieldReport::query()
            ->where('client_uuid', $clientUuid)
            ->where('user_id', $user->id)
            ->first();

        if (! $report) {
            return [
                'status' => 'processed',
                'message' => 'Already absent.',
            ];
        }

        $report->delete();

        return [
            'status' => 'processed',
            'data' => ['client_uuid' => $clientUuid, 'deleted' => true],
        ];
    }

    /**
     * @param  array<int, mixed>|null  $meta
     * @return list<array{id: int}>|null
     */
    protected function normalizeAttachmentsMeta(User $user, ?array $meta): ?array
    {
        if ($meta === null) {
            return null;
        }

        $ids = [];

        foreach ($meta as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $id = $item['id'];

            if (! is_numeric($id)) {
                continue;
            }

            $attachment = Attachment::query()->find((int) $id);

            if (! $attachment || ! $attachment->ownedBy($user)) {
                throw ValidationException::withMessages([
                    'attachments_meta' => ['Attachment does not belong to this user.'],
                ]);
            }

            $ids[] = ['id' => (int) $id];
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(FieldReport $report): array
    {
        return [
            'id' => $report->id,
            'client_uuid' => $report->client_uuid,
            'title' => $report->title,
            'body' => $report->body,
            'location' => $report->location,
            'status' => $report->status,
            'attachments_meta' => $report->attachments_meta,
            'attachments' => $report->relationLoaded('attachments')
                ? $report->attachments->map(fn (Attachment $attachment): array => [
                    'id' => $attachment->id,
                    'client_uuid' => $attachment->client_uuid,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                    'download_url' => $attachment->download_url,
                ])->all()
                : [],
            'client_updated_at' => $report->client_updated_at?->toIso8601String(),
            'updated_at' => $report->updated_at?->toIso8601String(),
            'deleted_at' => $report->deleted_at?->toIso8601String(),
        ];
    }
}
