<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\FcmNotificationHelper;
use App\Http\Controllers\Concerns\AuthorizesMisPermissions;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    use AuthorizesMisPermissions;

    public function index(Request $request): Response
    {
        $this->authorizePermission($request, 'chats.view');

        $currentUserId = (int) $request->user()->id;
        $search = trim((string) $request->query('search', ''));
        $selectedUserId = $request->integer('user') ?: null;

        $users = $this->chatUsers($currentUserId, $search);

        if ($selectedUserId && ! $users->contains('id', $selectedUserId)) {
            $selectedUserId = null;
        }

        $messages = [];
        $selectedUser = null;

        if ($selectedUserId) {
            Chat::query()
                ->where('sender', $selectedUserId)
                ->where('receiver', $currentUserId)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $selectedUser = User::query()->find($selectedUserId);
            $messages = Chat::query()
                ->where(function ($query) use ($currentUserId, $selectedUserId) {
                    $query->where(function ($inner) use ($currentUserId, $selectedUserId) {
                        $inner->where('sender', $currentUserId)
                            ->where('receiver', $selectedUserId);
                    })->orWhere(function ($inner) use ($currentUserId, $selectedUserId) {
                        $inner->where('sender', $selectedUserId)
                            ->where('receiver', $currentUserId);
                    });
                })
                ->with(['senderUser:id,name', 'receiverUser:id,name'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (Chat $chat) => $this->serializeMessage($chat))
                ->all();

            $users = $this->chatUsers($currentUserId, $search);
        }

        return Inertia::render('resources/chats/Index', [
            'users' => $users->values()->all(),
            'selectedUserId' => $selectedUserId,
            'selectedUser' => $selectedUser ? [
                'id' => $selectedUser->id,
                'name' => $selectedUser->name,
                'email' => $selectedUser->email,
                'phone_number' => $selectedUser->phone_number,
            ] : null,
            'messages' => $messages,
            'filters' => [
                'search' => $search !== '' ? $search : null,
            ],
            'unreadTotal' => Chat::query()
                ->where('receiver', $currentUserId)
                ->where('is_read', false)
                ->count(),
            'authUserId' => $currentUserId,
        ]);
    }

    public function send(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'chats.create');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('chat-attachments', 'public');
        }

        Chat::query()->create([
            'sender' => $request->user()->id,
            'receiver' => $user->id,
            'message' => $validated['message'],
            'attachment' => $attachmentPath,
            'is_sent' => true,
            'is_delivered' => false,
            'is_read' => false,
        ]);

        try {
            $sender = $request->user();
            if ($sender && filled($user->fcm_token)) {
                FcmNotificationHelper::sendMessageNotification(
                    $user,
                    $sender,
                    $validated['message']
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send FCM notification for message', [
                'sender_id' => $request->user()->id,
                'receiver_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('chats.index', [
                'user' => $user->id,
                'search' => $request->query('search'),
            ])
            ->with('success', __('Sent.'));
    }

    public function markRead(Request $request, User $user): RedirectResponse
    {
        $this->authorizePermission($request, 'chats.edit');

        Chat::query()
            ->where('sender', $user->id)
            ->where('receiver', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return redirect()->route('chats.index', [
            'user' => $user->id,
            'search' => $request->query('search'),
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    protected function chatUsers(int $currentUserId, string $search)
    {
        $users = User::query()
            ->where('id', '!=', $currentUserId)
            ->withCount(['sentMessages as unread_count' => function ($query) use ($currentUserId) {
                $query->where('receiver', $currentUserId)
                    ->where('is_read', false);
            }])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->get();

        foreach ($users as $user) {
            $lastMessage = Chat::query()
                ->where(function ($query) use ($user, $currentUserId) {
                    $query->where(function ($inner) use ($user, $currentUserId) {
                        $inner->where('sender', $user->id)
                            ->where('receiver', $currentUserId);
                    })->orWhere(function ($inner) use ($user, $currentUserId) {
                        $inner->where('sender', $currentUserId)
                            ->where('receiver', $user->id);
                    });
                })
                ->orderByDesc('created_at')
                ->first();

            $user->last_message_time = $lastMessage?->created_at;
            $user->last_message_preview = $lastMessage?->message;
            $user->last_message_label = $lastMessage
                ? $this->formatMessageTime($lastMessage->created_at)
                : null;
        }

        return $users->sort(function ($a, $b) {
            if ($a->last_message_time && ! $b->last_message_time) {
                return -1;
            }
            if (! $a->last_message_time && $b->last_message_time) {
                return 1;
            }
            if ($a->last_message_time && $b->last_message_time) {
                $timeCompare = $b->last_message_time->timestamp <=> $a->last_message_time->timestamp;
                if ($timeCompare !== 0) {
                    return $timeCompare;
                }
            }
            if ($a->unread_count != $b->unread_count) {
                return $b->unread_count <=> $a->unread_count;
            }

            return strcasecmp((string) $a->name, (string) $b->name);
        })->values()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'unread_count' => (int) $user->unread_count,
            'last_message_preview' => $user->last_message_preview,
            'last_message_label' => $user->last_message_label,
            'last_message_at' => $user->last_message_time?->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(Chat $chat): array
    {
        return [
            'id' => $chat->id,
            'sender' => (int) $chat->sender,
            'receiver' => (int) $chat->receiver,
            'message' => $chat->message,
            'attachment' => $chat->attachment,
            'attachment_url' => $chat->attachment
                ? Storage::disk('public')->url($chat->attachment)
                : null,
            'is_read' => (bool) $chat->is_read,
            'created_at' => $chat->created_at?->toIso8601String(),
            'sender_name' => $chat->senderUser?->name,
        ];
    }

    protected function formatMessageTime($date): string
    {
        $date = Carbon::parse($date);

        if ($date->isToday()) {
            return $date->format('H:i');
        }
        if ($date->isYesterday()) {
            return 'Yesterday';
        }
        if ($date->isCurrentWeek()) {
            return $date->format('D');
        }

        return $date->format('M d');
    }
}
