<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiChatService;
use App\Support\AiSdkBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use RuntimeException;
use Throwable;

class AiChatController extends Controller
{
    public function conversations(Request $request, AiChatService $chat): JsonResponse
    {
        abort_unless($request->user(), 401);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'cursor' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        try {
            return response()->json(
                $chat->conversations(
                    user: $request->user(),
                    search: $validated['q'] ?? null,
                    cursor: $validated['cursor'] ?? null,
                    limit: (int) ($validated['limit'] ?? 12),
                ),
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }
    }

    public function show(Request $request, string $conversation, AiChatService $chat): JsonResponse
    {
        abort_unless($request->user(), 401);

        try {
            return response()->json(
                $chat->conversation($request->user(), $conversation),
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found')
                ? 404
                : (str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }
    }

    public function destroy(Request $request, string $conversation, AiChatService $chat): JsonResponse
    {
        abort_unless($request->user(), 401);

        try {
            $chat->deleteConversation($request->user(), $conversation);

            return response()->json(['ok' => true]);
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found')
                ? 404
                : (str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        }
    }

    public function chat(Request $request, AiChatService $chat): JsonResponse
    {
        abort_unless($request->user(), 401);

        $maxFiles = (int) config('ai.chat_attachments.max_files', 5);
        $maxKb = (int) config('ai.chat_attachments.max_kb', 10_240);

        $maxChars = (int) config('ai.limits.max_chat_message_chars', 4000);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:'.$maxChars],
            'conversation_id' => ['nullable', 'string', 'max:64'],
            'context' => ['nullable', 'array'],
            'context.label' => ['nullable', 'string', 'max:120'],
            'context.path' => ['nullable', 'string', 'max:240'],
            'context.resource' => ['nullable', 'string', 'max:64'],
            'context.record_id' => ['nullable', 'integer', 'min:1'],
            'files' => ['nullable', 'array', 'max:'.$maxFiles],
            'files.*' => [
                'file',
                'max:'.$maxKb,
                'mimetypes:'.implode(',', \App\Services\Ai\AiChatAttachmentService::allowedMimes()),
            ],
        ]);

        $message = trim((string) ($validated['message'] ?? ''));
        $uploads = $request->file('files', []);
        if (! is_array($uploads)) {
            $uploads = $uploads ? [$uploads] : [];
        }
        $uploads = array_values(array_filter($uploads));

        if ($message === '' && $uploads === []) {
            return response()->json([
                'message' => 'Provide a message or attach at least one file.',
            ], 422);
        }

        if ($message === '') {
            $message = 'Please review the attached file(s) and follow my request if stated in the filenames; otherwise summarize and suggest useful next actions.';
        }

        try {
            $result = $chat->chat(
                user: $request->user(),
                message: $message,
                conversationId: $validated['conversation_id'] ?? null,
                uploads: $uploads,
                pageContext: is_array($validated['context'] ?? null) ? $validated['context'] : null,
            );
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => AiSdkBridge::userMessage($exception),
            ], 502);
        }

        return response()->json($result);
    }

    public function approve(Request $request, AiChatService $chat): JsonResponse
    {
        abort_unless($request->user(), 401);

        $validated = $request->validate([
            'conversation_id' => ['required', 'string', 'max:64'],
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required'],
        ]);

        try {
            $result = $chat->approve(
                user: $request->user(),
                conversationId: $validated['conversation_id'],
                decisions: $validated['decisions'],
            );
        } catch (ApprovalMismatchException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'pending_approvals' => $exception->pendingApprovals->values()->toArray(),
            ], 409);
        } catch (RuntimeException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
            ], $status);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => AiSdkBridge::userMessage($exception),
            ], 502);
        }

        return response()->json($result);
    }
}
