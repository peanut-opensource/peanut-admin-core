<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\importexport\ImportExportHttpRuntime;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class ImportExportController
{
    #[OpenApiHandlerContract] public function index(Request $r): Response
    {
        return ImportExportHttpRuntime::index($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function show(Request $r, string $operationKey): Response
    {
        return ImportExportHttpRuntime::show($r, $operationKey);
    }
    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function submitImport(Request $r): Response
    {
        return ImportExportHttpRuntime::submitImport($r);
    }
    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function submitExport(Request $r): Response
    {
        return ImportExportHttpRuntime::submitExport($r);
    }
    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)] public function cancel(Request $r, string $operationKey): Response
    {
        return ImportExportHttpRuntime::cancel($r, $operationKey);
    }
}
