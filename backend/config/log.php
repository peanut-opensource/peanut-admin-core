<?php

declare(strict_types=1);

return [
    'default' => 'file',
    'level' => [],
    'type_channel' => [],
    'close' => false,
    'processor' => null,
    'channels' => [
        'file' => [
            'type' => 'File',
            'path' => '',
            'single' => false,
            'apart_level' => [],
            'max_files' => 30,
            'json' => true,
            'processor' => null,
            'close' => false,
            'realtime_write' => false,
        ],
    ],
];
