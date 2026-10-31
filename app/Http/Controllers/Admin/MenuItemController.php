<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuAttachment;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Size;
use App\Models\Topping;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MenuItemController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'menu_items.view');

        $search = trim((string) $request->query('search', ''));
        $menuId = $request->query('menu_id');
        $status = $request->query('status');
        $hasSizes = $request->query('has_sizes');

        $items = MenuItem::query()
            ->select('menu_items.*')
            ->with('menu:id,title')
            ->withSum('order_items as total_sold', 'quantity')
            ->addSelect([
                'total_revenue' => OrderItem::query()
                    ->selectRaw('COALESCE(SUM(price * quantity), 0)')
                    ->whereColumn('menu_item_id', 'menu_items.id'),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when(filled($menuId), fn ($query) => $query->where('menu_id', $menuId))
            ->when($status === '1' || $status === '0', fn ($query) => $query->where('status', (bool) (int) $status))
            ->when($hasSizes === '1', fn ($query) => $query->has('sizes'))
            ->when($hasSizes === '0', fn ($query) => $query->doesntHave('sizes'))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MenuItem $item) => $this->serializeListItem($item));

        return Inertia::render('resources/menu-items/Index', [
            'items' => $this->inertiaPagination($items),
            'menus' => Menu::query()->orderBy('title')->get(['id', 'title']),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'menu_id' => filled($menuId) ? (string) $menuId : null,
                'status' => in_array($status, ['0', '1'], true) ? (string) $status : null,
                'has_sizes' => in_array($hasSizes, ['0', '1'], true) ? (string) $hasSizes : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'menu_items.create');

        return Inertia::render('resources/menu-items/Create', [
            'menus' => Menu::query()->orderBy('title')->get(['id', 'title']),
            'defaults' => [
                'menu_id' => $request->query('menu_id'),
                'status' => true,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'menu_items.create');

        $validated = $this->validatePayload($request);

        $path = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('menu_item', 'public');
        }

        $menuItem = DB::transaction(function () use ($validated, $path) {
            $menuItem = MenuItem::query()->create([
                'menu_id' => $validated['menu_id'],
                'title' => $validated['title'],
                'price' => $validated['price'] ?? null,
                'description' => $validated['description'] ?? null,
                'image' => $path,
                'status' => $validated['status'] ?? true,
            ]);

            $this->syncSizes($menuItem, $validated['sizes'] ?? []);
            $this->syncToppings($menuItem, $validated['toppings'] ?? []);

            return $menuItem;
        });

        return redirect()
            ->route('menu-items.show', $menuItem)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, MenuItem $menuItem): Response
    {
        $this->authorizePermission($request, 'menu_items.view');

        $menuItem->load([
            'menu:id,title',
            'sizes',
            'toppings.attachments',
        ]);

        $sold = (int) OrderItem::query()
            ->where('menu_item_id', $menuItem->id)
            ->sum('quantity');

        $revenue = (float) (OrderItem::query()
            ->where('menu_item_id', $menuItem->id)
            ->selectRaw('COALESCE(SUM(price * quantity), 0) as total')
            ->value('total') ?? 0);

        return Inertia::render('resources/menu-items/Show', [
            'item' => $this->serializeDetailItem($menuItem, $sold, $revenue),
        ]);
    }

    public function edit(Request $request, MenuItem $menuItem): Response
    {
        $this->authorizePermission($request, 'menu_items.edit');

        $menuItem->load([
            'menu:id,title',
            'sizes',
            'toppings.attachments',
        ]);

        return Inertia::render('resources/menu-items/Edit', [
            'item' => $this->serializeDetailItem($menuItem),
            'menus' => Menu::query()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function update(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizePermission($request, 'menu_items.edit');

        $validated = $this->validatePayload($request);

        $path = $menuItem->image;

        if ($request->boolean('remove_image') && $path) {
            Storage::disk('public')->delete($path);
            $path = null;
        }

        if ($request->hasFile('image')) {
            if ($menuItem->image) {
                Storage::disk('public')->delete($menuItem->image);
            }
            $path = $request->file('image')->store('menu_item', 'public');
        }

        DB::transaction(function () use ($menuItem, $validated, $path) {
            $menuItem->update([
                'menu_id' => $validated['menu_id'],
                'title' => $validated['title'],
                'price' => $validated['price'] ?? null,
                'description' => $validated['description'] ?? null,
                'image' => $path,
                'status' => $validated['status'] ?? true,
            ]);

            $this->syncSizes($menuItem, $validated['sizes'] ?? []);
            $this->syncToppings($menuItem, $validated['toppings'] ?? []);
        });

        return redirect()
            ->route('menu-items.show', $menuItem)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizePermission($request, 'menu_items.delete');

        if ($menuItem->image) {
            Storage::disk('public')->delete($menuItem->image);
        }

        $menuItem->delete();

        return redirect()
            ->route('menu-items.index')
            ->with('success', __('Deleted.'));
    }

    public function toggleStatus(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizePermission($request, 'menu_items.edit');

        $menuItem->update([
            'status' => ! $menuItem->status,
        ]);

        return back()->with('success', __('Status Updated'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePayload(Request $request): array
    {
        return $request->validate([
            'menu_id' => ['required', 'integer', Rule::exists('menus', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
            'sizes' => ['nullable', 'array'],
            'sizes.*.id' => ['nullable', 'integer'],
            'sizes.*.size' => ['required_with:sizes', 'string', 'max:255'],
            'sizes.*.price' => ['required_with:sizes', 'numeric', 'min:0'],
            'toppings' => ['nullable', 'array'],
            'toppings.*.id' => ['nullable', 'integer'],
            'toppings.*.name' => ['required_with:toppings', 'string', 'max:255'],
            'toppings.*.required' => ['nullable', 'integer', 'min:0'],
            'toppings.*.is_optional' => ['nullable', 'boolean'],
            'toppings.*.attachments' => ['nullable', 'array'],
            'toppings.*.attachments.*.id' => ['nullable', 'integer'],
            'toppings.*.attachments.*.name' => ['required_with:toppings.*.attachments', 'string', 'max:255'],
            'toppings.*.attachments.*.price' => ['required_with:toppings.*.attachments', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sizes
     */
    protected function syncSizes(MenuItem $menuItem, array $sizes): void
    {
        $keepIds = [];

        foreach ($sizes as $row) {
            $size = isset($row['id'])
                ? Size::query()
                    ->where('menu_item_id', $menuItem->id)
                    ->whereKey($row['id'])
                    ->first()
                : null;

            if ($size) {
                $size->update([
                    'size' => $row['size'],
                    'price' => $row['price'],
                ]);
            } else {
                $size = $menuItem->sizes()->create([
                    'size' => $row['size'],
                    'price' => $row['price'],
                ]);
            }

            $keepIds[] = $size->id;
        }

        $query = $menuItem->sizes();
        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $toppings
     */
    protected function syncToppings(MenuItem $menuItem, array $toppings): void
    {
        $keepIds = [];

        foreach ($toppings as $row) {
            $topping = isset($row['id'])
                ? Topping::query()
                    ->where('menu_item_id', $menuItem->id)
                    ->whereKey($row['id'])
                    ->first()
                : null;

            $payload = [
                'name' => $row['name'],
                'required' => (int) ($row['required'] ?? 0),
                'is_optional' => (bool) ($row['is_optional'] ?? true),
            ];

            if ($topping) {
                $topping->update($payload);
            } else {
                $topping = $menuItem->toppings()->create($payload);
            }

            $this->syncAttachments($topping, $row['attachments'] ?? []);
            $keepIds[] = $topping->id;
        }

        $toDelete = $menuItem->toppings()
            ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
            ->get();

        foreach ($toDelete as $topping) {
            $topping->attachments()->delete();
            $topping->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    protected function syncAttachments(Topping $topping, array $attachments): void
    {
        $keepIds = [];

        foreach ($attachments as $row) {
            $attachment = isset($row['id'])
                ? MenuAttachment::query()
                    ->where('topping_id', $topping->id)
                    ->whereKey($row['id'])
                    ->first()
                : null;

            $payload = [
                'name' => $row['name'],
                'price' => $row['price'],
            ];

            if ($attachment) {
                $attachment->update($payload);
            } else {
                $attachment = $topping->attachments()->create($payload);
            }

            $keepIds[] = $attachment->id;
        }

        $query = $topping->attachments();
        if ($keepIds !== []) {
            $query->whereNotIn('id', $keepIds);
        }
        $query->delete();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeListItem(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'price' => $item->price !== null ? (float) $item->price : null,
            'status' => (bool) $item->status,
            'image' => $item->image,
            'image_url' => $this->publicImageUrl($item->image),
            'description' => $item->description,
            'menu_id' => $item->menu_id,
            'menu' => $item->menu
                ? ['id' => $item->menu->id, 'title' => $item->menu->title]
                : null,
            'total_sold' => (int) ($item->total_sold ?? 0),
            'total_revenue' => (float) ($item->total_revenue ?? 0),
            'created_at' => $item->created_at?->toIso8601String(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeDetailItem(MenuItem $item, ?int $sold = null, ?float $revenue = null): array
    {
        return [
            ...$this->serializeListItem($item),
            'total_sold' => $sold ?? (int) ($item->total_sold ?? 0),
            'total_revenue' => $revenue ?? (float) ($item->total_revenue ?? 0),
            'sizes' => $item->relationLoaded('sizes')
                ? $item->sizes->map(fn (Size $size) => [
                    'id' => $size->id,
                    'size' => $size->size,
                    'price' => (float) $size->price,
                ])->values()->all()
                : [],
            'toppings' => $item->relationLoaded('toppings')
                ? $item->toppings->map(fn (Topping $topping) => [
                    'id' => $topping->id,
                    'name' => $topping->name,
                    'required' => (int) $topping->required,
                    'is_optional' => (bool) $topping->is_optional,
                    'attachments' => $topping->relationLoaded('attachments')
                        ? $topping->attachments->map(fn (MenuAttachment $attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'price' => (float) $attachment->price,
                        ])->values()->all()
                        : [],
                ])->values()->all()
                : [],
        ];
    }

    protected function publicImageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        if (str_starts_with($path, '/storage/') || str_starts_with($path, '/brand/')) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
