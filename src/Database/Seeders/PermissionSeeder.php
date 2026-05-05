<?php

namespace Nawasara\Zoom\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'zoom.view' => 'View Zoom module',
            'zoom.user.view' => 'View Zoom users',
            'zoom.meeting.view' => 'View meetings',
            'zoom.meeting.create' => 'Create meetings',
            'zoom.meeting.update' => 'Update meetings',
            'zoom.meeting.delete' => 'Delete meetings',
            'zoom.recording.view' => 'View recordings',
            'zoom.recording.download' => 'Download recordings',
            'zoom.recording.delete' => 'Delete recordings',
            'zoom.sync.execute' => 'Execute Zoom sync jobs',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web', 'description' => $description]
            );
        }
    }
}
