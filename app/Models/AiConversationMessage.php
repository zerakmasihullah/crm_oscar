<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Ai\Models\ConversationMessage;

class AiConversationMessage extends ConversationMessage
{
    use SoftDeletes;
}
