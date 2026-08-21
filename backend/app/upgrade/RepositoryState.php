<?php

declare(strict_types=1);

namespace PeanutAdmin\App\upgrade;

final readonly class RepositoryState
{
    public function __construct(
        public string $commit,
        public string $tree,
        public bool $clean,
    ) {
        if (preg_match('/^[a-f0-9]{40}$/D', $commit) !== 1 || preg_match('/^[a-f0-9]{40}$/D', $tree) !== 1) {
            throw new UpgradeFailure('UPGRADE_REPOSITORY_STATE_INVALID');
        }
    }
}
