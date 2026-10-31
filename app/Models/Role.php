<?php

namespace App\Models;

use App\Concerns\Trashable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use SoftDeletes;
    use Trashable;
}
