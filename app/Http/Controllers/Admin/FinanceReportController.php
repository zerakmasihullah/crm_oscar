<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Platform;
use App\Models\PlatformRevenue;
use App\Support\OrderStatuses;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class FinanceReportController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizeAnyPermission($request, [
            'platform_revenues.view',
            'expenses.view',
            'orders.view',
        ]);

        $filters = $this->resolveFilters($request);
        [$start, $end] = $this->periodBounds($filters);

        $canOrders = $request->user()?->can('orders.view') ?? false;
        $canRevenue = $request->user()?->can('platform_revenues.view') ?? false;
        $canExpenses = $request->user()?->can('expenses.view') ?? false;

        $orderSource = $filters['order_source'];
        $platformId = $filters['platform_id'];
        $categoryId = $filters['category_id'];

        $systemRevenue = 0.0;
        $systemOrders = 0;
        $bySource = [];
        $ordersTrend = [];

        if ($canOrders) {
            $orders = Order::query()
                ->when($start && $end, function ($q) use ($start, $end) {
                    $q->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()]);
                })
                ->where('status', '!=', OrderStatuses::REJECTED)
                ->get(['id', 'total_price', 'description', 'latitude', 'longitude', 'created_at']);

            if ($orderSource !== null && $orderSource !== '') {
                $orders = $orders->filter(
                    fn (Order $order) => $order->source_key === $orderSource
                )->values();
            }

            $systemRevenue = round((float) $orders->sum('total_price'), 2);
            $systemOrders = $orders->count();

            $bySource = collect(['website', 'mobile', 'admin'])
                ->map(function (string $key) use ($orders) {
                    $group = $orders->filter(fn (Order $order) => $order->source_key === $key);

                    return [
                        'key' => $key,
                        'name' => match ($key) {
                            'mobile' => __('Mobile App'),
                            'admin' => __('Admin / POS'),
                            default => __('Website'),
                        },
                        'total' => round((float) $group->sum('total_price'), 2),
                        'count' => $group->count(),
                    ];
                })
                ->filter(fn (array $row) => $row['count'] > 0 || $row['total'] > 0)
                ->values()
                ->all();
        }

        $platformRevenue = 0.0;
        $platformEntries = 0;
        $byPlatform = [];
        $platformTrend = [];

        if ($canRevenue) {
            $revenues = PlatformRevenue::query()
                ->with('platform:id,name')
                ->when($start && $end, function ($q) use ($start, $end) {
                    $q->whereBetween('revenue_date', [$start->toDateString(), $end->toDateString()]);
                })
                ->when($platformId, fn ($q) => $q->where('platform_id', $platformId))
                ->get();

            $platformRevenue = round((float) $revenues->sum('amount'), 2);
            $platformEntries = $revenues->count();

            $byPlatform = $revenues
                ->groupBy('platform_id')
                ->map(function (Collection $group) {
                    /** @var PlatformRevenue $first */
                    $first = $group->first();

                    return [
                        'id' => $first->platform_id,
                        'name' => $first->platform?->name ?? __('Unknown'),
                        'total' => round((float) $group->sum('amount'), 2),
                        'count' => $group->count(),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();
        }

        $expenseTotal = 0.0;
        $expenseEntries = 0;
        $byCategory = [];
        $expenseTrend = [];

        if ($canExpenses) {
            $expenses = Expense::query()
                ->with('category:id,name')
                ->when($start && $end, function ($q) use ($start, $end) {
                    $q->where('expense_date', '<=', $end->toDateString())
                        ->whereRaw('COALESCE(period_end, expense_date) >= ?', [$start->toDateString()]);
                })
                ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
                ->get();

            $expenseTotal = round((float) $expenses->sum('amount'), 2);
            $expenseEntries = $expenses->count();

            $byCategory = $expenses
                ->groupBy('expense_category_id')
                ->map(function (Collection $group) {
                    /** @var Expense $first */
                    $first = $group->first();

                    return [
                        'id' => $first->expense_category_id,
                        'name' => $first->category?->name ?? __('Unknown'),
                        'total' => round((float) $group->sum('amount'), 2),
                        'count' => $group->count(),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->all();
        }

        $incomeTotal = round($systemRevenue + $platformRevenue, 2);
        $net = round($incomeTotal - $expenseTotal, 2);

        $sharedStart = $start;
        $sharedEnd = $end;
        if (! $sharedStart || ! $sharedEnd) {
            $dates = collect();
            if (isset($orders)) {
                $dates = $dates->merge($orders->pluck('created_at'));
            }
            if (isset($revenues)) {
                $dates = $dates->merge($revenues->pluck('revenue_date'));
            }
            if (isset($expenses)) {
                $dates = $dates->merge($expenses->pluck('expense_date'));
            }
            if ($dates->isNotEmpty()) {
                $sharedStart = Carbon::parse($dates->min())->startOfDay();
                $sharedEnd = Carbon::parse($dates->max())->endOfDay();
            }
        }

        if ($canOrders) {
            $ordersTrend = $this->trendBuckets(
                $sharedStart,
                $sharedEnd,
                $orders ?? collect(),
                'created_at',
                'total_price',
            );
        }
        if ($canRevenue) {
            $platformTrend = $this->trendBuckets(
                $sharedStart,
                $sharedEnd,
                $revenues ?? collect(),
                'revenue_date',
                'amount',
                true,
            );
        }
        if ($canExpenses) {
            $expenseTrend = $this->trendBuckets(
                $sharedStart,
                $sharedEnd,
                $expenses ?? collect(),
                'expense_date',
                'amount',
                true,
            );
        }

        $trendLabels = $this->mergeTrendLabels($ordersTrend, $platformTrend, $expenseTrend);

        return Inertia::render('resources/finance/Reports', [
            'summary' => [
                'system_revenue' => $systemRevenue,
                'system_orders' => $systemOrders,
                'platform_revenue' => $platformRevenue,
                'platform_entries' => $platformEntries,
                'expense_total' => $expenseTotal,
                'expense_entries' => $expenseEntries,
                'income_total' => $incomeTotal,
                'net' => $net,
                'period_label' => $this->periodLabel($filters, $start, $end),
            ],
            'charts' => [
                'trend' => [
                    'labels' => $trendLabels,
                    'datasets' => array_values(array_filter([
                        $canOrders ? [
                            'label' => __('System orders'),
                            'data' => $this->alignTrend($trendLabels, $ordersTrend),
                        ] : null,
                        $canRevenue ? [
                            'label' => __('Platform revenue'),
                            'data' => $this->alignTrend($trendLabels, $platformTrend),
                        ] : null,
                        $canExpenses ? [
                            'label' => __('Expenses'),
                            'data' => $this->alignTrend($trendLabels, $expenseTrend),
                        ] : null,
                    ])),
                ],
                'order_sources' => [
                    'labels' => array_column($bySource, 'name'),
                    'datasets' => [[
                        'label' => __('Revenue'),
                        'data' => array_column($bySource, 'total'),
                    ]],
                ],
                'platforms' => [
                    'labels' => array_column($byPlatform, 'name'),
                    'datasets' => [[
                        'label' => __('Revenue'),
                        'data' => array_column($byPlatform, 'total'),
                    ]],
                ],
                'categories' => [
                    'labels' => array_column($byCategory, 'name'),
                    'datasets' => [[
                        'label' => __('Expenses'),
                        'data' => array_column($byCategory, 'total'),
                    ]],
                ],
            ],
            'by_source' => $bySource,
            'by_platform' => $byPlatform,
            'by_category' => $byCategory,
            'can_orders' => $canOrders,
            'can_revenue' => $canRevenue,
            'can_expenses' => $canExpenses,
            'filter_options' => [
                'platforms' => Platform::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Platform $p) => [
                        'value' => (string) $p->id,
                        'label' => $p->name,
                    ])
                    ->values()
                    ->all(),
                'categories' => ExpenseCategory::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (ExpenseCategory $c) => [
                        'value' => (string) $c->id,
                        'label' => $c->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{
     *     period: string,
     *     date: string,
     *     week: string,
     *     month: string,
     *     year: string,
     *     date_from: string,
     *     date_to: string,
     *     order_source: ?string,
     *     platform_id: ?int,
     *     category_id: ?int
     * }
     */
    protected function resolveFilters(Request $request): array
    {
        $period = (string) $request->query('period', 'month');
        if (! in_array($period, ['day', 'week', 'month', 'year', 'custom', 'all'], true)) {
            $period = 'month';
        }

        $orderSource = $request->query('order_source');
        $orderSource = is_string($orderSource) && in_array($orderSource, ['website', 'mobile', 'admin'], true)
            ? $orderSource
            : null;

        return [
            'period' => $period,
            'date' => (string) $request->query('date', now()->toDateString()),
            'week' => (string) $request->query('week', now()->startOfWeek()->toDateString()),
            'month' => (string) $request->query('month', now()->format('Y-m')),
            'year' => (string) $request->query('year', now()->format('Y')),
            'date_from' => (string) $request->query('date_from', now()->startOfMonth()->toDateString()),
            'date_to' => (string) $request->query('date_to', now()->toDateString()),
            'order_source' => $orderSource,
            'platform_id' => $request->filled('platform_id') ? $request->integer('platform_id') : null,
            'category_id' => $request->filled('category_id') ? $request->integer('category_id') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function periodBounds(array $filters): array
    {
        try {
            return match ($filters['period']) {
                'day' => [
                    Carbon::parse($filters['date'])->startOfDay(),
                    Carbon::parse($filters['date'])->endOfDay(),
                ],
                'week' => [
                    Carbon::parse($filters['week'])->startOfWeek(),
                    Carbon::parse($filters['week'])->endOfWeek(),
                ],
                'month' => [
                    Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth(),
                    Carbon::createFromFormat('Y-m', $filters['month'])->endOfMonth(),
                ],
                'year' => [
                    Carbon::createFromFormat('Y', $filters['year'])->startOfYear(),
                    Carbon::createFromFormat('Y', $filters['year'])->endOfYear(),
                ],
                'custom' => [
                    Carbon::parse($filters['date_from'])->startOfDay(),
                    Carbon::parse($filters['date_to'])->endOfDay(),
                ],
                default => [null, null],
            };
        } catch (\Throwable) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function periodLabel(array $filters, ?Carbon $start, ?Carbon $end): string
    {
        if ($filters['period'] === 'all' || ! $start || ! $end) {
            return __('All time');
        }

        return match ($filters['period']) {
            'day' => $start->toFormattedDateString(),
            'week' => $start->toFormattedDateString().' – '.$end->toFormattedDateString(),
            'month' => $start->format('F Y'),
            'year' => $start->format('Y'),
            'custom' => $start->toFormattedDateString().' – '.$end->toFormattedDateString(),
            default => __('All time'),
        };
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return array<string, float>
     */
    protected function trendBuckets(
        ?Carbon $start,
        ?Carbon $end,
        Collection $rows,
        string $dateField,
        string $amountField,
        bool $dateOnly = false,
    ): array {
        if ($rows->isEmpty()) {
            return [];
        }

        $effectiveStart = $start?->copy();
        $effectiveEnd = $end?->copy();

        if (! $effectiveStart || ! $effectiveEnd) {
            $dates = $rows->map(function ($row) use ($dateField, $dateOnly) {
                $value = $row->{$dateField};

                return $dateOnly
                    ? Carbon::parse($value)->startOfDay()
                    : Carbon::parse($value);
            });
            $effectiveStart = $dates->min()?->copy()->startOfDay();
            $effectiveEnd = $dates->max()?->copy()->endOfDay();
        }

        if (! $effectiveStart || ! $effectiveEnd) {
            return [];
        }

        $days = $effectiveStart->diffInDays($effectiveEnd) + 1;
        $useMonths = $days > 62;

        $buckets = [];

        if ($useMonths) {
            $cursor = $effectiveStart->copy()->startOfMonth();
            $last = $effectiveEnd->copy()->startOfMonth();
            while ($cursor <= $last) {
                $buckets[$cursor->format('Y-m')] = 0.0;
                $cursor->addMonth();
            }
        } else {
            foreach (CarbonPeriod::create($effectiveStart->copy()->startOfDay(), $effectiveEnd->copy()->startOfDay()) as $day) {
                $buckets[$day->format('Y-m-d')] = 0.0;
            }
        }

        foreach ($rows as $row) {
            $date = Carbon::parse($row->{$dateField});
            $key = $useMonths ? $date->format('Y-m') : $date->format('Y-m-d');
            if (! array_key_exists($key, $buckets)) {
                continue;
            }
            $buckets[$key] += (float) $row->{$amountField};
        }

        $labeled = [];
        foreach ($buckets as $key => $total) {
            $label = $useMonths
                ? Carbon::createFromFormat('Y-m', $key)->format('M Y')
                : Carbon::createFromFormat('Y-m-d', $key)->format('M j');
            $labeled[$label] = round($total, 2);
        }

        return $labeled;
    }

    /**
     * @param  array<string, float>  ...$series
     * @return list<string>
     */
    protected function mergeTrendLabels(array ...$series): array
    {
        $labels = [];
        foreach ($series as $map) {
            foreach (array_keys($map) as $label) {
                $labels[$label] = true;
            }
        }

        return array_keys($labels);
    }

    /**
     * @param  list<string>  $labels
     * @param  array<string, float>  $map
     * @return list<float>
     */
    protected function alignTrend(array $labels, array $map): array
    {
        return array_map(fn (string $label) => (float) ($map[$label] ?? 0), $labels);
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function authorizeAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();
        if ($user && collect($permissions)->contains(fn (string $p) => $user->can($p))) {
            return;
        }

        abort(403);
    }
}
