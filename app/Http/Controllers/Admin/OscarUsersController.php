<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Concerns\PaginatesInertia;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderStatuses;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OscarUsersController extends Controller
{
    use AuthorizesMisPermissions;
    use PaginatesInertia;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'oscar_users.view');

        $search = trim((string) $request->query('search', ''));
        $isActive = $request->query('is_active');

        $users = $this->customers()
            ->withCount('orders')
            ->withSum([
                'orders as total_spent' => fn ($query) => $query->where('status', '!=', OrderStatuses::REJECTED),
            ], 'total_price')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->when($isActive !== null && $isActive !== '', function ($query) use ($isActive) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->serializeUser($user));

        return Inertia::render('resources/oscar-users/Index', [
            'items' => $this->inertiaPagination($users),
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'is_active' => $isActive === null || $isActive === ''
                    ? null
                    : filter_var($isActive, FILTER_VALIDATE_BOOLEAN),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizePermission($request, 'oscar_users.create');

        return Inertia::render('resources/oscar-users/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'oscar_users.create');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'role' => User::ROLE_CUSTOMER,
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('oscar-users.show', $user)
            ->with('success', __('Created.'));
    }

    public function show(Request $request, User $user): Response
    {
        $this->authorizePermission($request, 'oscar_users.view');
        $this->ensureCustomer($user);

        $ordersQuery = $user->orders();

        $orders = (clone $ordersQuery)
            ->with(['deliveryman:id,name', 'order_items.menu_item:id,title'])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Order $order) => [
                'id' => $order->id,
                'status' => (int) $order->status,
                'status_label' => OrderStatuses::label((int) $order->status),
                'type' => (int) $order->type,
                'type_label' => OrderStatuses::typeLabel((int) $order->type),
                'source_label' => $order->source_label,
                'total_price' => (float) $order->total_price,
                'payment_method' => $order->payment_method,
                'phone_number' => $order->phone_number,
                'address' => $order->address,
                'deliveryman' => $order->deliveryman?->name,
                'items_summary' => $order->order_items
                    ->map(fn ($item) => ($item->menu_item?->title ?? 'Item').' × '.$item->quantity)
                    ->implode(', '),
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        $user->loadCount('orders');
        $user->loadSum([
            'orders as total_spent' => fn ($query) => $query->where('status', '!=', OrderStatuses::REJECTED),
        ], 'total_price');

        return Inertia::render('resources/oscar-users/Show', [
            'item' => $this->serializeUser($user, detailed: true),
            'stats' => $this->customerStats($user),
            'orders' => $this->inertiaPagination($orders),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorizePermission($request, 'oscar_users.edit');
        $this->ensureCustomer($user);

        return Inertia::render('resources/oscar-users/Edit', [
            'item' => $this->serializeUser($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'oscar_users.edit');
        $this->ensureCustomer($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone_number' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone_number' => $validated['phone_number'] ?? null,
            'role' => User::ROLE_CUSTOMER,
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if ($request->has('is_active')) {
            if ($request->boolean('is_active')) {
                $user->enable($request->user());
            } else {
                $user->disable($request->user());
            }
        }

        $user->save();

        return redirect()
            ->route('oscar-users.show', $user)
            ->with('success', __('Updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'oscar_users.delete');
        $this->ensureCustomer($user);

        $user->delete();

        return redirect()
            ->route('oscar-users.index')
            ->with('success', __('Deleted.'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'oscar_users.edit');
        $this->ensureCustomer($user);

        if ($user->is_active) {
            $user->disable($request->user());
        } else {
            $user->enable($request->user());
        }

        return back()->with('success', __('Updated.'));
    }

    /**
     * @return Builder<User>
     */
    protected function customers(): Builder
    {
        return User::query()->where('role', User::ROLE_CUSTOMER);
    }

    protected function ensureCustomer(User $user): void
    {
        abort_unless((int) $user->role === User::ROLE_CUSTOMER, 404);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeUser(User $user, bool $detailed = false): array
    {
        $data = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'role' => (int) $user->role,
            'role_label' => $user->getOscarRoleName(),
            'is_active' => (bool) $user->is_active,
            'orders_count' => (int) ($user->orders_count ?? 0),
            'total_spent' => round((float) ($user->total_spent ?? 0), 2),
            'fcm_token' => filled($user->fcm_token),
            'area_code' => $user->area_code,
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['email_verified_at'] = $user->email_verified_at?->toIso8601String();
            $data['disabled_at'] = $user->disabled_at?->toIso8601String();
            $data['image'] = $user->image;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerStats(User $user): array
    {
        $orders = $user->orders()->get([
            'id', 'status', 'type', 'total_price', 'payment_method',
            'description', 'address', 'phone_number', 'longitude', 'latitude', 'created_at',
        ]);

        $nonRejected = $orders->where('status', '!=', OrderStatuses::REJECTED);
        $completed = $orders->where('status', OrderStatuses::COMPLETED);

        $sourceCounts = [
            'website' => 0,
            'mobile' => 0,
            'admin' => 0,
        ];
        foreach ($orders as $order) {
            $key = $order->source_key;
            if (! isset($sourceCounts[$key])) {
                $sourceCounts[$key] = 0;
            }
            $sourceCounts[$key]++;
        }
        arsort($sourceCounts);
        $topSource = array_key_first($sourceCounts);
        $topSourceCount = $sourceCounts[$topSource] ?? 0;

        $typeCounts = [
            'delivery' => $orders->where('type', 1)->count(),
            'collection' => $orders->where('type', 2)->count(),
        ];

        $statusCounts = collect(OrderStatuses::labels())->mapWithKeys(
            fn ($label, $status) => [$label => $orders->where('status', $status)->count()]
        )->all();

        $paymentCounts = $orders
            ->groupBy(fn ($o) => $o->payment_method ?: 'unknown')
            ->map->count()
            ->sortDesc()
            ->all();

        $addresses = $orders
            ->pluck('address')
            ->filter(fn ($a) => filled($a))
            ->countBy()
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $address) => ['address' => $address, 'count' => $count])
            ->values()
            ->all();

        $topItems = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->where('orders.customer_id', $user->id)
            ->select(
                'menu_items.title',
                DB::raw('SUM(order_items.quantity) as sold'),
                DB::raw('SUM(order_items.price * order_items.quantity) as revenue'),
            )
            ->groupBy('menu_items.id', 'menu_items.title')
            ->orderByDesc('sold')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'title' => $row->title,
                'sold' => (int) $row->sold,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();

        $avgOrder = $nonRejected->count() > 0
            ? round($nonRejected->avg('total_price'), 2)
            : 0;

        return [
            'orders_count' => $orders->count(),
            'completed_count' => $completed->count(),
            'pending_count' => $orders->where('status', OrderStatuses::PENDING)->count(),
            'rejected_count' => $orders->where('status', OrderStatuses::REJECTED)->count(),
            'total_spent' => round((float) $nonRejected->sum('total_price'), 2),
            'avg_order' => $avgOrder,
            'first_order_at' => optional($orders->sortBy('created_at')->first()?->created_at)?->toIso8601String(),
            'last_order_at' => optional($orders->sortByDesc('created_at')->first()?->created_at)?->toIso8601String(),
            'source_counts' => $sourceCounts,
            'top_source' => $topSource,
            'top_source_label' => match ($topSource) {
                'mobile' => 'Mobile App',
                'admin' => 'Admin',
                default => 'Website',
            },
            'top_source_share' => $orders->count() > 0
                ? round(($topSourceCount / $orders->count()) * 100)
                : 0,
            'type_counts' => $typeCounts,
            'status_counts' => $statusCounts,
            'payment_counts' => $paymentCounts,
            'top_addresses' => $addresses,
            'top_items' => $topItems,
            'has_push' => filled($user->fcm_token),
        ];
    }
}
