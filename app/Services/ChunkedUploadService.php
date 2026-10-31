<?php

namespace App\Services;

use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ChunkedUploadService
{
    public function __construct(
        private FileManagerService $files,
    ) {}

    /**
     * @param  array{
     *     filename: string,
     *     size: int,
     *     mime?: string|null,
     *     checksum?: string|null,
     *     chunk_bytes?: int|null,
     *     destination?: string,
     *     root?: string|null,
     *     path?: string|null,
     *     disk?: string|null,
     *     directory?: string|null,
     *     replace_path?: string|null,
     *     attachable?: string|null,
     *     attachable_id?: int|null,
     *     replace_attachment_id?: int|null,
     *     meta?: array<string, mixed>|null
     * }  $input
     */
    public function initiate(User $user, array $input): UploadSession
    {
        $filename = $this->sanitizeFilename($input['filename']);
        $size = (int) $input['size'];
        $maxBytes = (int) config('uploads.max_bytes');
        $chunkBytes = $this->resolveChunkBytes(isset($input['chunk_bytes']) ? (int) $input['chunk_bytes'] : null);
        $destination = $input['destination'] ?? 'file_manager';
        $root = $input['root'] ?? 'private';
        $relativePath = $input['path'] ?? '';
        $declaredMime = isset($input['mime']) ? trim((string) $input['mime']) : null;
        $expectedChecksum = $this->normalizeChecksum($input['checksum'] ?? null);

        if (! in_array($destination, ['file_manager', 'attachment', 'disk'], true)) {
            throw new InvalidArgumentException(__('Unknown upload destination.'));
        }

        if ($size <= 0) {
            throw new InvalidArgumentException(__('File size must be greater than zero.'));
        }

        if ($size > $maxBytes) {
            throw new InvalidArgumentException(__('The file exceeds the maximum upload size of :max.', [
                'max' => $this->humanBytes($maxBytes),
            ]));
        }

        if (config('uploads.require_file_checksum') && $expectedChecksum === null) {
            throw new InvalidArgumentException(__('A SHA-256 checksum is required for this upload.'));
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION)) ?: null;
        $this->assertExtensionAllowed($extension);
        $this->assertMimeAllowed($declaredMime);

        $meta = is_array($input['meta'] ?? null) ? $input['meta'] : [];

        if ($destination === 'file_manager') {
            $this->files->assertRoot((string) $root);
            $this->files->ensureRootExists((string) $root);
            $this->files->assertDirectory((string) $root, (string) $relativePath);

            if (! empty($input['replace_path'])) {
                // Validated on finalize against the storage root.
                $meta['replace_path'] = trim(str_replace('\\', '/', (string) $input['replace_path']), '/');
            }
        }

        if ($destination === 'disk') {
            $disk = (string) ($input['disk'] ?? 'public');
            $directory = trim(str_replace('\\', '/', (string) ($input['directory'] ?? '')), '/');
            $this->assertDiskAllowed($disk);
            $meta['disk'] = $disk;
            $meta['directory'] = $directory;

            if (! empty($input['replace_path'])) {
                $meta['replace_path'] = ltrim(str_replace('\\', '/', (string) $input['replace_path']), '/');
            }
        }

        if ($destination === 'attachment') {
            $attachableKey = (string) ($input['attachable'] ?? '');
            $attachableId = (int) ($input['attachable_id'] ?? 0);
            $parent = $this->resolveAttachable($user, $attachableKey, $attachableId);
            $meta['attachable'] = $attachableKey;
            $meta['attachable_type'] = $parent->getMorphClass();
            $meta['attachable_id'] = (int) $parent->getKey();

            if (! empty($input['replace_attachment_id'])) {
                $meta['replace_attachment_id'] = (int) $input['replace_attachment_id'];
            }
        }

        $this->assertUserCapacity($user);
        $this->assertDiskSpace($size);

        $totalChunks = (int) ceil($size / $chunkBytes);

        if ($totalChunks < 1 || $totalChunks > (int) config('uploads.max_chunks')) {
            throw new InvalidArgumentException(__('The file would require too many chunks. Try a larger chunk size.'));
        }

        $sessionId = (string) Str::uuid();
        $disk = (string) config('uploads.disk', 'local');
        $directory = trim((string) config('uploads.chunk_directory', 'chunked-uploads'), '/').'/'.$sessionId;

        Storage::disk($disk)->makeDirectory($directory);

        return UploadSession::query()->create([
            'id' => $sessionId,
            'user_id' => $user->id,
            'original_filename' => $input['filename'],
            'safe_filename' => $filename,
            'extension' => $extension,
            'declared_mime' => $declaredMime !== '' ? $declaredMime : null,
            'total_bytes' => $size,
            'chunk_bytes' => $chunkBytes,
            'total_chunks' => $totalChunks,
            'received_chunks' => [],
            'received_bytes' => 0,
            'checksum_algo' => (string) config('uploads.checksum_algo', 'sha256'),
            'expected_checksum' => $expectedChecksum,
            'status' => UploadSession::STATUS_PENDING,
            'destination' => $destination,
            'destination_root' => $destination === 'file_manager' ? $root : ($meta['disk'] ?? null),
            'destination_path' => match ($destination) {
                'file_manager' => (string) $relativePath,
                'disk' => (string) ($meta['directory'] ?? ''),
                default => null,
            },
            'chunk_disk' => $disk,
            'chunk_directory' => $directory,
            'meta' => $meta,
            'expires_at' => now()->addHours((int) config('uploads.session_ttl_hours', 48)),
        ]);
    }

    public function storeChunk(
        UploadSession $session,
        int $index,
        UploadedFile $chunk,
        ?string $checksum = null,
    ): UploadSession {
        $this->assertSessionWritable($session);

        if ($index < 0 || $index >= $session->total_chunks) {
            throw new InvalidArgumentException(__('Invalid chunk index.'));
        }

        $expectedSize = $this->expectedChunkSize($session, $index);
        $actualSize = (int) $chunk->getSize();

        if ($actualSize !== $expectedSize) {
            throw new InvalidArgumentException(__('Chunk :index has the wrong size (expected :expected bytes, got :actual).', [
                'index' => $index,
                'expected' => $expectedSize,
                'actual' => $actualSize,
            ]));
        }

        $normalizedChecksum = $this->normalizeChecksum($checksum);

        if (config('uploads.require_chunk_checksum') && $normalizedChecksum === null) {
            throw new InvalidArgumentException(__('Each chunk must include a SHA-256 checksum.'));
        }

        $binary = file_get_contents($chunk->getRealPath());

        if ($binary === false) {
            throw new RuntimeException(__('Could not read the uploaded chunk.'));
        }

        if ($normalizedChecksum !== null) {
            $actual = hash((string) config('uploads.checksum_algo', 'sha256'), $binary);

            if (! hash_equals($normalizedChecksum, $actual)) {
                throw new InvalidArgumentException(__('Chunk :index failed integrity verification.', [
                    'index' => $index,
                ]));
            }
        }

        return DB::transaction(function () use ($session, $index, $binary, $expectedSize) {
            /** @var UploadSession $locked */
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $this->assertSessionWritable($locked);

            $disk = Storage::disk($locked->chunk_disk);
            $path = $this->chunkPath($locked, $index);
            $already = in_array($index, $locked->receivedChunkIndexes(), true);

            if (! $disk->put($path, $binary, 'private')) {
                throw new RuntimeException(__('Could not store chunk :index.', ['index' => $index]));
            }

            $received = $locked->receivedChunkIndexes();

            if (! $already) {
                $received[] = $index;
                sort($received);
                $locked->received_chunks = $received;
                $locked->received_bytes = min(
                    $locked->total_bytes,
                    $locked->received_bytes + $expectedSize,
                );
            }

            $locked->status = UploadSession::STATUS_UPLOADING;
            $locked->save();

            return $locked->fresh() ?? $locked;
        });
    }

    public function complete(UploadSession $session): UploadSession
    {
        return DB::transaction(function () use ($session) {
            /** @var UploadSession $locked */
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === UploadSession::STATUS_COMPLETED) {
                return $locked;
            }

            $this->assertSessionWritable($locked);

            $missing = $locked->missingChunkIndexes();

            if ($missing !== []) {
                throw new InvalidArgumentException(__('Upload is incomplete. Missing :count chunk(s).', [
                    'count' => count($missing),
                ]));
            }

            $locked->status = UploadSession::STATUS_ASSEMBLING;
            $locked->save();

            try {
                $assembled = $this->assemble($locked);
                $finalRelative = $this->finalizeDestination($locked, $assembled['absolute'], $assembled['checksum'], $assembled['mime']);

                $this->deleteChunkDirectory($locked);

                $locked->forceFill([
                    'status' => UploadSession::STATUS_COMPLETED,
                    'actual_checksum' => $assembled['checksum'],
                    'detected_mime' => $assembled['mime'],
                    'final_path' => $finalRelative,
                    'meta' => $locked->meta,
                    'assembled_at' => now(),
                    'completed_at' => now(),
                    'error_message' => null,
                ])->save();
            } catch (Throwable $e) {
                $locked->forceFill([
                    'status' => UploadSession::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ])->save();

                throw $e;
            }

            return $locked->fresh() ?? $locked;
        });
    }

    public function abort(UploadSession $session): UploadSession
    {
        return DB::transaction(function () use ($session) {
            /** @var UploadSession $locked */
            $locked = UploadSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === UploadSession::STATUS_COMPLETED) {
                throw new InvalidArgumentException(__('A completed upload cannot be aborted.'));
            }

            if (in_array($locked->status, [UploadSession::STATUS_ABORTED, UploadSession::STATUS_EXPIRED], true)) {
                return $locked;
            }

            $this->deleteChunkDirectory($locked);

            $locked->forceFill([
                'status' => UploadSession::STATUS_ABORTED,
                'error_message' => __('Upload aborted.'),
            ])->save();

            return $locked;
        });
    }

    public function status(UploadSession $session): UploadSession
    {
        if ($session->isActive() && $session->isExpired()) {
            $this->expire($session);

            return $session->fresh() ?? $session;
        }

        return $session;
    }

    public function expire(UploadSession $session): void
    {
        if (! $session->isActive()) {
            return;
        }

        $this->deleteChunkDirectory($session);

        $session->forceFill([
            'status' => UploadSession::STATUS_EXPIRED,
            'error_message' => __('Upload session expired.'),
        ])->save();
    }

    /**
     * @return array{expired: int, failed_stale: int}
     */
    public function prune(bool $dryRun = false): array
    {
        $expired = 0;
        $failedStale = 0;

        UploadSession::query()
            ->whereIn('status', UploadSession::ACTIVE_STATUSES)
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$expired, $dryRun): void {
                foreach ($sessions as $session) {
                    /** @var UploadSession $session */
                    if ($dryRun) {
                        $expired++;

                        continue;
                    }

                    $this->expire($session);
                    $expired++;
                }
            });

        UploadSession::query()
            ->whereIn('status', [
                UploadSession::STATUS_FAILED,
                UploadSession::STATUS_ABORTED,
                UploadSession::STATUS_EXPIRED,
            ])
            ->where('updated_at', '<', now()->subDays(7))
            ->orderBy('id')
            ->chunkById(100, function ($sessions) use (&$failedStale, $dryRun): void {
                foreach ($sessions as $session) {
                    /** @var UploadSession $session */
                    if ($dryRun) {
                        $failedStale++;

                        continue;
                    }

                    $this->deleteChunkDirectory($session);
                    $session->delete();
                    $failedStale++;
                }
            });

        return [
            'expired' => $expired,
            'failed_stale' => $failedStale,
        ];
    }

    /**
     * @return array{absolute: string, checksum: string, mime: string|null}
     */
    protected function assemble(UploadSession $session): array
    {
        $disk = Storage::disk($session->chunk_disk);
        $root = $disk->path($session->chunk_directory);
        $assembledPath = $root.DIRECTORY_SEPARATOR.'assembled.bin';

        if (is_file($assembledPath)) {
            @unlink($assembledPath);
        }

        $out = fopen($assembledPath, 'wb');

        if ($out === false) {
            throw new RuntimeException(__('Could not open assembly target.'));
        }

        $hash = hash_init((string) config('uploads.checksum_algo', 'sha256'));
        $written = 0;

        try {
            for ($i = 0; $i < $session->total_chunks; $i++) {
                $chunkRelative = $this->chunkPath($session, $i);
                $absolute = $disk->path($chunkRelative);

                if (! is_file($absolute)) {
                    throw new RuntimeException(__('Missing chunk file :index during assembly.', ['index' => $i]));
                }

                $expected = $this->expectedChunkSize($session, $i);
                $size = filesize($absolute);

                if ($size !== $expected) {
                    throw new RuntimeException(__('Chunk :index size mismatch during assembly.', ['index' => $i]));
                }

                $in = fopen($absolute, 'rb');

                if ($in === false) {
                    throw new RuntimeException(__('Could not read chunk :index.', ['index' => $i]));
                }

                while (! feof($in)) {
                    $buffer = fread($in, 1024 * 1024);

                    if ($buffer === false) {
                        fclose($in);
                        throw new RuntimeException(__('Failed while reading chunk :index.', ['index' => $i]));
                    }

                    if ($buffer === '') {
                        continue;
                    }

                    $bytes = fwrite($out, $buffer);

                    if ($bytes === false) {
                        fclose($in);
                        throw new RuntimeException(__('Failed while writing assembled file.'));
                    }

                    hash_update($hash, $buffer);
                    $written += $bytes;
                }

                fclose($in);
            }
        } finally {
            fclose($out);
        }

        if ($written !== $session->total_bytes) {
            @unlink($assembledPath);
            throw new RuntimeException(__('Assembled file size mismatch (expected :expected, got :actual).', [
                'expected' => $session->total_bytes,
                'actual' => $written,
            ]));
        }

        $checksum = hash_final($hash);

        if ($session->expected_checksum !== null && ! hash_equals($session->expected_checksum, $checksum)) {
            @unlink($assembledPath);
            throw new InvalidArgumentException(__('File checksum verification failed.'));
        }

        $mime = @mime_content_type($assembledPath) ?: null;
        $this->assertMimeAllowed($mime);
        $this->assertExtensionAllowed($session->extension);

        // Extra sensitivity: reject PHP source disguised by extension.
        if ($mime !== null && str_contains(strtolower($mime), 'php')) {
            @unlink($assembledPath);
            throw new InvalidArgumentException(__('Executable or script content is not allowed.'));
        }

        return [
            'absolute' => $assembledPath,
            'checksum' => $checksum,
            'mime' => $mime,
        ];
    }

    protected function finalizeDestination(
        UploadSession $session,
        string $assembledAbsolute,
        string $checksum,
        ?string $mime,
    ): string {
        $relative = match ($session->destination) {
            'file_manager' => $this->finalizeFileManager($session, $assembledAbsolute),
            'disk' => $this->finalizeDisk($session, $assembledAbsolute),
            'attachment' => $this->finalizeAttachment($session, $assembledAbsolute, $mime),
            default => throw new InvalidArgumentException(__('Unknown upload destination.')),
        };

        @unlink($assembledAbsolute);

        $session->meta = array_merge($session->meta ?? [], [
            'checksum' => $checksum,
            'mime' => $mime,
        ]);

        return $relative;
    }

    protected function finalizeFileManager(UploadSession $session, string $assembledAbsolute): string
    {
        $replacePath = $session->meta['replace_path'] ?? null;

        if (is_string($replacePath) && $replacePath !== '') {
            try {
                $this->files->delete((string) $session->destination_root, $replacePath);
            } catch (Throwable) {
                // Continue — store the new file anyway.
            }
        }

        return $this->files->storeAssembledFile(
            (string) $session->destination_root,
            (string) $session->destination_path,
            $assembledAbsolute,
            $session->safe_filename,
        );
    }

    protected function finalizeDisk(UploadSession $session, string $assembledAbsolute): string
    {
        $diskName = (string) ($session->meta['disk'] ?? $session->destination_root ?? 'public');
        $directory = trim((string) ($session->meta['directory'] ?? $session->destination_path ?? ''), '/');
        $this->assertDiskAllowed($diskName);

        $disk = Storage::disk($diskName);
        $replacePath = $session->meta['replace_path'] ?? null;

        if (is_string($replacePath) && $replacePath !== '' && $disk->exists($replacePath)) {
            $disk->delete($replacePath);
        }

        $filename = $session->safe_filename;
        $relative = ($directory !== '' ? $directory.'/' : '').$filename;

        if ($disk->exists($relative)) {
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $i = 1;

            do {
                $candidate = $extension !== ''
                    ? "{$base} ({$i}).{$extension}"
                    : "{$base} ({$i})";
                $relative = ($directory !== '' ? $directory.'/' : '').$candidate;
                $i++;
            } while ($disk->exists($relative));
        }

        $stream = fopen($assembledAbsolute, 'rb');

        if ($stream === false) {
            throw new RuntimeException(__('Could not read assembled file.'));
        }

        try {
            if (! $disk->put($relative, $stream)) {
                throw new RuntimeException(__('Could not store the uploaded file.'));
            }
        } finally {
            fclose($stream);
        }

        return $relative;
    }

    protected function finalizeAttachment(
        UploadSession $session,
        string $assembledAbsolute,
        ?string $mime,
    ): string {
        $meta = $session->meta ?? [];
        $attachableKey = (string) ($meta['attachable'] ?? '');
        $attachableId = (int) ($meta['attachable_id'] ?? 0);
        $user = $session->user;

        if (! $user) {
            throw new RuntimeException(__('Upload session owner is missing.'));
        }

        $parent = $this->resolveAttachable($user, $attachableKey, $attachableId);
        $replaceId = isset($meta['replace_attachment_id']) ? (int) $meta['replace_attachment_id'] : null;

        if ($replaceId) {
            /** @var \App\Models\Attachment|null $old */
            $old = $parent->attachments()->whereKey($replaceId)->first();

            if (! $old) {
                throw new InvalidArgumentException(__('The attachment to replace was not found.'));
            }

            $attachment = $parent->replaceAttachment(
                $old,
                $assembledAbsolute,
                $session->safe_filename,
                $mime,
                $session->total_bytes,
                $user->id,
            );
        } else {
            $attachment = $parent->storeAttachmentFromPath(
                $assembledAbsolute,
                $session->safe_filename,
                $mime,
                $session->total_bytes,
                $user->id,
            );
        }

        $session->meta = array_merge($meta, [
            'attachment_id' => $attachment->id,
            'download_url' => $attachment->download_url,
            'replaced_attachment_id' => $replaceId,
        ]);

        return $attachment->file_path;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Model&\App\Concerns\HasAttachments
     */
    protected function resolveAttachable(User $user, string $key, int $id): mixed
    {
        /** @var array<string, mixed>|null $config */
        $config = config("uploads.attachables.{$key}");

        if (! is_array($config) || ! isset($config['model']) || ! is_string($config['model'])) {
            throw new InvalidArgumentException(__('Unsupported attachment target.'));
        }

        $modelClass = $config['model'];

        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, \Illuminate\Database\Eloquent\Model::class)) {
            throw new InvalidArgumentException(__('Unsupported attachment target.'));
        }

        if (! in_array(\App\Concerns\HasAttachments::class, class_uses_recursive($modelClass), true)) {
            throw new InvalidArgumentException(__('This record does not support attachments.'));
        }

        $permissions = $config['permissions'] ?? [];

        if (is_array($permissions) && $permissions !== []) {
            $allowed = false;

            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                throw new InvalidArgumentException(__('You are not allowed to upload attachments here.'));
            }
        }

        /** @var \Illuminate\Database\Eloquent\Model|null $parent */
        $parent = $modelClass::query()->find($id);

        if (! $parent) {
            throw new InvalidArgumentException(__('Attachment parent not found.'));
        }

        if (($config['user_scoped'] ?? false) && isset($parent->user_id) && (int) $parent->user_id !== (int) $user->id) {
            throw new InvalidArgumentException(__('Attachment parent not found.'));
        }

        return $parent;
    }

    protected function assertDiskAllowed(string $disk): void
    {
        $allowed = ['public', 'local'];

        if (! in_array($disk, $allowed, true) || ! array_key_exists($disk, config('filesystems.disks', []))) {
            throw new InvalidArgumentException(__('Storage disk is not allowed.'));
        }
    }

    protected function expectedChunkSize(UploadSession $session, int $index): int
    {
        if ($index === $session->total_chunks - 1) {
            $remainder = $session->total_bytes % $session->chunk_bytes;

            return $remainder === 0 ? $session->chunk_bytes : $remainder;
        }

        return $session->chunk_bytes;
    }

    protected function chunkPath(UploadSession $session, int $index): string
    {
        return $session->chunk_directory.'/'.str_pad((string) $index, 8, '0', STR_PAD_LEFT).'.part';
    }

    protected function deleteChunkDirectory(UploadSession $session): void
    {
        try {
            Storage::disk($session->chunk_disk)->deleteDirectory($session->chunk_directory);
        } catch (Throwable) {
            // Best-effort cleanup.
        }
    }

    protected function assertSessionWritable(UploadSession $session): void
    {
        if ($session->isExpired()) {
            $this->expire($session);
            throw new InvalidArgumentException(__('Upload session expired.'));
        }

        if (! $session->isActive()) {
            throw new InvalidArgumentException(__('Upload session is not accepting data (status: :status).', [
                'status' => $session->status,
            ]));
        }
    }

    protected function assertUserCapacity(User $user): void
    {
        $max = (int) config('uploads.max_active_sessions_per_user', 10);
        $active = UploadSession::query()
            ->where('user_id', $user->id)
            ->whereIn('status', UploadSession::ACTIVE_STATUSES)
            ->count();

        if ($active >= $max) {
            throw new InvalidArgumentException(__('You already have the maximum number of active uploads. Finish or abort one first.'));
        }
    }

    protected function assertDiskSpace(int $neededBytes): void
    {
        $disk = Storage::disk((string) config('uploads.disk', 'local'));
        $root = $disk->path('');
        $free = @disk_free_space($root);

        if ($free === false) {
            return;
        }

        $headroom = (int) config('uploads.min_free_bytes', 0);

        if ($free < ($neededBytes + $headroom)) {
            throw new RuntimeException(__('Not enough free disk space to accept this upload.'));
        }
    }

    protected function resolveChunkBytes(?int $requested): int
    {
        $default = (int) config('uploads.chunk_bytes');
        $min = (int) config('uploads.min_chunk_bytes');
        $max = (int) config('uploads.max_chunk_bytes');
        $value = $requested !== null && $requested > 0 ? $requested : $default;

        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(__('Chunk size must be between :min and :max bytes.', [
                'min' => $min,
                'max' => $max,
            ]));
        }

        return $value;
    }

    protected function sanitizeFilename(string $name): string
    {
        $name = trim(str_replace(['\\', '/'], '', $name));

        if ($name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException(__('Enter a valid file name.'));
        }

        if (preg_match('/[\x00-\x1F<>:"|?*]/', $name)) {
            throw new InvalidArgumentException(__('The file name contains invalid characters.'));
        }

        if (mb_strlen($name) > 180) {
            throw new InvalidArgumentException(__('The file name is too long.'));
        }

        // Block double-extension script tricks: report.php.jpg still ok by extension,
        // but report.php. or name.php.anything with blocked middle segments.
        $parts = explode('.', $name);

        if (count($parts) > 2) {
            $blocked = array_map('strtolower', (array) config('uploads.blocked_extensions', []));

            foreach (array_slice($parts, 0, -1) as $segment) {
                if (in_array(strtolower($segment), $blocked, true)) {
                    throw new InvalidArgumentException(__('The file name is not allowed.'));
                }
            }
        }

        return $name;
    }

    protected function assertExtensionAllowed(?string $extension): void
    {
        if ($extension === null || $extension === '') {
            return;
        }

        $extension = strtolower($extension);
        $blocked = array_map('strtolower', (array) config('uploads.blocked_extensions', []));

        if (in_array($extension, $blocked, true)) {
            throw new InvalidArgumentException(__('Files of type .:ext are not allowed.', ['ext' => $extension]));
        }

        $allowed = array_map('strtolower', (array) config('uploads.allowed_extensions', []));

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            throw new InvalidArgumentException(__('Files of type .:ext are not allowed.', ['ext' => $extension]));
        }
    }

    protected function assertMimeAllowed(?string $mime): void
    {
        if ($mime === null || $mime === '') {
            return;
        }

        $mime = strtolower(trim(explode(';', $mime)[0]));
        $blocked = array_map('strtolower', (array) config('uploads.blocked_mimes', []));

        if (in_array($mime, $blocked, true)) {
            throw new InvalidArgumentException(__('This file type is not allowed.'));
        }

        $allowed = array_map('strtolower', (array) config('uploads.allowed_mimes', []));

        if ($allowed !== [] && ! in_array($mime, $allowed, true)) {
            throw new InvalidArgumentException(__('This file type is not allowed.'));
        }
    }

    protected function normalizeChecksum(mixed $checksum): ?string
    {
        if ($checksum === null || $checksum === '') {
            return null;
        }

        $checksum = strtolower(trim((string) $checksum));

        if (! preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new InvalidArgumentException(__('Checksum must be a 64-character SHA-256 hex digest.'));
        }

        return $checksum;
    }

    protected function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 2).' '.$units[$i];
    }
}
