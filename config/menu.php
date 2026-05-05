<?php

$prefix = 'nawasara-zoom';

return [
    [
        'label' => 'Zoom',
        'icon' => 'lucide-video',
        'url' => '',
        'permission' => 'zoom.user.view',
        'workspace' => 'communication',
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
