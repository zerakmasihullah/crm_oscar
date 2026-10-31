<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'discounts.view');

        $search = trim((string) $request->query('search', ''));

        $items = Discount::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->filled('discount_type'), function ($query) use ($request) {
                $query->where('discount_type', $request->query('discount_type'));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Discount $discount) => $this->serialize($discount));

        return Inertia::render('resources/discounts/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'is_active' => $request->query('is_active'),
                'discount_type' => $request->query('discount_type'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'discounts.create');

        return Inertia::render('resources/discounts/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'discounts.create');

        $validated = $this->validated($request);

        $discount = Discount::query()->create($validated);

        return redirect()
            ->route('discounts.show', $discount)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, Discount $discount): Response
    {
        $this->authorizePermission($request, 'discounts.view');

        return Inertia::render('resources/discounts/Show', [
            'item' => $this->serialize($discount),
        ]);
    }

    public function edit(Request $request, Discount $discount): Response
    {
        $this->authorizePermission($request, 'discounts.edit');

        return Inertia::render('resources/discounts/Edit', [
            'item' => $this->serialize($discount),
        ]);
    }

    public function update(Request $request, Discount $discount): RedirectResponse
    {
        $this->authorizePermission($request, 'discounts.edit');

        $discount->update($this->validated($request));

        return redirect()
            ->route('discounts.show', $discount)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Discount $discount): RedirectResponse
    {
        $this->authorizePermission($request, 'discounts.delete');

        $discount->delete();

        return redirect()
            ->route('discounts.index')
            ->with('success', __('Deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', 'in:percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'minimum_purchase_amount' => ['nullable', 'numeric', 'min:0'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['days_of_week'] = array_values($validated['days_of_week'] ?? []);
        $validated['minimum_purchase_amount'] = $validated['minimum_purchase_amount'] ?? null;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Discount $discount): array
    {
        return [
            'id' => $discount->id,
            'name' => $discount->name,
            'discount_type' => $discount->discount_type,
            'discount_value' => $discount->discount_value,
            'minimum_purchase_amount' => $discount->minimum_purchase_amount,
            'days_of_week' => $discount->days_of_week ?? [],
            'is_active' => (bool) $discount->is_active,
            'created_at' => $discount->created_at?->toIso8601String(),
            'updated_at' => $discount->updated_at?->toIso8601String(),
        ];
    }
}
