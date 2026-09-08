<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Contract;

use DateTimeImmutable;
use PeanutAdmin\App\controller\api\v1\MemberController;
use PeanutAdmin\Kernel\Auth\TenantContext;
use PeanutAdmin\Kernel\Auth\ValidatedTenantSession;
use PeanutAdmin\Kernel\Membership\Application\MemberAdminService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use think\Request;

/** Keeps the HTTP guard and advertised member-role bound aligned with Core. */
final class MemberRoleLimitTest extends TestCase
{
    public function testHttpRejectsOversizedInputBeforeConstructingTheDatabaseService(): void
    {
        $context = TenantContext::fromValidatedSession(new ValidatedTenantSession(
            1,
            'session',
            9,
            10,
            11,
            'web',
            new DateTimeImmutable('2026-07-16T12:00:00Z'),
            1,
        ), 'req_role_limit');
        foreach ([
            array_map(strval(...), range(1, MemberAdminService::MAX_ROLE_IDS + 1)),
            array_fill(0, MemberAdminService::MAX_ROLE_IDS + 1, '1'),
        ] as $roleIds) {
            $request = (new Request())
                ->withRoute(['tenant_context' => $context])
                ->withPost(['role_ids' => $roleIds]);
            $response = (new MemberController())->replaceRoles($request, '11');

            self::assertSame(422, $response->getCode());
            self::assertSame('MEMBER_ROLE_LIMIT_EXCEEDED', $response->getData()['code']);
        }
    }

    public function testOpenApiUsesTheCoreRoleLimitWithoutForbiddingEmptyReplacement(): void
    {
        $schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/api/schemas/member.yaml');
        $roleIds = $schema['ReplaceMemberRolesRequest']['properties']['role_ids'];

        self::assertSame(MemberAdminService::MAX_ROLE_IDS, $roleIds['maxItems']);
        self::assertSame(0, $roleIds['minItems'] ?? 0);
    }
}
