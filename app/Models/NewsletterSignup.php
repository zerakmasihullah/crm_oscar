<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSignup extends Model
{
    protected $fillable = [
        'email',
        'unsubscribed',
        'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed' => 'boolean',
        'unsubscribed_at' => 'datetime',
    ];
}
