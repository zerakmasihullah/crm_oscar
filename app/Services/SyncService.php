<?php

namespace App\Services;

use App\Contracts\SyncsOfflineMutations;
use App\Models\SyncClientMutation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SyncService
{
    /**
     * @param  list<array{client_uuid: string, resource: string, action: string, body?: array<string, mixed>, client_updated_at?: string|null}>  $mutations
     * @return list<array{client_uuid: string, status: string, data?: mixed, errors?: array<string, mixed>, message?: string}>
     */
    public function processBatch(User $user, array $mutations, Request $request): array
    {
        $max = (int) config('offline.max_batch_size', 50);
        $mutations = array_slice($mutations, 0, $max);
        $results = [];
        $started = microtime(true);

        foreach ($mutations as $mutation) {
            $results[] = $this->processOne($user, $mutation, $request);
        }

        $durationMs = (int) round((microtime(true) - $started) * 1000);

        Log::channel('sync')->info('sync.batch', [
            'user_id' => $user->id,
            'count' => count($mutations),
            'duration_ms' => $durationMs,
        ]);

        return $results;
    }

    /**
     * @param  array{client_uuid: string, resource: string, action: string, body?: array<string, mixed>, client_updated_at?: string|null}  $mutation
     * @return array{client_uuid: string, status: string, data?: mixed, errors?: array<string, mixed>, message?: string}
     */
    public function processOne(User $user, array $mutation, Request $request): array
    {
        $clientUuid = $mutation['client_uuid'] ?? '';
        $resource = $mutation['resource'] ?? '';
        $action = $mutation['action'] ?? '';

        $existing = SyncClientMutation::query()
            ->where('user_id', $user->id)
            ->where('client_uuid', $clientUuid)
            ->first();

        if ($existing && in_array($existing->status, ['processed', 'conflict'], true)) {
            return [
                'client_uuid' => $clientUuid,
                'status' => $existing->status,
                'data' => $existing->response_payload,
                'message' => 'Idempotent replay.',
            ];
        }

        try {
            $handler = $this->handlerFor($resource);
            $body = $mutation['body'] ?? [];
            if (! is_array($body)) {
                $body = [];
            }
            if (! isset($body['client_uuid'])) {
                $body['client_uuid'] = $clientUuid;
            }
            if (isset($mutation['client_updated_at']) && ! isset($body['client_updated_at'])) {
                $body['client_updated_at'] = $mutation['client_updated_at'];
            }

            $result = $handler->handle($user, $action, $body, $request);
            $status = $result['status'] ?? 'processed';

            SyncClientMutation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'client_uuid' => $clientUuid,
                ],
                [
                    'resource' => $resource,
                    'action' => $action,
                    'status' => $status,
                    'request_payload' => $mutation,
                    'response_payload' => $result['data'] ?? $result,
                    'client_updated_at' => $mutation['client_updated_at'] ?? null,
                    'processed_at' => now(),
                ],
            );

            return [
                'client_uuid' => $clientUuid,
                'status' => $status,
                'data' => $result['data'] ?? null,
                'message' => $result['message'] ?? null,
                'errors' => $result['errors'] ?? null,
            ];
        } catch (ValidationException $e) {
            SyncClientMutation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'client_uuid' => $clientUuid,
                ],
                [
                    'resource' => $resource,
                    'action' => $action,
                    'status' => 'failed',
                    'request_payload' => $mutation,
                    'response_payload' => ['errors' => $e->errors()],
                    'client_updated_at' => $mutation['client_updated_at'] ?? null,
                    'processed_at' => now(),
                ],
            );

            return [
                'client_uuid' => $clientUuid,
                'status' => 'failed',
                'errors' => $e->errors(),
                'message' => 'Validation failed.',
            ];
        } catch (\Throwable $e) {
            Log::channel('sync')->error('sync.mutation_failed', [
                'client_uuid' => $clientUuid,
                'resource' => $resource,
                'error' => $e->getMessage(),
            ]);

            SyncClientMutation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'client_uuid' => $clientUuid,
                ],
                [
                    'resource' => $resource,
                    'action' => $action,
                    'status' => 'failed',
                    'request_payload' => $mutation,
                    'response_payload' => ['message' => $e->getMessage()],
                    'client_updated_at' => $mutation['client_updated_at'] ?? null,
                    'processed_at' => now(),
                ],
            );

            return [
                'client_uuid' => $clientUuid,
                'status' => 'failed',
                'message' => 'Server error processing mutation.',
            ];
        }
    }

    /**
     * @return array{resource: string, items: list<array<string, mixed>>, cursor: string|null}
     */
    public function pull(User $user, string $resource, ?string $since, int $limit = 100): array
    {
        $handler = $this->handlerFor($resource);
        $result = $handler->pull($user, $since, $limit);

        return [
            'resource' => $resource,
            'items' => $result['items'],
            'cursor' => $result['cursor'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function warmPayload(User $user): array
    {
        $orgKeys = config('offline.warm.org_settings', []);
        $settings = [];

        foreach ($orgKeys as $key) {
            $settings[$key] = \App\Models\OrgSetting::getValue($key);
        }

        $resources = [];
        foreach (config('offline.warm.resources', []) as $resource => $_config) {
            if (! isset(config('offline.resources')[$resource])) {
                continue;
            }

            $resources[$resource] = $this->pull($user, $resource, null, 100);
        }

        return [
            'org_settings' => $settings,
            'resources' => $resources,
            'warmed_at' => now()->toIso8601String(),
        ];
    }

    public function handlerFor(string $resource): SyncsOfflineMutations
    {
        $map = config('offline.resources', []);
        $class = $map[$resource] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            throw new InvalidArgumentException("No sync handler for resource [{$resource}].");
        }

        $handler = app($class);

        if (! $handler instanceof SyncsOfflineMutations) {
            throw new InvalidArgumentException("Handler for [{$resource}] must implement SyncsOfflineMutations.");
        }

        return $handler;
    }
}
