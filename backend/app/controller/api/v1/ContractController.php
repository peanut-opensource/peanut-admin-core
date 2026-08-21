<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\Kernel\Api\ApiException;

final class ContractController
{
    public function handle(): never
    {
        throw new ApiException(
            'API_OPERATION_UNAVAILABLE',
            503,
            'This contract operation is unavailable in the current runtime profile.',
        );
    }
}
