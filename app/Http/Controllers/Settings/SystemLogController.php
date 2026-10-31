<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Services\SystemLogReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SystemLogController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        protected SystemLogReader $reader,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'audit.view');

        $files = $this->reader->listFiles();
        $selectedFile = $request->string('file')->trim()->toString();

        if ($selectedFile === '' && $files !== []) {
            $selectedFile = $files[0]['name'];
        }

        if ($selectedFile !== '' && $this->reader->resolvePath($selectedFile) === null) {
            $selectedFile = $files[0]['name'] ?? '';
        }

        $filters = [
            'file' => $selectedFile !== '' ? $selectedFile : null,
            'level' => $request->string('level')->trim()->toString() ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
            'date_from' => $request->string('date_from')->trim()->toString() ?: null,
            'date_to' => $request->string('date_to')->trim()->toString() ?: null,
            'page' => max(1, $request->integer('page', 1)),
            'per_page' => $request->integer('per_page', 50),
        ];

        $result = $this->reader->query($filters);
        $totalBytes = collect($files)->sum('size');

        return Inertia::render('settings/SystemLogs/Index', [
            'files' => $files,
            'entries' => [
                'data' => $result['entries'],
                'meta' => $result['meta'],
                'links' => $result['links'],
            ],
            'scan' => $result['scan'],
            'levels' => SystemLogReader::LEVELS,
            'filters' => [
                'file' => $filters['file'],
                'level' => $filters['level'],
                'search' => $filters['search'],
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
                'per_page' => $filters['per_page'],
            ],
            'summary' => [
                'file_count' => count($files),
                'total_bytes' => $totalBytes,
                'latest_file' => $files[0]['name'] ?? null,
                'latest_modified_at' => $files[0]['modified_at'] ?? null,
            ],
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizePermission($request, 'audit.view');

        $filename = $request->string('file')->trim()->toString();
        $path = $this->reader->resolvePath($filename !== '' ? $filename : null);

        abort_unless($path !== null, 404);

        return response()->download($path, basename($path), [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'audit.view');

        $filename = $request->string('file')->trim()->toString();
        $path = $this->reader->resolvePath($filename !== '' ? $filename : null);

        abort_unless($path !== null, 404);

        File::delete($path);

        return redirect()
            ->route('settings.system-logs.index')
            ->with('success', __('Log file deleted.'));
    }
}
