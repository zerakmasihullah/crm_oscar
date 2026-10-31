<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    use AuthorizesMisPermissions;

    public function __construct(
        private BackupService $backups,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'backups.view');

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', Rule::in(['database', 'files', 'full'])],
            'trigger' => ['nullable', 'string', Rule::in(['manual', 'scheduled', 'pre_restore'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'completed', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = BackupRecord::query()->with('creator:id,name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('uuid', 'like', "%{$search}%")
                    ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['trigger'])) {
            $query->where('trigger', $filters['trigger']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $summary = [
            'total' => BackupRecord::query()->where('status', 'completed')->count(),
            'database' => BackupRecord::query()->where('status', 'completed')->where('type', 'database')->count(),
            'files' => BackupRecord::query()->where('status', 'completed')->where('type', 'files')->count(),
            'full' => BackupRecord::query()->where('status', 'completed')->where('type', 'full')->count(),
        ];

        return Inertia::render('settings/Backups/Index', [
            'backups' => $query
                ->latest()
                ->paginate(20)
                ->withQueryString()
                ->through(fn (BackupRecord $backup) => [
                    'id' => $backup->id,
                    'uuid' => $backup->uuid,
                    'name' => $backup->name,
                    'type' => $backup->type,
                    'trigger' => $backup->trigger,
                    'retention_tier' => $backup->retention_tier,
                    'status' => $backup->status,
                    'size' => $backup->size,
                    'error' => $backup->error,
                    'created_at' => $backup->created_at?->toIso8601String(),
                    'creator' => $backup->creator
                        ? ['id' => $backup->creator->id, 'name' => $backup->creator->name]
                        : null,
                    'restorable' => $backup->isCompleted() ? $backup->restorableLayers() : [],
                ]),
            'auto' => $this->backups->autoSettings(),
            'summary' => $summary,
            'filters' => [
                'search' => $filters['search'] ?? null,
                'type' => $filters['type'] ?? null,
                'trigger' => $filters['trigger'] ?? null,
                'status' => $filters['status'] ?? null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'backups.run');

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:database,files,full'],
        ]);

        try {
            Bus::dispatch(new RunBackupJob(
                $validated['type'],
                'manual',
                $request->user()?->id,
            ));
        } catch (Throwable $e) {
            return back()->with('error', __('Backup failed: :error', ['error' => $e->getMessage()]));
        }

        $queued = config('queue.default') !== 'sync';

        return back()->with(
            'success',
            $queued ? 'Backup queued.' : 'Backup created.',
        );
    }

    public function updateAuto(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'backups.run');

        $validated = $request->validate([
            'enabled' => ['required'],
            'type' => ['required', 'string', 'in:database,files,full'],
        ]);

        $enabled = filter_var($validated['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($enabled === null) {
            throw ValidationException::withMessages([
                'enabled' => __('The enabled field must be true or false.'),
            ]);
        }

        $this->backups->updateAutoSettings(
            $enabled,
            $validated['type'],
        );

        return back()->with('success', __('Auto backup settings saved.'));
    }

    public function restore(Request $request, BackupRecord $backup): RedirectResponse
    {
        $this->authorizePermission($request, 'backups.restore');

        $validated = $request->validate([
            'target' => ['required', 'string', 'in:database,files,full'],
        ]);

        abort_unless($backup->status === 'completed', 404);

        try {
            $this->backups->restore($backup, $validated['target'], $request->user()?->id);
        } catch (Throwable $e) {
            return back()->with('error', __('Restore failed: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('success', __('Restore completed. A safety backup of the previous state was kept.'));
    }

    public function download(Request $request, BackupRecord $backup): BinaryFileResponse
    {
        $this->authorizePermission($request, 'backups.download');

        abort_unless($backup->status === 'completed', 404);

        return response()->download(
            $this->backups->downloadPath($backup),
            $backup->name.'.zip',
        );
    }

    public function destroy(Request $request, BackupRecord $backup): RedirectResponse
    {
        $this->authorizePermission($request, 'backups.delete');

        $this->backups->delete($backup);

        return back()->with('success', __('Backup deleted.'));
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'backups.delete');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $backups = BackupRecord::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($backups as $backup) {
            $this->backups->delete($backup);
        }

        return back()->with('success', __('Backups deleted.'));
    }
}
