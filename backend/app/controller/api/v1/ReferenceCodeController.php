<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\referencecode\ReferenceCodeRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\Request;
use think\Response;

final class ReferenceCodeController
{
    #[OpenApiHandlerContract]
    public function listReferenceCodeSets(Request $request): Response
    {
        return ReferenceCodeRuntimeFactory::listSets($request);
    }

    #[OpenApiHandlerContract]
    public function listReferenceCodes(Request $request, string $moduleKey, string $setKey): Response
    {
        return ReferenceCodeRuntimeFactory::listCodes($request, $moduleKey, $setKey);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function getReferenceCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
    ): Response {
        return ReferenceCodeRuntimeFactory::getCode($request, $moduleKey, $setKey, $code);
    }

    #[OpenApiHandlerContract(
        successStatus: 201,
        headers: OpenApiHandlerContract::CREATED_HEADERS,
    )]
    public function createReferenceCode(Request $request, string $moduleKey, string $setKey): Response
    {
        return ReferenceCodeRuntimeFactory::createCode($request, $moduleKey, $setKey);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function replaceReferenceCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
    ): Response {
        return ReferenceCodeRuntimeFactory::replaceCode($request, $moduleKey, $setKey, $code);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function retireReferenceCode(
        Request $request,
        string $moduleKey,
        string $setKey,
        string $code,
    ): Response {
        return ReferenceCodeRuntimeFactory::retireCode($request, $moduleKey, $setKey, $code);
    }
}
