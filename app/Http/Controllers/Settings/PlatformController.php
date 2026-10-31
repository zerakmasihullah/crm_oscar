<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Platform;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlatformController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'platforms.view');

        $search = trim((string) $request->query('search', ''));

        $items = Platform::query()
            ->withCount('revenues')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Platform $platform) => $this->serialize($platform));

        return Inertia::render('settings/Platforms/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'platforms.create');

        return Inertia::render('settings/Platforms/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'platforms.create');

        Platform::query()->create($this->validated($request));

        return redirect()
            ->route('settings.platforms.index')
            ->with('success', __('Created.'));
    }

    public function edit(Request $request, Platform $platform): Response
    {
        $this->authorizePermission($request, 'platforms.edit');

        return Inertia::render('settings/Platforms/Edit', [
            'item' => $this->serialize($platform),
        ]);
    }

    public function update(Request $request, Platform $platform): RedirectResponse
    {
        $this->authorizePermission($request, 'platforms.edit');

        $platform->update($this->validated($request, $platform));

        return redirect()
            ->route('settings.platforms.index')
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Platform $platform): RedirectResponse
    {
        $this->authorizePermission($request, 'platforms.delete');

        $platform->delete();

        return redirect()
            ->route('settings.platforms.index')
            ->with('success', __('Deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?Platform $platform = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:platforms,name'.($platform ? ','.$platform->id : ''),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Platform $platform): array
    {
        return [
            'id' => $platform->id,
            'name' => $platform->name,
            'is_active' => (bool) $platform->is_active,
            'revenues_count' => (int) ($platform->revenues_count ?? 0),
        ];
    }
}
