<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use PDO;
use PeanutAdmin\Kernel\Identity\SelfService\AccountSelfService;
use think\Container;

final class TenantAccountRuntimeFactory
{
    private function __construct() {}

    public static function create(): AccountSelfService
    {
        return new AccountSelfService(Container::getInstance()->make(PDO::class));
    }
}
