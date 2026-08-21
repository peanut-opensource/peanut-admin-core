<?php

declare(strict_types=1);

namespace PeanutAdmin\InternalStarter;

use think\exception\Handle;
use think\Request;
use think\Response;
use Throwable;

final class StarterExceptionHandler extends Handle
{
    public function render(Request $request, Throwable $exception): Response
    {
        error_log('Peanut Admin internal starter request failed.');

        return Response::create([
            'error' => 'STARTER_REQUEST_FAILED',
        ], 'json', 500);
    }
}
