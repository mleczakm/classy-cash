<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use Symfony\Component\Uid\Ulid;
use App\Application\Command\RecalculateCashState;
use App\Entity\CashStateRegistry;
use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\Payment;
use App\Repository\CashStateRegistryRepository;
use App\Repository\ClassCouncil\ClassExpenseRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\PaymentRepository;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class RecalculateCashStateHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CashStateRegistryRepository $registryRepository,
        private StudentPaymentRepository $studentPayments,
        private ClassExpenseRepository $expenses,
        private PaymentRepository $payments,
        private ClassRoomRepository $classRooms,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RecalculateCashState $command): void
    {
        $this->logger->info('RecalculateCashStateHandler started');

        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            $this->logger->warning('No class room found, skipping cash state recalculation');
            return;
        }

        // If recalculating for a specific payment, check if registry entry already exists
        if ($command->payment !== null) {
            $existingEntry = $this->registryRepository->findOneByPayment($command->payment);
            if ($existingEntry !== null) {
                $this->logger->info(
                    'Registry entry already exists for payment ' . $command->payment->getId() . ', skipping recalculation'
                );
                return;
            }
        }

        // If recalculating for a specific expense, check if registry entry already exists
        if ($command->expense !== null) {
            $existingEntry = $this->registryRepository->findOneByExpense($command->expense);
            if ($existingEntry !== null) {
                $this->logger->info(
                    'Registry entry already exists for expense ' . $command->expense->getId() . ', skipping recalculation'
                );
                return;
            }
        }

        // For full recalculation (no specific payment/expense), clean up existing duplicates
        if ($command->payment === null && $command->expense === null) {
            $this->logger->info('Performing full recalculation to clean up any existing duplicates');
            $removedCount = $this->registryRepository->removeDuplicates();
            if ($removedCount > 0) {
                $this->logger->info('Removed ' . $removedCount . ' duplicate registry entries');
            }
        }

        // Determine the starting point for recalculation
        $fromDate = $command->fromDate;

        if ($command->payment !== null) {
            $fromDate = $this->getPaymentDate($command->payment);
        } elseif ($command->expense !== null) {
            $fromDate = $command->expense->getSpentAt();
        }

        if ($fromDate === null) {
            $this->logger->warning('No date found for recalculation, recalculating all');
            $fromDate = new \DateTimeImmutable('1970-01-01');
        }

        // Find the last registry entry before the from date to get the starting balance
        $lastRegistry = $this->registryRepository->findLastBeforeDate($fromDate);
        $currentBalance = $lastRegistry ? $lastRegistry->getBalanceAfter() : Money::zero('PLN');

        $this->logger->info('Starting recalculation from date: ' . $fromDate->format('Y-m-d H:i:s')
                           . ', current balance: ' . $currentBalance->getAmount());

        // Delete all registry entries after the from date
        $deletedCount = $this->registryRepository->deleteAfterDate($fromDate);
        $this->logger->info('Deleted ' . $deletedCount . ' registry entries after ' . $fromDate->format('Y-m-d H:i:s'));

        // Get all transactions from the from date onwards
        $expenseId = $command->expense?->getId();
        $transactions = $this->getAllTransactions($class, $fromDate, $expenseId);

        // Sort transactions by date
        usort($transactions, fn($a, $b) => $a['date'] <=> $b['date']);

        $this->logger->info('Processing ' . count($transactions) . ' transactions');

        // Create registry entries for each transaction
        foreach ($transactions as $transaction) {
            if ($transaction['type'] === 'income') {
                $currentBalance = $currentBalance->plus($transaction['amount']);
            } else {
                $currentBalance = $currentBalance->minus($transaction['amount']);
            }

            // Check if registry entry already exists for this payment/expense to prevent duplicates
            $existingEntry = null;
            if (isset($transaction['payment'])) {
                $existingEntry = $this->registryRepository->findOneByPayment($transaction['payment']);
            } elseif (isset($transaction['expense'])) {
                $existingEntry = $this->registryRepository->findOneByExpense($transaction['expense']);
            }

            // Only create new entry if one doesn't already exist
            if ($existingEntry === null) {
                $registry = new CashStateRegistry(
                    $transaction['date'],
                    $currentBalance,
                    $transaction['amount'],
                    $transaction['type'],
                    $transaction['payment'] ?? null,
                    $transaction['expense'] ?? null
                );

                $this->entityManager->persist($registry);
            }
        }

        $this->entityManager->flush();
        $this->logger->info('RecalculateCashStateHandler completed');
    }

    /**
     * @return array<array{type: string, amount: Money, date: \DateTimeImmutable, payment?: Payment, expense?: ClassExpense}>
     */
    private function getAllTransactions(ClassRoom $class, \DateTimeImmutable $fromDate, ?Ulid $expenseId = null): array
    {
        $transactions = [];

        // Get student payments (income)
        $studentPayments = $this->studentPayments->findPaidAfterDate($class, $fromDate);
        foreach ($studentPayments as $studentPayment) {
            $payment = $studentPayment->getPayment();
            if ($payment) {
                $date = $payment->getPaidAt() ?? $payment->getCreatedAt();
                $transactions[] = [
                    'type' => 'income',
                    'amount' => $studentPayment->getAmount(),
                    'date' => $date,
                    'payment' => $payment,
                ];
            }
        }

        // Get general payments (income)
        $generalPayments = $this->payments->findAfterDate($fromDate);
        foreach ($generalPayments as $payment) {
            // Check if this payment is already linked to a student payment to avoid duplicates
            $linkedStudentPayments = $this->studentPayments->findByPayment($payment);
            if (count($linkedStudentPayments) === 0) {
                $date = $payment->getPaidAt() ?? $payment->getCreatedAt();
                $transactions[] = [
                    'type' => 'income',
                    'amount' => $payment->getAmount(),
                    'date' => $date,
                    'payment' => $payment,
                ];
            }
        }

        // Get expenses (outcome)
        $expenses = $this->expenses->findByClass($class);
        foreach ($expenses as $expense) {
            // Always include the expense being recalculated
            if ($expenseId !== null && $expense->getId() === $expenseId) {
                $transactions[] = [
                    'type' => 'expense',
                    'amount' => $expense->getAmount(),
                    'date' => $expense->getSpentAt(),
                    'expense' => $expense,
                ];
            } elseif ($expense->getSpentAt() >= $fromDate) {
                $transactions[] = [
                    'type' => 'expense',
                    'amount' => $expense->getAmount(),
                    'date' => $expense->getSpentAt(),
                    'expense' => $expense,
                ];
            }
        }

        return $transactions;
    }

    private function getPaymentDate(Payment $payment): \DateTimeImmutable
    {
        return $payment->getPaidAt() ?? $payment->getCreatedAt();
    }
}
