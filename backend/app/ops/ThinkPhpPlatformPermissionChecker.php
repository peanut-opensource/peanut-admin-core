<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\OpsConsole\Application\PlatformPermissionChecker;

final readonly class ThinkPhpPlatformPermissionChecker implements PlatformPermissionChecker
{
    public function __construct(private PlatformAuthorizationEvaluator $evaluator) {}

    public function allows(PlatformContext $context, string $permissionKey): bool
    {
        return $this->evaluator->allows($context, $permissionKey);
    }
}
