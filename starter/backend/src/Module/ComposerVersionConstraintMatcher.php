<?php

declare(strict_types=1);

namespace PeanutAdmin\InternalStarter\Module;

use Composer\Semver\Semver;
use PeanutAdmin\Kernel\Module\VersionConstraintMatcher;

final class ComposerVersionConstraintMatcher implements VersionConstraintMatcher
{
    public function matches(string $version, string $constraint): bool
    {
        return Semver::satisfies($version, $constraint);
    }
}
