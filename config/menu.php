<?php

$prefix = 'nawasara-zoom';

return [
    [
        'label' => 'Zoom',
        'icon' => 'lucide-video',
        'group' => 'Layanan',
        'url' => '',
        // Workspace gate pakai zoom.view (permission paling dasar) — bukan
        // zoom.user.view. Tidak semua deployment punya scope Zoom user:*;
        // submenu tetap di-gate permission spesifiknya masing-masing, jadi
        // entri yang tak ter-akses otomatis tidak tampil.
        'permission' => 'zoom.view',
        'workspace' => 'zoom',
        'submenu' => [
            [
                'label' => 'Users',
                'icon' => 'lucide-users',
                'url' => url($prefix.'/users'),
                'permission' => 'zoom.user.view',
                'navigate' => true,
            ],
            [
                'label' => 'Meetings',
                'icon' => 'lucide-calendar',
                'url' => url($prefix.'/meetings'),
                'permission' => 'zoom.meeting.view',
                'navigate' => true,
            ],
            [
                'label' => 'Recordings',
                'icon' => 'lucide-film',
                'url' => url($prefix.'/recordings'),
                'permission' => 'zoom.recording.view',
                'navigate' => true,
            ],
        ],
    ],
];
