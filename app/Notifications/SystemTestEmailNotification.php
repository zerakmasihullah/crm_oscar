<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemTestEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $appName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Test email from '.$this->appName)
            ->greeting('Hello!')
            ->line('This is a test email from '.$this->appName.'.')
            ->line('If you received this message, outbound mail and your selected email template are working.')
            ->action('Open dashboard', url('/dashboard'))
            ->line('You can change the email template anytime in Settings → Email → Templates.');
    }
}
