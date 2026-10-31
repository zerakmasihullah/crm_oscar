<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatuses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $weekStart = $now->copy()->subDays(6)->startOfDay();

        $todayOrders = Order::query()
            ->select([
                'id',
                'customer_id',
                'customer_name',
                'phone_number',
                'address',
                'latitude',
                'longitude',
                'delivery_datetime',
                'subtotal',
                'discount',
                'total_price',
                'payment_method',
                'payment_id',
                'description',
                'status',
                'type',
                'deliveryman_id',
                'created_at',
            ])
            ->with([
                'customer:id,name',
                'deliveryman:id,name',
                'order_items:id,order_id,menu_item_id,size_id,quantity',
                'order_items.menu_item:id,title',
                'order_items.size:id,size',
                'order_items.orderAttachments:id,order_item_id,attachment_id,quantity',
                'order_items.orderAttachments.attachment:id,name',
            ])
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->latest('id')
            ->get();

        $statusCounts = [];
        $ordersToday = 0;
        $revenueToday = 0.0;

        $mappedOrders = $todayOrders->map(function (Order $order) use (&$statusCounts, &$ordersToday, &$revenueToday) {
            $status = (int) $order->status;
            $total = (float) $order->total_price;
            $ordersToday++;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            if ($status !== OrderStatuses::REJECTED) {
                $revenueToday += $total;
            }

            $dueAt = $this->dueAt($order);
            $notes = Order::stripSource($order->description);

            return [
                'id' => $order->id,
                'customer_id' => $order->customer_id,
                'customer' => $order->customer_name ?? $order->customer?->name ?? '—',
                'phone' => $order->phone_number,
                'address' => $order->address,
                'latitude' => $order->latitude,
                'longitude' => $order->longitude,
                'delivery_datetime' => $order->delivery_datetime?->toIso8601String(),
                'due_at' => $dueAt?->toIso8601String(),
                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'total_price' => $total,
                'payment_method' => $order->payment_method,
                'payment_id' => $order->payment_id,
                'notes' => $notes !== '' ? $notes : null,
                'status' => $status,
                'status_label' => OrderStatuses::label($status),
                'type' => (int) $order->type,
                'type_label' => OrderStatuses::typeLabel($order->type),
                'deliveryman_id' => $order->deliveryman_id,
                'deliveryman' => $order->deliveryman?->name,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->order_items->map(static fn ($item) => [
                    'title' => $item->menu_item?->title ?? __('Item'),
                    'quantity' => (int) $item->quantity,
                    'size' => $item->size?->size,
                    'extras' => $item->orderAttachments
                        ->map(static fn ($a) => trim(($a->attachment?->name ?? '').' ×'.(int) $a->quantity))
                        ->filter()
                        ->values()
                        ->all(),
                ])->values()->all(),
            ];
        })
            ->sortBy(static fn (array $order) => $order['due_at'] ?? $order['created_at'] ?? '')
            ->values();

        $pendingAll = (int) Order::query()
            ->where('status', OrderStatuses::PENDING)
            ->count();

        $weekAgg = Order::query()
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(CASE WHEN status != ? THEN total_price ELSE 0 END) as revenue_total', [OrderStatuses::REJECTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_count', [OrderStatuses::COMPLETED])
            ->where('created_at', '>=', $weekStart)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $labels = [];
        $revenue = [];
        $ordersCount = [];
        $completedWeek = 0;
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i)->format('Y-m-d');
            $row = $weekAgg->get($day);
            $labels[] = Carbon::parse($day)->format('D');
            $revenue[] = round((float) ($row->revenue_total ?? 0), 2);
            $ordersCount[] = (int) ($row->orders_count ?? 0);
            $completedWeek += (int) ($row->completed_count ?? 0);
        }

        $statusChart = collect(OrderStatuses::labels())
            ->map(static fn ($label, $status) => [
                'label' => $label,
                'value' => (int) ($statusCounts[$status] ?? 0),
            ])
            ->filter(static fn ($row) => $row['value'] > 0)
            ->values()
            ->all();

        $drivers = User::query()
            ->where('role', User::ROLE_DELIVERY_MAN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'stats' => [
                'orders_today' => $ordersToday,
                'revenue_today' => round($revenueToday, 2),
                'pending' => $pendingAll,
                'completed_week' => $completedWeek,
            ],
            'revenueChart' => [
                'labels' => $labels,
                'data' => $revenue,
            ],
            'ordersChart' => [
                'labels' => $labels,
                'data' => $ordersCount,
            ],
            'statusChart' => $statusChart,
            'todayOrders' => $mappedOrders,
            'drivers' => $drivers,
            'today' => $todayStart->toDateString(),
            'canListenSound' => (int) ($request->user()?->role ?? 0) === User::ROLE_ADMIN
                || $request->user()?->can('orders.view'),
        ]);
    }

    protected function dueAt(Order $order): ?Carbon
    {
        if (! $order->created_at) {
            return null;
        }

        $placed = Carbon::parse($order->created_at);
        $asapDue = $placed->copy()->addMinutes(((int) $order->type === 1) ? 45 : 15);

        if ((int) $order->type === 1 && $order->delivery_datetime) {
            $selected = Carbon::parse($order->delivery_datetime);
            if ($selected->gt($asapDue)) {
                return $selected;
            }
        }

        return $asapDue;
    }
}
