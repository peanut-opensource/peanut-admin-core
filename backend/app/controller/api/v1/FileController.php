<?php

declare(strict_types=1);

namespace PeanutAdmin\App\controller\api\v1;

use PeanutAdmin\App\filemedia\FileDeliveryHttpRuntime;
use PeanutAdmin\App\filemedia\FileRuntimeFactory;
use PeanutAdmin\Kernel\Api\OpenApiHandlerContract;
use think\db\PDOConnection;
use think\Request;
use think\Response;

final class FileController
{
    public function __construct(private readonly PDOConnection $connection) {}

    #[OpenApiHandlerContract]
    public function index(Request $request): Response
    {
        return FileRuntimeFactory::list($request, $this->connection);
    }

    #[OpenApiHandlerContract(successStatus: 201, headers: OpenApiHandlerContract::CREATED_HEADERS)]
    public function create(Request $request): Response
    {
        return FileRuntimeFactory::upload($request, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function show(Request $request, string $fileKey): Response
    {
        return FileRuntimeFactory::detail($request, $fileKey, $this->connection);
    }

    #[OpenApiHandlerContract(hasJsonBody: false, headers: [
        'Cache-Control',
        'Content-Disposition',
        'Content-Length',
        'Content-Type',
        'X-Content-Type-Options',
        'X-Request-Id',
    ])]
    public function content(Request $request, string $fileKey): Response
    {
        return FileRuntimeFactory::download($request, $fileKey, $this->connection);
    }

    #[OpenApiHandlerContract(headers: OpenApiHandlerContract::VERSIONED_HEADERS)]
    public function archive(Request $request, string $fileKey): Response
    {
        return FileRuntimeFactory::archive($request, $fileKey, $this->connection);
    }

    #[OpenApiHandlerContract]
    public function assets(Request $request): Response
    {
        return FileDeliveryHttpRuntime::assets($request, $this->connection);
    }

    #[OpenApiHandlerContract(successStatus: 201)]
    public function grant(Request $request, string $fileKey): Response
    {
        return FileDeliveryHttpRuntime::grant($request, $fileKey, $this->connection);
    }

    #[OpenApiHandlerContract(hasJsonBody: false, headers: [
        'Cache-Control', 'Content-Disposition', 'Content-Length', 'Content-Type', 'X-Content-Type-Options', 'X-Request-Id',
    ])]
    public function deliver(Request $request, string $fileKey): Response
    {
        return FileDeliveryHttpRuntime::deliver($request, $fileKey, $this->connection);
    }
}
