<?php

declare(strict_types=1);

namespace PeanutAdmin\App\command;

use PeanutAdmin\App\task\TaskWorkerService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

final class TaskWorkerCommand extends Command
{
    public function __construct(private readonly TaskWorkerService $workerService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('peanut:task-worker')->setDescription('Run one trusted Tenant task')->addOption('tenant', null, Option::VALUE_REQUIRED, 'Tenant ID')->addOption('worker', null, Option::VALUE_REQUIRED, 'Stable worker ID');
    }

    protected function execute(Input $input, Output $output): int
    {
        $tenant = filter_var($input->getOption('tenant'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $worker = (string) $input->getOption('worker');
        if (!is_int($tenant)) {
            throw new \InvalidArgumentException('A positive --tenant is required.');
        }
        $status = $this->workerService->runOnce($tenant, $worker);
        $output->writeln($status ?? 'idle');
        return 0;
    }
}
