<?php

namespace App\Listeners;

use App\Services\EmailLogService;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Mail\Events\MessageSent;

class LogOutboundMail
{
    public function __construct(
        protected EmailLogService $emailLogService,
    ) {}

    public function handleMessageSent(MessageSent $event): void
    {
        try {
            $this->emailLogService->recordSent($event);
        } catch (\Throwable) {
            // Never break mail delivery because of audit logging.
        }
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(MessageSent::class, [self::class, 'handleMessageSent']);
    }
}
