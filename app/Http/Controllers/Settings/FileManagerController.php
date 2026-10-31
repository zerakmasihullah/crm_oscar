<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Services\FileManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileManagerController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        private FileManagerService $files,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'storage.view');

        $validated = $request->validate([
            'root' => ['nullable', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['nullable', 'string', 'max:1000'],
            'search' => ['nullable', 'string', 'max:120'],
            'preview' => ['nullable', 'string', 'max:1000'],
        ]);

        $root = $validated['root'] ?? 'public';
        $path = $validated['path'] ?? '';
        $search = $validated['search'] ?? null;
        $previewPath = $validated['preview'] ?? null;

        $this->files->ensureRootExists($root);

        try {
            $listing = $this->files->list($root, $path, $search);
        } catch (InvalidArgumentException) {
            $listing = $this->files->list($root, '', $search);
        }

        $preview = null;

        if (is_string($previewPath) && $previewPath !== '') {
            try {
                $preview = $this->files->inspect($root, $previewPath);
            } catch (InvalidArgumentException|RuntimeException) {
                $preview = null;
            }
        }

        return Inertia::render('settings/FileManager/Index', [
            'roots' => $this->files->roots(),
            'listing' => $listing,
            'preview' => $preview,
            'filters' => [
                'root' => $listing['root'],
                'path' => $listing['path'],
                'search' => $search,
                'preview' => $preview['path'] ?? null,
            ],
            'permissions' => [
                'create' => $request->user()?->can('storage.create') ?? false,
                'edit' => $request->user()?->can('storage.edit') ?? false,
                'delete' => $request->user()?->can('storage.delete') ?? false,
            ],
            'limits' => [
                'max_upload_bytes' => (int) config('uploads.max_bytes'),
                'simple_max_upload_bytes' => FileManagerService::MAX_UPLOAD_BYTES,
                'chunk_bytes' => (int) config('uploads.chunk_bytes'),
                'concurrency' => (int) config('uploads.concurrency'),
                'require_chunk_checksum' => (bool) config('uploads.require_chunk_checksum'),
                'require_file_checksum' => (bool) config('uploads.require_file_checksum'),
                'max_preview_bytes' => FileManagerService::MAX_PREVIEW_BYTES,
            ],
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'storage.create');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['nullable', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:180'],
        ]);

        try {
            $this->files->createDirectory(
                $validated['root'],
                $validated['path'] ?? '',
                $validated['name'],
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.file-manager.index', [
                'root' => $validated['root'],
                'path' => $validated['path'] ?? '',
            ])
            ->with('success', __('Folder created.'));
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'storage.create');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['nullable', 'string', 'max:1000'],
            'file' => [
                'required',
                'file',
                'max:'.(int) (FileManagerService::MAX_UPLOAD_BYTES / 1024),
            ],
        ]);

        try {
            $this->files->upload(
                $validated['root'],
                $validated['path'] ?? '',
                $validated['file'],
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.file-manager.index', [
                'root' => $validated['root'],
                'path' => $validated['path'] ?? '',
            ])
            ->with('success', __('File uploaded.'));
    }

    public function rename(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'storage.edit');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['required', 'string', 'max:1000'],
            'name' => ['required', 'string', 'max:180'],
            'current_path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->files->rename(
                $validated['root'],
                $validated['path'],
                $validated['name'],
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.file-manager.index', [
                'root' => $validated['root'],
                'path' => $validated['current_path'] ?? '',
            ])
            ->with('success', __('Item renamed.'));
    }

    public function move(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'storage.edit');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['required', 'string', 'max:1000'],
            'destination' => ['nullable', 'string', 'max:1000'],
            'current_path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->files->move(
                $validated['root'],
                $validated['path'],
                $validated['destination'] ?? '',
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['destination' => $e->getMessage()]);
        }

        return redirect()
            ->route('settings.file-manager.index', [
                'root' => $validated['root'],
                'path' => $validated['current_path'] ?? '',
            ])
            ->with('success', __('Item moved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'storage.delete');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['required', 'string', 'max:1000'],
            'current_path' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->files->delete($validated['root'], $validated['path']);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('settings.file-manager.index', [
                'root' => $validated['root'],
                'path' => $validated['current_path'] ?? '',
            ])
            ->with('success', __('Item moved to trash.'));
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizePermission($request, 'storage.view');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $absolute = $this->files->resolveDownloadPath($validated['root'], $validated['path']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return response()->download($absolute, basename($absolute));
    }

    public function preview(Request $request): BinaryFileResponse
    {
        $this->authorizePermission($request, 'storage.view');

        $validated = $request->validate([
            'root' => ['required', 'string', Rule::in(array_keys(FileManagerService::ROOTS))],
            'path' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $absolute = $this->files->resolveDownloadPath($validated['root'], $validated['path']);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $mime = mime_content_type($absolute) ?: 'application/octet-stream';

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($absolute).'"',
        ]);
    }
}
