<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\PlatformRevenue;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformRevenueController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'platform_revenues.view');

        $period = $this->resolvePeriod($request);
        [$start, $end] = $this->periodBounds($period);

        $query = PlatformRevenue::query()
            ->with('platform:id,name')
            ->when($request->filled('platform_id'), function ($q) use ($request) {
                $q->where('platform_id', $request->integer('platform_id'));
            })
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('revenue_date', [$start->toDateString(), $end->toDateString()]);
            });

        $summaryQuery = clone $query;

        $items = (clone $query)
            ->latest('revenue_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PlatformRevenue $revenue) => $this->serialize($revenue));

        $totalAmount = (float) $summaryQuery->sum('amount');
        $entryCount = (int) $summaryQuery->count();

        $byPlatform = PlatformRevenue::query()
            ->select('platform_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as entries'))
            ->when($request->filled('platform_id'), function ($q) use ($request) {
                $q->where('platform_id', $request->integer('platform_id'));
            })
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('revenue_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->groupBy('platform_id')
            ->with('platform:id,name')
            ->get()
            ->map(fn ($row) => [
                'platform_id' => $row->platform_id,
                'platform_name' => $row->platform?->name ?? __('Unknown'),
                'total' => (float) $row->total,
                'entries' => (int) $row->entries,
            ])
            ->values();

        return Inertia::render('resources/platform-revenues/Index', [
            'items' => $this->inertiaPagination($items),
            'platforms' => $this->platformOptions(activeOnly: false),
            'summary' => [
                'total_amount' => $totalAmount,
                'entry_count' => $entryCount,
                'by_platform' => $byPlatform,
                'period_label' => $this->periodLabel($period, $start, $end),
            ],
            'filters' => [
                'period' => $period['type'],
                'date' => $period['date'],
                'week' => $period['week'],
                'month' => $period['month'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
                'platform_id' => $request->query('platform_id'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'platform_revenues.create');

        return Inertia::render('resources/platform-revenues/Create', [
            'platforms' => $this->platformOptions(activeOnly: true),
            'defaults' => [
                'revenue_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'platform_revenues.create');

        PlatformRevenue::query()->create($this->validated($request));

        return redirect()
            ->route('platform-revenues.index')
            ->with('success', __('Created.'));
    }

    public function edit(Request $request, PlatformRevenue $platformRevenue): Response
    {
        $this->authorizePermission($request, 'platform_revenues.edit');

        $platformRevenue->load('platform:id,name');

        return Inertia::render('resources/platform-revenues/Edit', [
            'item' => $this->serialize($platformRevenue),
            'platforms' => $this->platformOptions(activeOnly: false),
        ]);
    }

    public function update(Request $request, PlatformRevenue $platformRevenue): RedirectResponse
    {
        $this->authorizePermission($request, 'platform_revenues.edit');

        $platformRevenue->update($this->validated($request));

        return redirect()
            ->route('platform-revenues.index')
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, PlatformRevenue $platformRevenue): RedirectResponse
    {
        $this->authorizePermission($request, 'platform_revenues.delete');

        $platformRevenue->delete();

        return redirect()
            ->route('platform-revenues.index')
            ->with('success', __('Deleted.'));
    }

    /**
     * @return array{type: string, date: string, week: string, month: string, date_from: string, date_to: string}
     */
    protected function resolvePeriod(Request $request): array
    {
        $type = (string) $request->query('period', 'month');
        if (! in_array($type, ['day', 'week', 'month', 'custom', 'all'], true)) {
            $type = 'month';
        }

        return [
            'type' => $type,
            'date' => (string) $request->query('date', now()->toDateString()),
            'week' => (string) $request->query('week', now()->startOfWeek()->toDateString()),
            'month' => (string) $request->query('month', now()->format('Y-m')),
            'date_from' => (string) $request->query('date_from', now()->startOfMonth()->toDateString()),
            'date_to' => (string) $request->query('date_to', now()->toDateString()),
        ];
    }

    /**
     * @param  array{type: string, date: string, week: string, month: string, date_from: string, date_to: string}  $period
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected function periodBounds(array $period): array
    {
        try {
            return match ($period['type']) {
                'day' => [
                    Carbon::parse($period['date'])->startOfDay(),
                    Carbon::parse($period['date'])->endOfDay(),
                ],
                'week' => [
                    Carbon::parse($period['week'])->startOfWeek(),
                    Carbon::parse($period['week'])->endOfWeek(),
                ],
                'month' => [
                    Carbon::createFromFormat('Y-m', $period['month'])->startOfMonth(),
                    Carbon::createFromFormat('Y-m', $period['month'])->endOfMonth(),
                ],
                'custom' => [
                    Carbon::parse($period['date_from'])->startOfDay(),
                    Carbon::parse($period['date_to'])->endOfDay(),
                ],
                default => [null, null],
            };
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();

            return [$start, $end];
        }
    }

    /**
     * @param  array{type: string, date: string, week: string, month: string, date_from: string, date_to: string}  $period
     */
    protected function periodLabel(array $period, ?Carbon $start, ?Carbon $end): string
    {
        if ($period['type'] === 'all' || ! $start || ! $end) {
            return __('All time');
        }

        return match ($period['type']) {
            'day' => $start->toFormattedDateString(),
            'week', 'custom' => $start->toFormattedDateString().' – '.$end->toFormattedDateString(),
            'month' => $start->format('F Y'),
            default => __('All time'),
        };
    }

    /**
     * @return list<array{value: int|string, label: string}>
     */
    protected function platformOptions(bool $activeOnly): array
    {
        return Platform::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Platform $platform) => [
                'value' => $platform->id,
                'label' => $platform->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'platform_id' => ['required', 'exists:platforms,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'revenue_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(PlatformRevenue $revenue): array
    {
        return [
            'id' => $revenue->id,
            'platform_id' => $revenue->platform_id,
            'platform_name' => $revenue->platform?->name,
            'amount' => $revenue->amount,
            'revenue_date' => $revenue->revenue_date?->toDateString(),
            'notes' => $revenue->notes,
            'created_at' => $revenue->created_at?->toIso8601String(),
            'updated_at' => $revenue->updated_at?->toIso8601String(),
        ];
    }
}
