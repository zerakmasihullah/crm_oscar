<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'analytics.view');

        $tz = 'Europe/Dublin';
        $revenueLabels = [];
        $revenueData = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now($tz)->subDays($i);
            $revenueLabels[] = $date->format('M d');
            $revenueData[] = round(
                (float) Order::query()
                    ->whereDate('created_at', $date)
                    ->where('status', '!=', 3)
                    ->sum('total_price'),
                2
            );
        }

        $statusLabels = ['Pending', 'Accepted', 'Rejected', 'Delivering', 'Completed'];
        $statusData = [
            Order::where('status', 1)->count(),
            Order::where('status', 2)->count(),
            Order::where('status', 3)->count(),
            Order::where('status', 4)->count(),
            Order::where('status', 5)->count(),
        ];

        $topItems = OrderItem::query()
            ->select(
                'menu_items.id',
                'menu_items.title',
                'menu_items.image',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->groupBy('menu_items.id', 'menu_items.title', 'menu_items.image')
            ->orderByDesc('total_quantity')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'image' => $item->image,
                'total_quantity' => (int) $item->total_quantity,
                'total_revenue' => round((float) $item->total_revenue, 2),
            ])
            ->all();

        $latestOrders = Order::query()
            ->with(['customer:id,name', 'order_items.menu_item:id,title'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'customer_name' => $order->customer?->name ?? $order->customer_name ?? 'Admin Order',
                'total_price' => (float) $order->total_price,
                'status' => (int) $order->status,
                'status_label' => match ((int) $order->status) {
                    1 => 'Pending',
                    2 => 'Accepted',
                    3 => 'Rejected',
                    4 => 'Delivering',
                    5 => 'Completed',
                    default => 'Unknown',
                },
                'type_label' => match ((int) $order->type) {
                    1 => 'Delivery',
                    2 => 'Collection',
                    default => 'Unknown',
                },
                'items_summary' => $order->order_items
                    ->map(fn ($item) => ($item->menu_item?->title ?? 'Item').' × '.$item->quantity)
                    ->implode(', '),
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();

        $totalRevenue = round(array_sum($revenueData), 2);
        $ordersToday = Order::query()->whereDate('created_at', Carbon::now($tz))->count();
        $pendingOrders = Order::query()->where('status', 1)->count();
        $completedOrders = Order::query()->where('status', 5)->count();

        return Inertia::render('resources/analytics/Index', [
            'stats' => [
                'revenue_30d' => $totalRevenue,
                'orders_today' => $ordersToday,
                'pending' => $pendingOrders,
                'completed' => $completedOrders,
            ],
            'revenueChart' => [
                'labels' => $revenueLabels,
                'datasets' => [
                    ['label' => 'Revenue (€)', 'data' => $revenueData],
                ],
            ],
            'orderStatusChart' => [
                'labels' => $statusLabels,
                'datasets' => [
                    ['label' => 'Orders', 'data' => $statusData],
                ],
            ],
            'topSellingItems' => $topItems,
            'latestOrders' => $latestOrders,
        ]);
    }
}
