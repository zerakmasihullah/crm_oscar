<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Topping extends Model
{
    protected $fillable = [
        'menu_item_id',  // Foreign key to the menu_item table
        'name',          // Name of the topping (e.g., Cheese, Bacon, etc.)
        'required',      // Whether this topping is required (0 = no, 1 = yes)
        'is_optional',   // Whether this topping is optional (true or false)
    ];

    protected function casts(): array
    {
        return [
            'required' => 'integer',
            'is_optional' => 'boolean',
        ];
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function attachments()
    {
        return $this->hasMany(MenuAttachment::class);
    }
}
