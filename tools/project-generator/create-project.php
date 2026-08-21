<?php

declare(strict_types=1);

use PeanutAdmin\ProjectGenerator\ProjectGeneratorCli;

require __DIR__ . '/src/ProjectGenerator.php';

exit(ProjectGeneratorCli::run(dirname(__DIR__, 2), array_slice($argv, 1)));
