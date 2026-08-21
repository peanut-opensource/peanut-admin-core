<?php

declare(strict_types=1);

use PeanutAdmin\App\command\BootstrapPlatformOwner;
use PeanutAdmin\App\command\TaskWorkerCommand;

return [
    'commands' => [
        BootstrapPlatformOwner::class,
        TaskWorkerCommand::class,
    ],
];
