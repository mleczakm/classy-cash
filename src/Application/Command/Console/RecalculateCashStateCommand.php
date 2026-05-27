<?php

declare(strict_types=1);

namespace App\Application\Command\Console;

use App\Application\Command\RecalculateCashState;
use App\Application\CommandHandler\RecalculateCashStateHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:recalculate-cash-state')]
final class RecalculateCashStateCommand extends Command
{
    public function __construct(
        private readonly RecalculateCashStateHandler $handler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Recalculating cash state for all historical transactions...');

        $this->handler->__invoke(new RecalculateCashState());

        $output->writeln('Cash state recalculation completed.');

        return Command::SUCCESS;
    }
}
