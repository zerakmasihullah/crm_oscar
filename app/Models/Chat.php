<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'chat';
    protected $fillable = [
        'sender',
        'receiver',
        'message',
        'attachment',
        'is_sent',
        'is_delivered',
        'is_read',
    ];

    protected $casts = [
        'is_sent' => 'boolean',
        'is_delivered' => 'boolean',
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function senderUser()
    {
        return $this->belongsTo(User::class, 'sender');
    }

    public function receiverUser()
    {
        return $this->belongsTo(User::class, 'receiver');
    }
}
