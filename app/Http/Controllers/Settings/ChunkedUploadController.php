<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\UploadSession;
use App\Services\ChunkedUploadService;
use App\Services\FileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ChunkedUploadController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        private ChunkedUploadService $uploads,
    ) {}

    public function config(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403);

        return response()->json([
            'max_bytes' => (int) config('uploads.max_bytes'),
            'chunk_bytes' => (int) config('uploads.chunk_bytes'),
            'min_chunk_bytes' => (int) config('uploads.min_chunk_bytes'),
            'max_chunk_bytes' => (int) config('uploads.max_chunk_bytes'),
            'concurrency' => (int) config('uploads.concurrency'),
            'require_chunk_checksum' => (bool) config('uploads.require_chunk_checksum'),
            'require_file_checksum' => (bool) config('uploads.require_file_checksum'),
            'checksum_algo' => (string) config('uploads.checksum_algo', 'sha256'),
            'session_ttl_hours' => (int) config('uploads.session_ttl_hours'),
            'simple_max_bytes' => FileManagerService::MAX_UPLOAD_BYTES,
            'attachables' => array_keys(config('uploads.attachables', [])),
        ]);
    }

    public function initiate(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403);

        $destination = $request->input('destination', 'file_manager');

        if ($destination === 'file_manager') {
            $this->authorizePermission($request, 'storage.create');
        }

        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:180'],
            'size' => ['required', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:191'],
            'checksum' => ['nullable', 'string', 'size:64'],
            'chunk_bytes' => ['nullable', 'integer', 'min:1'],
            'destination' => ['nullable', 'string', Rule::in(['file_manager', 'attachment', 'disk'])],
            'root' => ['nullable', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['nullable', 'string', 'max:1000'],
            'disk' => ['nullable', 'string', Rule::in(['public', 'local'])],
            'directory' => ['nullable', 'string', 'max:500'],
            'replace_path' => ['nullable', 'string', 'max:1000'],
            'attachable' => ['nullable', 'string', 'max:64'],
            'attachable_id' => ['nullable', 'integer', 'min:1'],
            'replace_attachment_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $destination = $validated['destination'] ?? 'file_manager';

        if ($destination === 'file_manager' && empty($validated['root'])) {
            return response()->json(['message' => __('A storage root is required.')], 422);
        }

        if ($destination === 'attachment' && (empty($validated['attachable']) || empty($validated['attachable_id']))) {
            return response()->json(['message' => __('Attachment target is required.')], 422);
        }

        if ($destination === 'disk') {
            $this->authorizePermission($request, 'storage.create');
        }

        try {
            $session = $this->uploads->initiate($request->user(), [
                'filename' => $validated['filename'],
                'size' => (int) $validated['size'],
                'mime' => $validated['mime'] ?? null,
                'checksum' => $validated['checksum'] ?? null,
                'chunk_bytes' => isset($validated['chunk_bytes']) ? (int) $validated['chunk_bytes'] : null,
                'destination' => $destination,
                'root' => $validated['root'] ?? 'private',
                'path' => $validated['path'] ?? '',
                'disk' => $validated['disk'] ?? null,
                'directory' => $validated['directory'] ?? null,
                'replace_path' => $validated['replace_path'] ?? null,
                'attachable' => $validated['attachable'] ?? null,
                'attachable_id' => isset($validated['attachable_id']) ? (int) $validated['attachable_id'] : null,
                'replace_attachment_id' => isset($validated['replace_attachment_id']) ? (int) $validated['replace_attachment_id'] : null,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 507);
        }

        return response()->json([
            'message' => __('Upload session created.'),
            'session' => $session->toClientArray(),
        ], 201);
    }

    public function chunk(Request $request, UploadSession $uploadSession): JsonResponse
    {
        abort_unless($request->user(), 403);
        $this->authorizeSessionOwner($request, $uploadSession);

        $validated = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
            'checksum' => ['nullable', 'string', 'size:64'],
            'chunk' => ['required', 'file'],
        ]);

        $maxChunk = (int) config('uploads.max_chunk_bytes');
        $size = (int) $request->file('chunk')?->getSize();

        if ($size <= 0 || $size > $maxChunk) {
            return response()->json([
                'message' => __('Chunk size is outside the allowed range.'),
            ], 422);
        }

        try {
            $session = $this->uploads->storeChunk(
                $uploadSession,
                (int) $validated['index'],
                $request->file('chunk'),
                $validated['checksum'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => __('Chunk stored.'),
            'session' => $session->toClientArray(),
        ]);
    }

    public function status(Request $request, UploadSession $uploadSession): JsonResponse
    {
        abort_unless($request->user(), 403);
        $this->authorizeSessionOwner($request, $uploadSession);

        $session = $this->uploads->status($uploadSession);

        return response()->json([
            'session' => $session->toClientArray(),
        ]);
    }

    public function complete(Request $request, UploadSession $uploadSession): JsonResponse
    {
        abort_unless($request->user(), 403);
        $this->authorizeSessionOwner($request, $uploadSession);

        try {
            $session = $this->uploads->complete($uploadSession);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: __('Could not complete the upload.'),
            ], 500);
        }

        return response()->json([
            'message' => __('Upload completed.'),
            'session' => $session->toClientArray(),
        ]);
    }

    public function abort(Request $request, UploadSession $uploadSession): JsonResponse
    {
        abort_unless($request->user(), 403);
        $this->authorizeSessionOwner($request, $uploadSession);

        try {
            $session = $this->uploads->abort($uploadSession);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => __('Upload aborted.'),
            'session' => $session->toClientArray(),
        ]);
    }

    protected function authorizeSessionOwner(Request $request, UploadSession $session): void
    {
        abort_unless(
            $request->user() && (int) $session->user_id === (int) $request->user()->id,
            403,
        );
    }
}
