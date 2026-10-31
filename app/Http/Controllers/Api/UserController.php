<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesJson;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use PaginatesJson;

    public function index(Request $request): JsonResponse
    {
        abort_unless(config('kit.features.api'), 404);
        abort_unless($request->user()?->can('users.view') || $request->user()?->hasRole('Owner'), 403);

        $users = User::query()
            ->with('roles:id,name')
            ->orderBy('name')
            ->paginate(min(100, max(1, $request->integer('per_page', 20))));

        $users->getCollection()->transform(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name')->values()->all(),
        ]);

        return response()->json([
            'success' => true,
            ...$this->paginated($users),
        ]);
    }
}
