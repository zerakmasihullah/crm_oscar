<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FcmNotificationHelper;
use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Mail\OrderRejection;
use App\Models\Delivery;
use App\Models\Menu;
use App\Models\MenuAttachment;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\OrderPricingService;
use App\Support\OrderStatuses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function __construct(
        protected OrderPricingService $pricing,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'orders.view');

        $tab = $request->string('tab')->toString() ?: 'all';
        $query = Order::query()
            ->with([
                'customer:id,name,email,phone_number',
                'deliveryman:id,name',
                'order_items.menu_item:id,title',
                'order_items.size:id,size',
            ])
            ->withCount('order_items')
            ->latest();

        match ($tab) {
            'pending' => $query->where('status', OrderStatuses::PENDING),
            'delivery' => $query->where('type', 1),
            'collection' => $query->where('type', 2),
            default => null,
        };

        if ($request->filled('status')) {
            $query->where('status', (int) $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('type', (int) $request->input('type'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('deliveryman_id')) {
            $query->where('deliveryman_id', (int) $request->input('deliveryman_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('total_min')) {
            $query->where('total_price', '>=', (float) $request->input('total_min'));
        }

        if ($request->filled('total_max')) {
            $query->where('total_price', '<=', (float) $request->input('total_max'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('phone_number', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        }

        $items = $query->paginate(25)->withQueryString()->through(function (Order $order) {
            return [
                ...$order->toArray(),
                'items_summary' => $order->order_items->map(fn (OrderItem $item) => [
                    'quantity' => max((int) $item->quantity, 1),
                    'title' => $item->menu_item?->title ?? __('Item'),
                    'size' => $item->size?->size,
                ])->values()->all(),
            ];
        });

        return Inertia::render('resources/orders/Index', [
            'items' => $this->inertiaPagination($items),
            'filters' => [
                'tab' => $tab,
                'search' => $request->input('search'),
                'status' => $request->input('status'),
                'type' => $request->input('type'),
                'payment_method' => $request->input('payment_method'),
                'deliveryman_id' => $request->input('deliveryman_id'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'total_min' => $request->input('total_min'),
                'total_max' => $request->input('total_max'),
            ],
            'statusOptions' => OrderStatuses::labels(),
            'typeOptions' => OrderStatuses::typeLabels(),
            'deliverymen' => User::query()
                ->where('role', User::ROLE_DELIVERY_MAN)
                ->orderBy('name')
                ->get(['id', 'name']),
            'counts' => [
                'all' => Order::query()->count(),
                'pending' => Order::query()->where('status', OrderStatuses::PENDING)->count(),
                'delivery' => Order::query()->where('type', 1)->count(),
                'collection' => Order::query()->where('type', 2)->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'orders.create');

        return Inertia::render('resources/orders/Create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.create');

        $validated = $this->validateOrder($request);

        if ($toppingError = $this->validateOrderItemToppings($validated['order_items'])) {
            return back()->withErrors($toppingError)->withInput();
        }

        // Ensure pricing uses server-calculated unit prices (base + toppings).
        $pricedItems = array_map(function (array $item): array {
            $item['price'] = $this->lineUnitPrice($item);

            return $item;
        }, $validated['order_items']);

        $pricing = $this->pricing->calculate(
            $pricedItems,
            (int) $validated['type'],
            (float) ($validated['discount'] ?? 0),
        );

        $order = DB::transaction(function () use ($validated, $pricing) {
            $order = Order::query()->create([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => (int) ($validated['status'] ?? OrderStatuses::PENDING),
                'type' => (int) $validated['type'],
                'discount' => (float) ($validated['discount'] ?? 0),
                'subtotal' => $pricing['subtotal'],
                'total_price' => $pricing['total'],
                'payment_method' => $validated['payment_method'],
                'deliveryman_id' => $validated['deliveryman_id'] ?? null,
                'description' => Order::withSource($validated['description'] ?? null, 'admin'),
            ]);

            foreach ($validated['order_items'] as $item) {
                $unit = $this->lineUnitPrice($item);
                $orderItem = OrderItem::query()->create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['menu_item_id'],
                    'size_id' => $item['size_id'] ?? null,
                    'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                    'price' => $unit,
                ]);

                $this->syncOrderItemToppings($orderItem, $item['toppings'] ?? []);
            }

            return $order;
        });

        return redirect()
            ->route('orders.create')
            ->with('success', __('Order created.'))
            ->with('print_order_id', $order->id);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorizePermission($request, 'orders.view');

        $order->load([
            'customer:id,name,email,phone_number',
            'deliveryman:id,name',
            'order_items.menu_item.menu',
            'order_items.menu_item.toppings.attachments',
            'order_items.size',
            'order_items.orderAttachments.attachment',
            'order_items.orderAttachments.topping',
        ]);

        $stripeStatus = null;
        if ($order->payment_id) {
            try {
                $stripeStatus = $order->getStripePaymentStatus();
            } catch (\Throwable) {
                $stripeStatus = null;
            }
        }

        return Inertia::render('resources/orders/Show', [
            'item' => [
                'id' => $order->id,
                'status' => (int) $order->status,
                'status_label' => OrderStatuses::label($order->status),
                'type' => (int) $order->type,
                'type_label' => OrderStatuses::typeLabel($order->type),
                'source_label' => $order->source_label,
                'notes_text' => $order->notes_text,
                'customer_name' => $order->customer_name,
                'phone_number' => $order->phone_number,
                'address' => $order->address,
                'area_code' => $order->area_code,
                'payment_method' => $order->payment_method,
                'payment_id' => $order->payment_id,
                'stripe_status' => $stripeStatus,
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount,
                'total_price' => (float) $order->total_price,
                'latitude' => $order->latitude,
                'longitude' => $order->longitude,
                'created_at' => $order->created_at?->toIso8601String(),
                'delivery_datetime' => $order->delivery_datetime?->toIso8601String(),
                'customer' => $order->customer
                    ? $order->customer->only(['id', 'name', 'email', 'phone_number'])
                    : null,
                'deliveryman' => $order->deliveryman
                    ? $order->deliveryman->only(['id', 'name'])
                    : null,
                'line_items' => $order->order_items->map(function (OrderItem $item) {
                    $qty = max((int) $item->quantity, 1);
                    $unit = (float) $item->price;

                    return [
                        'id' => $item->id,
                        'menu_item_id' => $item->menu_item_id,
                        'title' => $item->menu_item?->title ?? __('Item'),
                        'size' => $item->size?->size,
                        'quantity' => $qty,
                        'unit_price' => $unit,
                        'line_total' => $unit * $qty,
                        'choices' => $item->orderAttachments
                            ->map(fn (OrderAttachment $a) => [
                                'name' => $a->attachment?->name,
                                'quantity' => max((int) $a->quantity, 1),
                                'price' => (float) ($a->attachment?->price ?? 0),
                            ])
                            ->filter(fn (array $row) => filled($row['name']))
                            ->values()
                            ->all(),
                    ];
                })->values()->all(),
            ],
            'statusOptions' => OrderStatuses::labels(),
        ]);
    }

    public function edit(Request $request, Order $order): Response
    {
        $this->authorizePermission($request, 'orders.edit');

        $order->load(['order_items.menu_item', 'order_items.size']);

        return Inertia::render('resources/orders/Edit', [
            ...$this->formOptions(),
            'item' => [
                ...$order->only([
                    'id', 'customer_id', 'customer_name', 'phone_number', 'address',
                    'status', 'type', 'discount', 'subtotal', 'total_price',
                    'payment_method', 'deliveryman_id', 'description',
                ]),
                'notes_text' => $order->notes_text,
                'order_items' => $order->order_items->map(fn (OrderItem $item) => [
                    'menu_id' => $item->menu_item?->menu_id,
                    'menu_item_id' => $item->menu_item_id,
                    'size_id' => $item->size_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                ])->values()->all(),
            ],
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.edit');

        $validated = $this->validateOrder($request);
        $pricing = $this->pricing->calculate(
            $validated['order_items'],
            (int) $validated['type'],
            (float) ($validated['discount'] ?? 0),
        );

        DB::transaction(function () use ($order, $validated, $pricing) {
            $source = Order::readSource($order->description) ?? 'admin';

            $order->update([
                'customer_id' => $validated['customer_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'status' => (int) ($validated['status'] ?? $order->status),
                'type' => (int) $validated['type'],
                'discount' => (float) ($validated['discount'] ?? 0),
                'subtotal' => $pricing['subtotal'],
                'total_price' => $pricing['total'],
                'payment_method' => $validated['payment_method'],
                'deliveryman_id' => $validated['deliveryman_id'] ?? null,
                'description' => Order::withSource($validated['description'] ?? null, $source),
            ]);

            $order->order_items()->delete();

            foreach ($validated['order_items'] as $item) {
                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['menu_item_id'],
                    'size_id' => $item['size_id'] ?? null,
                    'quantity' => max((int) ($item['quantity'] ?? 1), 1),
                    'price' => $this->unitPrice($item),
                ]);
            }
        });

        return redirect()->route('orders.show', $order)->with('success', __('Order updated.'));
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.delete');
        $order->order_items()->delete();
        $order->delete();

        return redirect()->route('orders.index')->with('success', __('Order deleted.'));
    }

    public function accept(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.accept');

        if ((int) $order->status !== OrderStatuses::PENDING) {
            return back()->with('error', __('Only pending orders can be accepted.'));
        }

        $order->update(['status' => OrderStatuses::ACCEPTED]);
        $order->load('customer');

        if ($order->customer) {
            try {
                FcmNotificationHelper::sendOrderNotification($order->customer, $order->id, OrderStatuses::ACCEPTED);
            } catch (\Throwable $e) {
                Log::error('Failed to send FCM for order accept', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        return back()->with('success', __('Order accepted.'));
    }

    public function reject(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.accept');

        if ((int) $order->status !== OrderStatuses::PENDING) {
            return back()->with('error', __('Only pending orders can be rejected.'));
        }

        $order->update(['status' => OrderStatuses::REJECTED]);
        $order->load(['customer', 'order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);

        try {
            if ($order->customer?->email) {
                Mail::to($order->customer->email)->send(new OrderRejection($order));
            }
            if ($order->customer) {
                FcmNotificationHelper::sendOrderNotification($order->customer, $order->id, OrderStatuses::REJECTED);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify order rejection', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        return back()->with('success', __('Order rejected.'));
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.edit');

        $validated = $request->validate([
            'status' => ['required', 'integer', Rule::in(array_keys(OrderStatuses::labels()))],
            'deliveryman_id' => ['nullable', 'exists:users,id'],
        ]);

        $order->update([
            'status' => (int) $validated['status'],
            'deliveryman_id' => $validated['deliveryman_id'] ?? $order->deliveryman_id,
        ]);

        return back()->with('success', __('Order updated.'));
    }

    public function bulkAccept(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.accept');
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:orders,id']])['ids'];

        Order::query()->whereIn('id', $ids)->where('status', OrderStatuses::PENDING)->each(function (Order $order) {
            $order->update(['status' => OrderStatuses::ACCEPTED]);
            $order->load('customer');
            if ($order->customer) {
                try {
                    FcmNotificationHelper::sendOrderNotification($order->customer, $order->id, OrderStatuses::ACCEPTED);
                } catch (\Throwable) {
                }
            }
        });

        return back()->with('success', __('Orders accepted.'));
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.accept');
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:orders,id']])['ids'];

        Order::query()->whereIn('id', $ids)->where('status', OrderStatuses::PENDING)->each(function (Order $order) {
            $order->update(['status' => OrderStatuses::REJECTED]);
            $order->load(['customer', 'order_items.menu_item', 'order_items.size', 'order_items.orderAttachments.attachment']);
            try {
                if ($order->customer?->email) {
                    Mail::to($order->customer->email)->send(new OrderRejection($order));
                }
                if ($order->customer) {
                    FcmNotificationHelper::sendOrderNotification($order->customer, $order->id, OrderStatuses::REJECTED);
                }
            } catch (\Throwable) {
            }
        });

        return back()->with('success', __('Orders rejected.'));
    }

    public function bulkAssign(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.edit');
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:orders,id'],
            'deliveryman_id' => ['required', 'exists:users,id'],
        ]);

        Order::query()
            ->whereIn('id', $validated['ids'])
            ->where('type', 1)
            ->where('status', '!=', OrderStatuses::REJECTED)
            ->update(['deliveryman_id' => $validated['deliveryman_id']]);

        return back()->with('success', __('Deliveryman assigned.'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'orders.view');
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $orders = Order::query()->whereIn('id', $ids)->with('customer')->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'customer', 'phone', 'status', 'type', 'total', 'payment', 'created_at']);
            foreach ($orders as $order) {
                fputcsv($out, [
                    $order->id,
                    $order->customer_name ?? $order->customer?->name,
                    $order->phone_number,
                    OrderStatuses::label($order->status),
                    OrderStatuses::typeLabel($order->type),
                    $order->total_price,
                    $order->payment_method,
                    $order->created_at,
                ]);
            }
            fclose($out);
        }, 'orders-export.csv', ['Content-Type' => 'text/csv']);
    }

    public function print(Request $request, Order $order, string $page = ''): \Illuminate\View\View
    {
        $this->authorizePermission($request, 'orders.print');

        $order->load([
            'customer',
            'order_items.menu_item.menu',
            'order_items.menu_item.toppings.attachments',
            'order_items.size',
            'order_items.orderAttachments.attachment',
            'order_items.orderAttachments.topping',
        ]);

        $delivery = Delivery::query()->first();
        $previousOrders = 0;
        if ($order->customer_id) {
            $previousOrders = Order::query()
                ->where('id', '!=', $order->id)
                ->where('customer_id', $order->customer_id)
                ->count();
        } elseif ($order->phone_number) {
            $previousOrders = Order::query()
                ->where('id', '!=', $order->id)
                ->where('phone_number', $order->phone_number)
                ->count();
        }

        return view('print.receipt', compact('order', 'page', 'delivery', 'previousOrders'));
    }

    public function createDeal(Request $request): Response
    {
        $this->authorizePermission($request, 'orders.create');

        $items = MenuItem::query()
            ->where('status', 1)
            ->whereHas('toppings')
            ->with(['menu:id,title', 'toppings'])
            ->orderBy('title')
            ->get()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'price' => $item->price,
                'image' => $item->image,
                'menu' => $item->menu?->title,
            ]);

        return Inertia::render('resources/orders/CreateDeal', [
            'menuItems' => $items,
        ]);
    }

    public function deal(Request $request, MenuItem $menuItem): Response
    {
        $this->authorizePermission($request, 'orders.create');

        $menuItem->load(['toppings.attachments', 'menu:id,title']);

        return Inertia::render('resources/orders/Deal', [
            'menuItem' => [
                'id' => $menuItem->id,
                'title' => $menuItem->title,
                'price' => (float) $menuItem->price,
                'image' => $menuItem->image,
                'menu' => $menuItem->menu?->title,
                'toppings' => $menuItem->toppings->map(fn ($topping) => [
                    'id' => $topping->id,
                    'name' => $topping->name,
                    'is_optional' => (bool) $topping->is_optional,
                    'required' => (int) ($topping->required ?? 0),
                    'attachments' => $topping->attachments->map(fn ($a) => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'price' => (float) $a->price,
                    ])->values()->all(),
                ])->values()->all(),
            ],
        ]);
    }

    public function storeDeal(Request $request, MenuItem $menuItem): RedirectResponse
    {
        $this->authorizePermission($request, 'orders.create');

        $menuItem->load(['toppings.attachments']);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:cash,card,stripe'],
            'toppings' => ['nullable', 'array'],
        ]);

        foreach ($menuItem->toppings as $topping) {
            if ($topping->is_optional) {
                continue;
            }
            $attachments = $validated['toppings'][$topping->id] ?? [];
            $totalQty = collect($attachments)->sum(fn ($q) => (int) $q);
            if ($totalQty < (int) $topping->required) {
                return back()->withErrors([
                    "toppings.{$topping->id}" => __("Select at least :n for :name.", [
                        'n' => $topping->required,
                        'name' => $topping->name,
                    ]),
                ])->withInput();
            }
        }

        $order = DB::transaction(function () use ($validated, $menuItem) {
            $order = Order::query()->create([
                'customer_name' => $validated['customer_name'] ?? null,
                'phone_number' => $validated['phone_number'] ?? null,
                'address' => $validated['address'] ?? null,
                'payment_method' => $validated['payment_method'],
                'total_price' => 0,
                'status' => OrderStatuses::COMPLETED,
                'type' => 2,
                'description' => Order::withSource(null, 'admin'),
            ]);

            $quantity = max((int) $validated['quantity'], 1);
            $extras = 0.0;

            foreach ($validated['toppings'] ?? [] as $attachments) {
                foreach ($attachments as $attachmentId => $qtyRaw) {
                    $qty = (int) $qtyRaw;
                    if ($qty <= 0) {
                        continue;
                    }
                    $attachment = MenuAttachment::query()->find($attachmentId);
                    if (! $attachment) {
                        continue;
                    }
                    $extras += (float) $attachment->price * $qty;
                }
            }

            $unitPrice = round((float) $menuItem->price + $extras, 2);
            $lineTotal = round($unitPrice * $quantity, 2);

            $orderItem = OrderItem::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $quantity,
                'price' => $unitPrice,
            ]);

            foreach ($validated['toppings'] ?? [] as $toppingId => $attachments) {
                foreach ($attachments as $attachmentId => $quantityRaw) {
                    $qty = (int) $quantityRaw;
                    if ($qty <= 0) {
                        continue;
                    }
                    $attachment = MenuAttachment::query()->find($attachmentId);
                    if (! $attachment) {
                        continue;
                    }
                    OrderAttachment::query()->create([
                        'order_item_id' => $orderItem->id,
                        'topping_id' => (int) $toppingId,
                        'attachment_id' => (int) $attachmentId,
                        'quantity' => $qty,
                    ]);
                }
            }

            $order->update(['total_price' => $lineTotal, 'subtotal' => $lineTotal]);

            return $order;
        });

        return redirect()->route('orders.print', ['order' => $order->id, 'page' => 'deal']);
    }

    public function pricingPreview(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorizePermission($request, 'orders.create');

        $validated = $request->validate([
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.menu_item_id' => ['required', 'integer'],
            'order_items.*.size_id' => ['nullable', 'integer'],
            'order_items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'order_items.*.price' => ['nullable', 'numeric'],
            'type' => ['required', 'integer', 'in:1,2'],
            'discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->pricing->calculate(
            $validated['order_items'],
            (int) $validated['type'],
            (float) ($validated['discount'] ?? 0),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $menus = Menu::query()
            ->whereHas('menu_items', fn ($q) => $q->where('status', true))
            ->with([
                'menu_items' => fn ($q) => $q
                    ->where('status', true)
                    ->with(['sizes', 'toppings.attachments']),
            ])
            ->orderBy('title')
            ->get();

        return [
            'menus' => $menus->map(fn (Menu $menu) => [
                'id' => $menu->id,
                'title' => $menu->title,
                'items' => $menu->menu_items->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'price' => (float) $item->price,
                    'image_url' => $this->publicImageUrl($item->image),
                    'is_deal' => $item->toppings->isNotEmpty(),
                    'sizes' => $item->sizes->map(fn ($size) => [
                        'id' => $size->id,
                        'size' => $size->size,
                        'price' => (float) $size->price,
                    ])->values()->all(),
                    'toppings' => $item->toppings->map(fn ($topping) => [
                        'id' => $topping->id,
                        'name' => $topping->name,
                        'is_optional' => (bool) $topping->is_optional,
                        'required' => (int) ($topping->required ?? 0),
                        'attachments' => $topping->attachments->map(fn ($attachment) => [
                            'id' => $attachment->id,
                            'name' => $attachment->name,
                            'price' => (float) $attachment->price,
                        ])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
            'customers' => User::query()
                ->where('role', User::ROLE_CUSTOMER)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'phone_number']),
            'deliverymen' => User::query()
                ->where('role', User::ROLE_DELIVERY_MAN)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statusOptions' => OrderStatuses::labels(),
            'typeOptions' => OrderStatuses::typeLabels(),
            'delivery' => Delivery::query()->first()?->only([
                'delivery_price',
                'free_delivery_limit',
                'service_fees',
                'discount_for_all',
                'min_order',
            ]) ?? [
                'delivery_price' => 0,
                'free_delivery_limit' => 0,
                'service_fees' => 0,
                'discount_for_all' => 0,
                'min_order' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateOrder(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'exists:users,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', Rule::in(array_keys(OrderStatuses::labels()))],
            'type' => ['required', 'integer', 'in:1,2'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash,card,stripe'],
            'deliveryman_id' => ['nullable', 'exists:users,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'order_items.*.size_id' => ['nullable', 'exists:sizes,id'],
            'order_items.*.quantity' => ['required', 'integer', 'min:1'],
            'order_items.*.price' => ['nullable', 'numeric', 'min:0'],
            'order_items.*.toppings' => ['nullable', 'array'],
            'order_items.*.toppings.*' => ['array'],
            'order_items.*.toppings.*.*' => ['integer', 'min:0'],
        ]);
    }

    /**
     * @param  array{menu_item_id?: int, size_id?: int|null, price?: float|int|null, toppings?: array<int|string, array<int|string, int>>}  $item
     */
    protected function unitPrice(array $item): float
    {
        return $this->lineUnitPrice($item);
    }

    /**
     * Base size/item price + selected topping extras (per unit).
     *
     * @param  array{menu_item_id?: int, size_id?: int|null, toppings?: array<int|string, array<int|string, int>>}  $item
     */
    protected function lineUnitPrice(array $item): float
    {
        if (! empty($item['size_id'])) {
            $size = \App\Models\Size::query()->find($item['size_id']);
            $base = (float) ($size?->price ?? 0);
        } else {
            $menuItem = MenuItem::query()->find($item['menu_item_id'] ?? null);
            $base = (float) ($menuItem?->price ?? 0);
        }

        return round($base + $this->toppingsExtra($item['toppings'] ?? []), 2);
    }

    /**
     * @param  array<int|string, array<int|string, int>>  $toppings
     */
    protected function toppingsExtra(array $toppings): float
    {
        $extra = 0.0;

        foreach ($toppings as $attachments) {
            if (! is_array($attachments)) {
                continue;
            }

            foreach ($attachments as $attachmentId => $quantity) {
                $qty = (int) $quantity;
                if ($qty <= 0) {
                    continue;
                }

                $attachment = MenuAttachment::query()->find((int) $attachmentId);
                if (! $attachment) {
                    continue;
                }

                $extra += (float) $attachment->price * $qty;
            }
        }

        return $extra;
    }

    /**
     * @param  list<array{menu_item_id: int, toppings?: array<int|string, array<int|string, int>>}>  $orderItems
     * @return array<string, string>|null
     */
    protected function validateOrderItemToppings(array $orderItems): ?array
    {
        foreach ($orderItems as $index => $item) {
            $menuItem = MenuItem::query()
                ->with('toppings')
                ->find($item['menu_item_id'] ?? null);

            if (! $menuItem) {
                continue;
            }

            foreach ($menuItem->toppings as $topping) {
                if ($topping->is_optional) {
                    continue;
                }

                $selected = collect($item['toppings'][$topping->id] ?? [])
                    ->sum(fn ($qty) => (int) $qty);

                $required = max((int) ($topping->required ?? 0), 1);

                if ($selected < $required) {
                    return [
                        "order_items.{$index}.toppings.{$topping->id}" => __('Select at least :n for :name.', [
                            'n' => $required,
                            'name' => $topping->name,
                        ]),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, array<int|string, int>>  $toppings
     */
    protected function syncOrderItemToppings(OrderItem $orderItem, array $toppings): void
    {
        foreach ($toppings as $toppingId => $attachments) {
            if (! is_array($attachments)) {
                continue;
            }

            foreach ($attachments as $attachmentId => $quantity) {
                $qty = (int) $quantity;
                if ($qty <= 0) {
                    continue;
                }

                $attachment = MenuAttachment::query()->find((int) $attachmentId);
                if (! $attachment) {
                    continue;
                }

                OrderAttachment::query()->create([
                    'order_item_id' => $orderItem->id,
                    'topping_id' => (int) ($attachment->topping_id ?: $toppingId),
                    'attachment_id' => (int) $attachmentId,
                    'quantity' => $qty,
                ]);
            }
        }
    }

    protected function itemsHtml(Order $order): string
    {
        $parts = [];
        foreach ($order->order_items as $item) {
            $title = e($item->menu_item?->title ?? 'Item');
            $qty = (int) $item->quantity;
            $size = $item->size?->size ? ' ('.e($item->size->size).')' : '';
            $line = "<strong>{$qty}× {$title}{$size}</strong>";
            $attachments = $item->orderAttachments
                ->map(fn ($a) => e(($a->attachment?->name ?? '').' ×'.$a->quantity))
                ->filter()
                ->implode(', ');
            if ($attachments !== '') {
                $line .= '<br><span class="text-muted">'.$attachments.'</span>';
            }
            $parts[] = $line;
        }

        return implode('<hr>', $parts);
    }

    protected function publicImageUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        // Skip remote URLs — create order UI uses a local default instead.
        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '//')
        ) {
            return null;
        }

        if (str_starts_with($path, '/storage/') || str_starts_with($path, '/brand/') || str_starts_with($path, '/images/')) {
            return $path;
        }

        return Storage::disk('public')->url(ltrim($path, '/'));
    }
}
