<?php

$prefix = 'nawasara-audit';

return [
    [
        'label' => 'Audit Trail',
        'icon' => 'lucide-shield-check',
        'url' => '',
        'permission' => 'audit.log.view',
        'submenu' => [
            [
                'label' => 'Activity Log',
                'icon' => 'lucide-file-text',
                'url' => url($prefix.'/activity-log'),
                'permission' => 'audit.log.view',
                'navigate' => true,
            ],
            [
                'label' => 'Login History',
                'icon' => 'lucide-log-in',
                'url' => url($prefix.'/login-history'),
                'permission' => 'audit.login.view',
                'navigate' => true,
            ],
        ],
    ],
];
