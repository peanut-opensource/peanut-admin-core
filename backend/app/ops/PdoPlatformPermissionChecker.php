<?php

declare(strict_types=1);

namespace PeanutAdmin\App\ops;

use PDO;
use PeanutAdmin\Kernel\Authorization\RevisionPermissionCache;
use PeanutAdmin\Kernel\Context\PlatformContext;
use PeanutAdmin\Kernel\Platform\Authorization\PdoPlatformAuthorizationRepository;
use PeanutAdmin\Kernel\Platform\Authorization\PlatformAuthorizationEvaluator;
use PeanutAdmin\OpsConsole\Application\PlatformPermissionChecker;

final readonly class PdoPlatformPermissionChecker implements PlatformPermissionChecker
{
    private PlatformAuthorizationEvaluator $evaluator;
    public function __construct(PDO $pdo)
    {
        $this->evaluator = new PlatformAuthorizationEvaluator(new PdoPlatformAuthorizationRepository($pdo), new RevisionPermissionCache());
    }
    public function allows(PlatformContext $context, string $permissionKey): bool
    {
        return $this->evaluator->allows($context, $permissionKey);
    }
}
