<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Services\TrashRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TrashController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request, TrashRegistry $trash): Response
    {
        $this->authorizePermission($request, 'trash.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        $search = $validated['search'] ?? null;
        $type = $validated['type'] ?? null;

        $items = $trash->collect($type, $search);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 20;
        $slice = $items->forPage($page, $perPage)->values();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('settings/Trash/Index', [
            'items' => $this->inertiaPagination($paginator),
            'types' => collect($trash->types())->map(fn (array $t) => [
                'key' => $t['key'],
                'label' => $t['label'],
            ])->values(),
            'summary' => $trash->summary(),
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'retention_days' => (int) config('trash.retention_days', 60),
        ]);
    }

    public function restore(Request $request, TrashRegistry $trash): RedirectResponse
    {
        $this->authorizePermission($request, 'trash.restore');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_column($trash->types(), 'key'))],
            'id' => ['required'],
        ]);

        $trash->restore($validated['type'], $validated['id']);

        return back()->with('success', __('Item restored from trash.'));
    }

    public function forceDestroy(Request $request, TrashRegistry $trash): RedirectResponse
    {
        $this->authorizePermission($request, 'trash.delete');

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_column($trash->types(), 'key'))],
            'id' => ['required'],
        ]);

        $trash->forceDestroy($validated['type'], $validated['id']);

        return back()->with('success', __('Item permanently deleted.'));
    }

    public function bulkRestore(Request $request, TrashRegistry $trash): RedirectResponse
    {
        $this->authorizePermission($request, 'trash.restore');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(array_column($trash->types(), 'key'))],
            'items.*.id' => ['required'],
        ]);

        foreach ($validated['items'] as $item) {
            $trash->restore($item['type'], $item['id']);
        }

        return back()->with('success', __('Items restored from trash.'));
    }

    public function bulkForceDestroy(Request $request, TrashRegistry $trash): RedirectResponse
    {
        $this->authorizePermission($request, 'trash.delete');

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', Rule::in(array_column($trash->types(), 'key'))],
            'items.*.id' => ['required'],
        ]);

        foreach ($validated['items'] as $item) {
            $trash->forceDestroy($item['type'], $item['id']);
        }

        return back()->with('success', __('Items permanently deleted.'));
    }

    public function empty(Request $request, TrashRegistry $trash): RedirectResponse
    {
        $this->authorizePermission($request, 'trash.delete');

        $count = $trash->emptyAll();

        return back()->with('success', __('Permanently deleted :count item(s) from trash.', ['count' => $count]));
    }
}
