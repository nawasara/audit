<?php

namespace Nawasara\Audit\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'audit.log.view',
            'audit.log.delete',
            'audit.log.export',
            'audit.login.view',
            'audit.settings.manage',

            // Impersonation log (admin launch-as audit). Sensitive — terpisah
            // dari audit.log.view supaya bisa di-grant ke role tertentu
            // (mis. atasan/auditor) tanpa kasih full audit access.
            'audit.impersonation.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Assign semua audit permissions ke role developer (jika ada)
        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
