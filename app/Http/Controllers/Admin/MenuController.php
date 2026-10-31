<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'menus.view');

        $search = trim((string) $request->query('search', ''));

        $menus = Menu::query()
            ->withCount('menu_items')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Menu $menu) => $this->serializeMenu($menu));

        return Inertia::render('resources/menus/Index', [
            'items' => $this->inertiaPagination($menus),
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'menus.create');

        return Inertia::render('resources/menus/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'menus.create');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menus', 'public');
        }

        $menu = Menu::query()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $path,
        ]);

        return redirect()
            ->route('menus.show', $menu)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, Menu $menu): Response
    {
        $this->authorizePermission($request, 'menus.view');

        $menu->loadCount('menu_items');

        return Inertia::render('resources/menus/Show', [
            'item' => $this->serializeMenu($menu),
        ]);
    }

    public function edit(Request $request, Menu $menu): Response
    {
        $this->authorizePermission($request, 'menus.edit');

        return Inertia::render('resources/menus/Edit', [
            'item' => $this->serializeMenu($menu),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizePermission($request, 'menus.edit');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        $path = $menu->image;

        if ($request->boolean('remove_image') && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }

        if ($request->hasFile('image')) {
            if ($menu->image) {
                Storage::disk('public')->delete($menu->image);
            }
            $path = $request->file('image')->store('menus', 'public');
        }

        $menu->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'image' => $path,
        ]);

        return redirect()
            ->route('menus.show', $menu)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Menu $menu): RedirectResponse
    {
        $this->authorizePermission($request, 'menus.delete');

        if ($menu->image) {
            Storage::disk('public')->delete($menu->image);
        }

        $menu->delete();

        return redirect()
            ->route('menus.index')
            ->with('success', __('Deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMenu(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'title' => $menu->title,
            'description' => $menu->description,
            'image' => $menu->image,
            'image_url' => $this->publicImageUrl($menu->image),
            'menu_items_count' => $menu->menu_items_count ?? null,
            'created_at' => $menu->created_at?->toIso8601String(),
            'updated_at' => $menu->updated_at?->toIso8601String(),
        ];
    }

    protected function publicImageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
