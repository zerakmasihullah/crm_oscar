<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class InAppNotification extends Notification
{
    public function __construct(
        public string $title,
        public ?string $body = null,
        public string $type = 'info',
        public ?string $actionUrl = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'action_url' => $this->actionUrl,
        ];
    }
}
