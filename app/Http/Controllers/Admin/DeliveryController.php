<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'deliveries.view');

        $delivery = Delivery::query()->first();

        if ($delivery) {
            return redirect()->route('deliveries.edit', $delivery);
        }

        return redirect()->route('deliveries.create');
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorizePermission($request, 'deliveries.create');

        $existing = Delivery::query()->first();
        if ($existing) {
            return redirect()->route('deliveries.edit', $existing);
        }

        return Inertia::render('resources/deliveries/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'deliveries.create');

        if (Delivery::query()->exists()) {
            return redirect()
                ->route('deliveries.edit', Delivery::query()->first())
                ->with('error', __('Delivery settings already exist.'));
        }

        $delivery = Delivery::query()->create($this->validated($request));

        return redirect()
            ->route('deliveries.edit', $delivery)
            ->with('success', __('Created.'));
    }

    public function edit(Request $request, Delivery $delivery): Response
    {
        $this->authorizePermission($request, 'deliveries.edit');

        return Inertia::render('resources/deliveries/Edit', [
            'item' => $this->serialize($delivery),
        ]);
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->authorizePermission($request, 'deliveries.edit');

        $delivery->update($this->validated($request));

        return back()->with('success', __('Updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'delivery_price' => ['nullable', 'numeric', 'min:0'],
            'free_delivery_limit' => ['nullable', 'numeric', 'min:0'],
            'service_fees' => ['nullable', 'numeric', 'min:0'],
            'discount_for_all' => ['nullable', 'numeric', 'min:0'],
            'min_order' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'delivery_price' => $delivery->delivery_price,
            'free_delivery_limit' => $delivery->free_delivery_limit,
            'service_fees' => $delivery->service_fees,
            'discount_for_all' => $delivery->discount_for_all,
            'min_order' => $delivery->min_order,
            'updated_at' => $delivery->updated_at?->toIso8601String(),
        ];
    }
}
