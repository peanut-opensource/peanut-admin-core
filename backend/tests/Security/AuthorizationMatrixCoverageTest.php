<?php

declare(strict_types=1);

namespace PeanutAdmin\App\Tests\Security;

use PeanutAdmin\Testing\Authorization\SecurityMatrixCoverage;
use PHPUnit\Framework\TestCase;

final class AuthorizationMatrixCoverageTest extends TestCase
{
    public function testP0AuthorizationMatrixHasNoUnassignedAcceptanceId(): void
    {
        $required = SecurityMatrixCoverage::requiredIds();
        $evidence = SecurityMatrixCoverage::evidence();

        self::assertSame([], array_values(array_diff($required, array_keys($evidence))));
        self::assertSame([], array_values(array_diff(array_keys($evidence), $required)));
        foreach ($required as $id) {
            self::assertNotSame('', trim($evidence[$id]));
        }
        self::assertCount(62, $evidence);
    }
}
