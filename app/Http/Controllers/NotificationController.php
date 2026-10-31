<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $this->notifications->recent($user),
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->notifications->markAsRead($user, $id);

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function markAsUnread(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->notifications->markAsUnread($user, $id);

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->notifications->markAllAsRead($user);

        return response()->json(['unread_count' => 0]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $this->notifications->delete($user, $id);

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($user),
        ]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->notifications->clearAll($user);

        return response()->json(['unread_count' => 0]);
    }
}
