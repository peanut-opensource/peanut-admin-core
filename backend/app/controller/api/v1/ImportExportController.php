<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\importexport\ImportExportHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class ImportExportController
{
    public function __construct(private readonly ImportExportHttpRuntime $runtime) {}

    #[OpenApiHandlerContract] public function index(Request $r): Response
    {
        return $this->runtime->index($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function show(Request $r, string $operationKey): Response
    {
        return $this->runtime->show($r, $operationKey);
    }
    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function submitImport(Request $r): Response
    {
        return $this->runtime->submitImport($r);
    }
    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function submitExport(Request $r): Response
    {
        return $this->runtime->submitExport($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function cancel(Request $r, string $operationKey): Response
    {
        return $this->runtime->cancel($r, $operationKey);
    }
}
