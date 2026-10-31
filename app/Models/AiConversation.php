<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Ai\Models\Conversation;

class AiConversation extends Conversation
{
    use SoftDeletes;
    use Trashable;

    public function trashTitle(): string
    {
        $title = $this->title;

        if (is_string($title) && $title !== '') {
            return mb_substr($title, 0, 120);
        }

        return 'Chat #'.mb_substr((string) $this->getKey(), 0, 8);
    }

    public function purgeTrashAssets(): void
    {
        AiConversationMessage::query()
            ->withTrashed()
            ->where('conversation_id', $this->getKey())
            ->each(function (AiConversationMessage $message): void {
                $message->forceDelete();
            });
    }

    protected static function bootAiConversation(): void
    {
        static::deleting(function (AiConversation $conversation): void {
            if ($conversation->isForceDeleting()) {
                return;
            }

            AiConversationMessage::query()
                ->where('conversation_id', $conversation->getKey())
                ->each(fn (AiConversationMessage $message) => $message->delete());
        });

        static::restoring(function (AiConversation $conversation): void {
            AiConversationMessage::query()
                ->onlyTrashed()
                ->where('conversation_id', $conversation->getKey())
                ->each(fn (AiConversationMessage $message) => $message->restore());
        });
    }
}
