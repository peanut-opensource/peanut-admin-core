<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'stores' => [
        'file' => [
            'type' => 'File',
            'path' => '',
            'prefix' => 'peanut_admin_starter:',
            'expire' => 0,
            'tag_prefix' => 'tag:',
            'serialize' => [],
        ],
    ],
];
