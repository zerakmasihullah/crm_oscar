<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiAssistService;
use App\Support\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class AiAssistController extends Controller
{
    public function __invoke(Request $request, AiAssistService $assist): JsonResponse
    {
        abort_unless($request->user(), 401);
        abort_unless(AiSettings::enabled(), 503, 'AI assist is disabled.');
        abort_unless($request->user()->can('ai.assist'), 403);

        $validated = $request->validate([
            'action' => ['required', 'string', Rule::in(AiSettings::actionIds())],
            'text' => ['nullable', 'string', 'max:'.max(
                (int) config('ai.limits.max_input_chars', 2500),
                (int) config('ai.limits.max_form_schema_chars', 6000),
            )],
            'instruction' => ['nullable', 'string', 'max:'.((int) config('ai.limits.max_instruction_chars', 1000))],
            'model_id' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $result = $assist->assist(
                action: $validated['action'],
                text: (string) ($validated['text'] ?? ''),
                instruction: $validated['instruction'] ?? null,
                modelId: filled($validated['model_id'] ?? null) ? (string) $validated['model_id'] : null,
                userId: $request->user()?->id,
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (Throwable) {
            return response()->json([
                'message' => 'AI assist failed unexpectedly.',
            ], 502);
        }

        return response()->json([
            'text' => $result['text'],
            'model_id' => $result['model_id'],
            'usage' => $result['usage'],
            'duration_ms' => $result['duration_ms'],
            'estimated_cost_usd' => $result['estimated_cost_usd'],
            'log_id' => $result['log_id'],
            'fallback_used' => $result['fallback_used'] ?? false,
        ]);
    }
}
