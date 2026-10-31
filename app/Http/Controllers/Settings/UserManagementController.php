<?php

namespace App\Http\Controllers\Settings;

use App\Concerns\ExportsSpreadsheet;
use App\Concerns\RendersPrintView;
use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreUserManagementRequest;
use App\Http\Requests\Settings\UpdateUserManagementRequest;
use App\Models\User;
use App\Services\Ai\PrivilegeGrantGuard;
use App\Support\EfficientQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use App\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserManagementController extends Controller
{
    use AuthorizesMisPermissions;
    use ExportsSpreadsheet;
    use RendersPrintView;

    public function __construct(
        private PrivilegeGrantGuard $privilegeGrantGuard,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorizePermission($request, 'users.view');

        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with('roles:id,name')
            ->when($search, function ($q) use ($search) {
                EfficientQuery::applySearch(
                    $q,
                    $search,
                    prefixColumns: ['name', 'email'],
                    exactColumns: ['email'],
                );
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('settings/Users/Index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search ?: null],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorizePermission($request, 'users.create');

        return Inertia::render('settings/Users/Create', [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserManagementRequest $request): RedirectResponse
    {
        $this->authorizePermission($request, 'users.create');

        $validated = $request->validated();
        $roles = $validated['roles'] ?? [];
        unset($validated['roles']);

        /** @var User $actor */
        $actor = $request->user();
        try {
            $this->privilegeGrantGuard->assertRolesAssignable($actor, $roles);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['roles' => $e->getMessage()]);
        }

        $user = User::query()->create([
            ...$validated,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if ($roles !== []) {
            $user->syncRoles($roles);
        }

        return redirect()
            ->route('settings.users.index')
            ->with('success', __('User created.'));
    }

    public function update(UpdateUserManagementRequest $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'users.edit');

        if ($user->id === $request->user()->id && $request->has('is_active') && ! $request->boolean('is_active')) {
            return back()->withErrors(['user' => 'You cannot disable your own account.']);
        }

        $validated = $request->validated();

        if (array_key_exists('roles', $validated)) {
            /** @var User $actor */
            $actor = $request->user();
            try {
                $this->privilegeGrantGuard->assertRolesAssignable($actor, $validated['roles'] ?? []);
            } catch (\InvalidArgumentException $e) {
                return back()->withErrors(['roles' => $e->getMessage()]);
            }
            $user->syncRoles($validated['roles'] ?? []);
            unset($validated['roles']);
        }

        if (array_key_exists('is_active', $validated)) {
            if ($validated['is_active']) {
                $user->enable($request->user());
            } else {
                $user->disable($request->user());
            }
            unset($validated['is_active']);
        }

        $passwordChanged = false;

        if (array_key_exists('password', $validated)) {
            if (filled($validated['password'])) {
                $passwordChanged = true;
            } else {
                unset($validated['password']);
            }
        }

        unset($validated['password_confirmation']);

        if ($validated !== []) {
            $user->update($validated);
        }

        if ($passwordChanged) {
            $user->forceFill([
                'session_version' => ((int) $user->session_version) + 1,
            ])->save();
        }

        return back()->with(
            'success',
            $passwordChanged ? __('Password updated.') : __('User updated.'),
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'users.delete');

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return back()->with('success', __('User deleted.'));
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'users.edit');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'is_active' => ['required', 'boolean'],
        ]);

        $currentId = $request->user()->id;

        User::query()
            ->whereIn('id', $validated['ids'])
            ->where('id', '!=', $currentId)
            ->update(['is_active' => $validated['is_active']]);

        return back()->with(
            'success',
            $validated['is_active']
                ? __('Users enabled.')
                : __('Users disabled.'),
        );
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizePermission($request, 'users.delete');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $currentId = $request->user()->id;

        User::query()
            ->whereIn('id', $validated['ids'])
            ->where('id', '!=', $currentId)
            ->get()
            ->each(fn (User $user) => $user->delete());

        return back()->with('success', __('Users deleted.'));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless(config('kit.features.exports'), 404);
        $this->authorizePermission($request, 'users.view');

        $validated = $request->validate([
            'format' => ['nullable', 'string', Rule::in(['csv', 'xlsx', 'xls', 'excel'])],
        ]);

        $format = $validated['format'] ?? 'csv';

        $rows = User::query()
            ->with('roles:id,name')
            ->orderBy('id')
            ->lazyById(500)
            ->map(fn (User $user) => [
                $user->id,
                $user->name,
                $user->email,
                $user->is_active ? 'active' : 'disabled',
                $user->roles->pluck('name')->implode('|'),
            ]);

        return $this->streamExport('users', ['id', 'name', 'email', 'status', 'roles'], $rows, $format);
    }

    public function print(Request $request): Response
    {
        $this->authorizePermission($request, 'users.view');

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return $this->printView('print.users', [
            'users' => $users,
        ]);
    }
}
