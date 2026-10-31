<?php

namespace App\Sync;

use App\Contracts\SyncsOfflineMutations;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Base last-write-wins sync handler for offline-capable resources.
 *
 * New modules should extend this (or follow the same contract) instead of
 * re-implementing conflict/idempotency logic.
 */
abstract class AbstractOfflineSyncHandler implements SyncsOfflineMutations
{
    abstract protected function modelClass(): string;

    /**
     * Validation rules for upsert bodies (excluding client_uuid / client_updated_at / force_overwrite).
     *
     * @return array<string, mixed>
     */
    abstract protected function rules(): array;

    /**
     * Map validated input to model attributes (excluding client_uuid / client_updated_at).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    abstract protected function attributesFromValidated(array $data): array;

    protected function userScoped(): bool
    {
        return false;
    }

    protected function userIdColumn(): string
    {
        return 'user_id';
    }

    /**
     * @return list<string>
     */
    protected function serializeExcept(): array
    {
        return [];
    }

    public function handle(User $user, string $action, array $body, Request $request): array
    {
        return match ($action) {
            'create', 'update', 'upsert' => $this->upsert($user, $body),
            'delete' => $this->delete($user, $body),
            default => [
                'status' => 'failed',
                'message' => "Unknown action [{$action}].",
            ],
        };
    }

    public function pull(User $user, ?string $since, int $limit = 100): array
    {
        $query = $this->newQuery()
            ->orderBy('updated_at')
            ->limit($limit);

        if ($this->userScoped()) {
            $query->where($this->userIdColumn(), $user->id);
        }

        if ($since) {
            $query->where('updated_at', '>', Carbon::parse($since));
        }

        $items = $query->get()
            ->map(fn (Model $model): array => $this->serialize($model))
            ->all();

        $cursor = count($items) > 0
            ? ($items[array_key_last($items)]['updated_at'] ?? null)
            : $since;

        return [
            'items' => $items,
            'cursor' => $cursor,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: string, data?: mixed, message?: string, errors?: array<string, mixed>}
     */
    protected function upsert(User $user, array $body): array
    {
        $validator = validator($body, array_merge([
            'client_uuid' => ['required', 'uuid'],
            'client_updated_at' => ['nullable', 'date'],
            'force_overwrite' => ['nullable', 'boolean'],
        ], $this->rules()));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $data */
        $data = $validator->validated();
        $forceOverwrite = (bool) ($data['force_overwrite'] ?? false);
        $clientUpdatedAt = isset($data['client_updated_at'])
            ? Carbon::parse((string) $data['client_updated_at'])
            : now();

        $existing = $this->findByClientUuid((string) $data['client_uuid']);

        if ($existing && $this->userScoped()) {
            $ownerId = (int) $existing->getAttribute($this->userIdColumn());

            if ($ownerId !== (int) $user->id) {
                return [
                    'status' => 'failed',
                    'message' => 'client_uuid belongs to another user.',
                ];
            }
        }

        if (
            $existing
            && ! $forceOverwrite
            && $existing->getAttribute('client_updated_at')
            && Carbon::parse((string) $existing->getAttribute('client_updated_at'))->greaterThan($clientUpdatedAt)
        ) {
            return [
                'status' => 'conflict',
                'message' => 'Server copy is newer (last-write-wins).',
                'data' => $this->serialize($existing),
            ];
        }

        $attributes = $this->attributesFromValidated($data);
        $attributes['client_updated_at'] = $forceOverwrite ? now() : $clientUpdatedAt;

        if ($this->userScoped()) {
            $attributes[$this->userIdColumn()] = $user->id;
        }

        if ($existing) {
            if (method_exists($existing, 'trashed') && $existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($attributes)->save();
            $model = $existing->fresh() ?? $existing;
        } else {
            $model = $this->newQuery()->create([
                'client_uuid' => $data['client_uuid'],
                ...$attributes,
            ]);
        }

        return [
            'status' => 'processed',
            'data' => $this->serialize($model),
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

        $query = $this->newQuery()->where('client_uuid', $clientUuid);

        if ($this->userScoped()) {
            $query->where($this->userIdColumn(), $user->id);
        }

        $model = $query->first();

        if (! $model) {
            return [
                'status' => 'processed',
                'message' => 'Already absent.',
            ];
        }

        $model->delete();

        return [
            'status' => 'processed',
            'data' => ['client_uuid' => $clientUuid, 'deleted' => true],
        ];
    }

    protected function findByClientUuid(string $clientUuid): ?Model
    {
        $class = $this->modelClass();

        if (method_exists($class, 'withTrashed')) {
            return $class::withTrashed()->where('client_uuid', $clientUuid)->first();
        }

        return $this->newQuery()->where('client_uuid', $clientUuid)->first();
    }

    protected function newQuery()
    {
        $class = $this->modelClass();

        return $class::query();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Model $model): array
    {
        $payload = $model->toArray();

        foreach ($this->serializeExcept() as $key) {
            unset($payload[$key]);
        }

        if ($model->getAttribute('client_updated_at')) {
            $payload['client_updated_at'] = Carbon::parse((string) $model->getAttribute('client_updated_at'))->toIso8601String();
        }

        if ($model->getAttribute('updated_at')) {
            $payload['updated_at'] = Carbon::parse((string) $model->getAttribute('updated_at'))->toIso8601String();
        }

        if ($model->getAttribute('deleted_at')) {
            $payload['deleted_at'] = Carbon::parse((string) $model->getAttribute('deleted_at'))->toIso8601String();
        } else {
            $payload['deleted_at'] = null;
        }

        return $payload;
    }
}
