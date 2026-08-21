<?php

declare(strict_types=1);

namespace PeanutAdmin\App\module;

use Composer\Semver\Semver;
use PeanutAdmin\Kernel\Module\VersionConstraintMatcher;

final class ComposerVersionConstraintMatcher implements VersionConstraintMatcher
{
    public function matches(string $version, string $constraint): bool
    {
        return Semver::satisfies($version, $constraint);
    }
}
