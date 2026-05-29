<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\MatchPaymentForTransfer;
use App\Application\Command\Notification\TransferNotMatchedCommand;
use App\Application\Command\RecalculateCashState;
use App\Application\Service\TransferPaymentMatcher;
use App\Entity\Transfer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class MatchPaymentForTransferHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TransferPaymentMatcher $transferPaymentMatcher,
        private WorkflowInterface $paymentStateMachine,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(MatchPaymentForTransfer $command): void
    {
        $transfer = $this->entityManager->find(Transfer::class, $command->transfer->getId());

        if (! $transfer) {
            return;
        }

        $payment = $this->transferPaymentMatcher->matchByPaymentCode($transfer)
            ?? $this->transferPaymentMatcher->matchByHistoricalData($transfer);

        if ($payment === null) {
            $this->messageBus->dispatch(new TransferNotMatchedCommand($transfer));

            return;
        }

        $payment->addTransfer($transfer);

        if ($this->paymentStateMachine->can($payment, 'pay')) {
            $this->paymentStateMachine->apply($payment, 'pay');
        }

        $this->messageBus->dispatch(
            new Envelope(new RecalculateCashState(payment: $payment))
                ->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
