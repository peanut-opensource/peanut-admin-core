<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([
        __DIR__ . '/../backend',
        __DIR__ . '/../packages/php',
    ]);

return (new Config())
    ->setCacheFile(__DIR__ . '/../.cache/php-cs-fixer.cache')
    ->setRiskyAllowed(false)
    ->setRules([
        '@PER-CS2.0' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
    ])
    ->setFinder($finder);
