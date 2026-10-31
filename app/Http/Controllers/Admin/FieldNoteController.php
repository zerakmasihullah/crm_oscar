<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Concerns\SharesOfflineInertiaProps;
use App\Http\Controllers\Controller;
use App\Models\FieldNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FieldNoteController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;
    use SharesOfflineInertiaProps;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'field_notes.view');

        $items = FieldNote::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('resources/field-notes/Index', [
            'items' => $this->inertiaPagination($items),
            ...$this->offlineInertiaProps('field_notes'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'field_notes.create');

        return Inertia::render('resources/field-notes/Create', [
            ...$this->offlineInertiaProps('field_notes'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'field_notes.create');

        $validated = $request->validate([
            'client_uuid' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        FieldNote::query()->create([
            'client_uuid' => $validated['client_uuid'] ?? (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'client_updated_at' => now(),
        ]);

        return redirect()->route('field-notes.index')->with('success', __('Created.'));
    }

    public function edit(Request $request, FieldNote $fieldNote): Response
    {
        $this->authorizePermission($request, 'field_notes.edit');
        abort_unless($fieldNote->user_id === $request->user()->id, 404);

        return Inertia::render('resources/field-notes/Edit', [
            'item' => $fieldNote,
            ...$this->offlineInertiaProps('field_notes'),
        ]);
    }

    public function update(Request $request, FieldNote $fieldNote): RedirectResponse
    {
        $this->authorizePermission($request, 'field_notes.edit');
        abort_unless($fieldNote->user_id === $request->user()->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $fieldNote->update([
            ...$validated,
            'client_updated_at' => now(),
        ]);

        return back()->with('success', __('Updated.'));
    }

    public function destroy(Request $request, FieldNote $fieldNote): RedirectResponse
    {
        $this->authorizePermission($request, 'field_notes.delete');
        abort_unless($fieldNote->user_id === $request->user()->id, 404);

        $fieldNote->delete();

        return back()->with('success', __('Deleted.'));
    }
}
