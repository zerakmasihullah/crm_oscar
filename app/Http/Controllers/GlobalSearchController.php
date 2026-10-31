<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = trim((string) ($validated['q'] ?? ''));
        $user = $request->user();

        abort_unless($user !== null, 401);

        return response()->json([
            'query' => $query,
            'groups' => $search->search($user, $query),
        ]);
    }
}
