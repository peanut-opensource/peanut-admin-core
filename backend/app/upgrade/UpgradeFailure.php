<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

use RuntimeException;

final class UpgradeFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('Upgrade preflight failed.');
    }
}
