<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\CriticalAlertNotification;
use App\Notifications\InAppNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyUser(User $user, string $title, ?string $body = null, string $type = 'info', ?string $actionUrl = null): void
    {
        $user->notify(new InAppNotification($title, $body, $type, $actionUrl));
    }

    public function notifyCritical(User $user, string $title, string $body, ?string $actionUrl = null, bool $mail = true): void
    {
        $user->notify(new CriticalAlertNotification($title, $body, $actionUrl, $mail));
    }

    /**
     * @param  iterable<int, User>  $users
     */
    public function notifyCriticalMany(iterable $users, string $title, string $body, ?string $actionUrl = null, bool $mail = true): void
    {
        foreach ($users as $user) {
            $this->notifyCritical($user, $title, $body, $actionUrl, $mail);
        }
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(User $user, int $limit = 20): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->format($notification));
    }

    public function markAsRead(User $user, string $id): bool
    {
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    public function markAsUnread(User $user, string $id): bool
    {
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return false;
        }

        $notification->forceFill(['read_at' => null])->save();

        return true;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function delete(User $user, string $id): bool
    {
        $notification = $user->notifications()->where('id', $id)->first();

        if (! $notification) {
            return false;
        }

        $notification->delete();

        return true;
    }

    public function clearAll(User $user): void
    {
        $user->notifications()->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function format(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? null,
            'type' => $data['type'] ?? 'info',
            'action_url' => $data['action_url'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
