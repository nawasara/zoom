<?php

namespace Nawasara\Zoom\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions kept as flat list — spatie/permission's `permissions`
        // table has no `description` column by default, so the human-readable
        // labels that used to ride along here live in the README's
        // Permissions section instead. Anyone needing UI labels should look
        // them up at render time.
        $permissions = [
            'zoom.view',
            'zoom.user.view',
            'zoom.meeting.view',
            'zoom.meeting.create',
            'zoom.meeting.update',
            'zoom.meeting.delete',
            'zoom.recording.view',
            'zoom.recording.download',
            'zoom.recording.delete',
            'zoom.sync.execute',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }
    }
}
