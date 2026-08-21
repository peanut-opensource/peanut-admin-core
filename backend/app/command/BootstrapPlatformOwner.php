<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class BootstrapPlatformOwner extends Command
{
    protected function configure(): void
    {
        $this->setName('peanut:bootstrap-platform-owner')
            ->setDescription('Create the first Peanut Admin platform owner exactly once')
            ->addOption('email', 'e', Option::VALUE_REQUIRED, 'Owner email address')
            ->addOption('display-name', 'n', Option::VALUE_REQUIRED, 'Owner display name');
    }

    protected function execute(Input $input, Output $output): int
    {
        $password = getenv('PEANUT_BOOTSTRAP_PASSWORD');
        if (!is_string($password) || $password === '') {
            throw new RuntimeException('PEANUT_BOOTSTRAP_PASSWORD must be set.');
        }

        $result = KernelBootstrapFactory::create()->bootstrapPlatformOwner(
            (string) $input->getOption('email'),
            $password,
            (string) $input->getOption('display-name'),
            'cli-bootstrap-' . bin2hex(random_bytes(12)),
        );
        $output->writeln(json_encode(
            $result->toArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ));

        return 0;
    }
}
