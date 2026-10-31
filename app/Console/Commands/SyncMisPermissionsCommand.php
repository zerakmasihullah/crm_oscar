<?php

namespace App\Console\Commands;

use App\Services\MisPermissionRegistrar;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use App\Models\Role;

class SyncMisPermissionsCommand extends Command
{
    protected $signature = 'mis:sync-permissions';

    protected $description = 'Sync Spatie permissions from config/mis_permissions.php';

    public function handle(MisPermissionRegistrar $registrar): int
    {
        $permissions = $registrar->sync();

        $all = Permission::query()
            ->whereIn('name', $registrar->definedPermissionNames())
            ->get();

        foreach (['Owner', 'Admin'] as $roleName) {
            $role = Role::findOrCreate($roleName);
            $role->syncPermissions($all);
        }

        $this->info('Synced '.count($permissions).' permissions.');

        return self::SUCCESS;
    }
}
