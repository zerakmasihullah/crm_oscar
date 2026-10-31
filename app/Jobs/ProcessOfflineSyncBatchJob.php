<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Throwable;

/**
 * Example job for deferred / retryable offline mutation batches.
 *
 * The live /api/sync/batch endpoint stays synchronous so the client can apply
 * results immediately. Dispatch this job when a clone needs background retries
 * or server-side reprocessing of a stored mutation payload.
 */
class ProcessOfflineSyncBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  list<array{client_uuid: string, resource: string, action: string, body?: array<string, mixed>, client_updated_at?: string|null}>  $mutations
     */
    public function __construct(
        public int $userId,
        public array $mutations,
    ) {
        $this->onQueue('sync');
    }

    /**
     * @return list<array{client_uuid: string, status: string, data?: mixed, errors?: array<string, mixed>, message?: string}>
     */
    public function handle(SyncService $sync): array
    {
        $user = User::query()->findOrFail($this->userId);

        $request = Request::create('/api/sync/batch', 'POST', [
            'mutations' => $this->mutations,
        ]);
        $request->setUserResolver(fn (): User => $user);

        return $sync->processBatch($user, $this->mutations, $request);
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}
