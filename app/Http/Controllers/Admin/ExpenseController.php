<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'expenses.view');

        $period = $this->resolvePeriod($request);
        [$start, $end] = $this->periodBounds($period);
        $search = trim((string) $request->query('search', ''));

        $query = Expense::query()
            ->with('category:id,name')
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('expense_category_id', $request->integer('category_id'));
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('vendor', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($start && $end, function ($q) use ($start, $end) {
                $this->scopeOverlappingPeriod($q, $start->toDateString(), $end->toDateString());
            });

        $summaryQuery = clone $query;

        $items = (clone $query)
            ->latest('expense_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Expense $expense) => $this->serialize($expense));

        $byCategory = Expense::query()
            ->select('expense_category_id', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as entries'))
            ->when($request->filled('category_id'), function ($q) use ($request) {
                $q->where('expense_category_id', $request->integer('category_id'));
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('vendor', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($start && $end, function ($q) use ($start, $end) {
                $this->scopeOverlappingPeriod($q, $start->toDateString(), $end->toDateString());
            })
            ->groupBy('expense_category_id')
            ->with('category:id,name')
            ->orderByDesc('total')
            ->limit(4)
            ->get()
            ->map(fn ($row) => [
                'category_id' => $row->expense_category_id,
                'category_name' => $row->category?->name ?? __('Unknown'),
                'total' => (float) $row->total,
                'entries' => (int) $row->entries,
            ])
            ->values();

        return Inertia::render('resources/expenses/Index', [
            'items' => $this->inertiaPagination($items),
            'categories' => $this->categoryOptions(activeOnly: false),
            'summary' => [
                'total_amount' => (float) $summaryQuery->sum('amount'),
                'entry_count' => (int) $summaryQuery->count(),
                'by_category' => $byCategory,
                'period_label' => $this->periodLabel($period, $start, $end),
            ],
            'filters' => [
                'period' => $period['type'],
                'date' => $period['date'],
                'week' => $period['week'],
                'month' => $period['month'],
                'date_from' => $period['date_from'],
                'date_to' => $period['date_to'],
                'category_id' => $request->query('category_id'),
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'expenses.create');

        return Inertia::render('resources/expenses/Create', [
            'categories' => $this->categoryOptions(activeOnly: true),
            'defaults' => [
                'expense_date' => now()->toDateString(),
                'period_end' => null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'expenses.create');

        Expense::query()->create($this->validated($request));

        return redirect()
            ->route('expenses.index')
            ->with('success', __('Created.'));
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $this->authorizePermission($request, 'expenses.edit');

        $expense->load('category:id,name');

        return Inertia::render('resources/expenses/Edit', [
            'item' => $this->serialize($expense),
            'categories' => $this->categoryOptions(activeOnly: false),
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizePermission($request, 'expenses.edit');

        $expense->update($this->validated($request));

        return redirect()
            ->route('expenses.index')
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizePermission($request, 'expenses.delete');

        $expense->delete();

        return redirect()
            ->route('expenses.index')
            ->with('success', __('Deleted.'));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'expenses.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $name = trim($validated['name']);
        $baseSlug = Str::slug($name) ?: 'category';
        $slug = $baseSlug;
        $suffix = 2;

        while (ExpenseCategory::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $existing = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        $category = $existing ?? ExpenseCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => ((int) ExpenseCategory::query()->max('sort_order')) + 10,
            'is_active' => true,
        ]);

        return response()->json([
            'category' => [
                'value' => $category->id,
                'label' => $category->name,
            ],
        ]);
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
            return [now()->startOfMonth(), now()->endOfMonth()];
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
    protected function categoryOptions(bool $activeOnly): array
    {
        return ExpenseCategory::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ExpenseCategory $category) => [
                'value' => $category->id,
                'label' => $category->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $validated = $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (empty($validated['period_end']) || $validated['period_end'] === $validated['expense_date']) {
            $validated['period_end'] = null;
        }

        return $validated;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Expense>  $query
     */
    protected function scopeOverlappingPeriod($query, string $start, string $end): void
    {
        $query->where('expense_date', '<=', $end)
            ->whereRaw('COALESCE(period_end, expense_date) >= ?', [$start]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialize(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_category_id' => $expense->expense_category_id,
            'category_name' => $expense->category?->name,
            'amount' => $expense->amount,
            'expense_date' => $expense->expense_date?->toDateString(),
            'period_end' => $expense->period_end?->toDateString(),
            'vendor' => $expense->vendor,
            'notes' => $expense->notes,
            'created_at' => $expense->created_at?->toIso8601String(),
            'updated_at' => $expense->updated_at?->toIso8601String(),
        ];
    }
}
