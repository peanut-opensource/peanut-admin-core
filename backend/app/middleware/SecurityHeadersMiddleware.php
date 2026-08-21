<?php

declare(strict_types=1);

namespace PeanutAdmin\App\middleware;

use Closure;
use think\facade\Config;
use think\Request;
use think\Response;

final readonly class SecurityHeadersMiddleware
{
    /** @param array<string, string>|null $headers */
    public function __construct(private ?array $headers = null) {}

    public function handle(Request $request, Closure $next): Response
    {
        $headers = $this->headers ?? Config::get('security.headers', []);
        if (!is_array($headers) || $headers === []) {
            throw new \RuntimeException('SECURITY_HEADERS_UNAVAILABLE: restrictive response headers are required.');
        }

        return $next($request)->header($headers);
    }
}
