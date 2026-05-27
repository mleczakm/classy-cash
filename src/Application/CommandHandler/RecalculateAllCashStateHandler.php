<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\RecalculateAllCashState;
use App\Application\Command\RecalculateCashState;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RecalculateAllCashStateHandler
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function __invoke(RecalculateAllCashState $command): void
    {
        // Dispatch RecalculateCashState without any entity to recalculate everything
        $this->commandBus->dispatch(new RecalculateCashState());
    }
}
