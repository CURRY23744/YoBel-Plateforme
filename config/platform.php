<?php

return [
    // Sécurité de connexion
    'max_login_attempts' => 5,
    'lockout_minutes' => 15,

    // Fichiers
    'storage_disk' => env('PLATFORM_DISK', 'local'),
    'upload_max_kb' => 20480, // 20 Mo
    'allowed_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'md',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'zip',
    ],

    // Discussions
    'message_edit_window_minutes' => 15,

    // Listes configurables
    'project_member_roles' => ['manager', 'developer', 'designer', 'tester', 'analyst', 'client_contact', 'other'],
    'milestone_statuses' => ['pending', 'in_progress', 'completed', 'missed'],
    'event_visibilities' => ['private', 'project', 'organization'],

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),
];
