<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Standard permission verbs
    |--------------------------------------------------------------------------
    */

    'verbs' => [
        'view',
        'create',
        'edit',
        'delete',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application modules
    |--------------------------------------------------------------------------
    */

    'modules' => [
        'dashboard' => [
            'extra' => [],
        ],
        'users' => [
            'extra' => [],
        ],
        'roles' => [
            'extra' => [],
        ],
        'settings' => [
            'extra' => [
                'manage',
            ],
        ],
        'backups' => [
            'extra' => [
                'run',
                'download',
                'restore',
            ],
        ],
        'storage' => [
            'extra' => [],
        ],
        'audit' => [
            'extra' => [],
        ],
        'trash' => [
            'extra' => [
                'restore',
            ],
        ],
        'field_reports' => [
            'extra' => [],
        ],
        'field_notes' => [
            'extra' => [],
        ],
        'orders' => [
            'extra' => [
                'accept',
                'print',
            ],
        ],
        'menus' => [
            'extra' => [],
        ],
        'menu_items' => [
            'extra' => [],
        ],
        'deliveries' => [
            'extra' => [],
        ],
        'discounts' => [
            'extra' => [],
        ],
        'reservations' => [
            'extra' => [],
        ],
        'promotions' => [
            'extra' => [
                'send',
            ],
        ],
        'promotion_recipients' => [
            'extra' => [],
        ],
        'oscar_users' => [
            'extra' => [],
        ],
        'chats' => [
            'extra' => [],
        ],
        'platforms' => [
            'extra' => [],
        ],
        'platform_revenues' => [
            'extra' => [],
        ],
        'expenses' => [
            'extra' => [],
        ],
        'analytics' => [
            'verbs' => [],
            'extra' => [
                'view',
            ],
        ],
        'ai' => [
            'verbs' => [],
            'extra' => [
                'view',
                'chat',
                'assist',
                'analytics',
                'manage',
            ],
        ],
    ],

];
