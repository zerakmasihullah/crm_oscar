<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    // The table associated with the model (optional if following naming convention)
    protected $table = 'menus';

    // Define which fields are mass assignable
    protected $fillable = [
        'title',
        'description',
        'image',
    ];



    public function menu_items()
    {
        return $this->hasMany(MenuItem::class);
    }
}
