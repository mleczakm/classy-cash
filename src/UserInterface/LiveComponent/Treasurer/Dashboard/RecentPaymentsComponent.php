<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\Entity\CashStateRegistry;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\CashStateRegistryRepository;
use Brick\Money\Money;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('treasurer:dashboard:recent_payments')]
class RecentPaymentsComponent extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public int $page = 1;

    #[LiveProp]
    public ?ClassRoom $classRoom = null;

    public function __construct(
        private readonly StudentPaymentRepository $studentPayments,
        private readonly CashStateRegistryRepository $registry,
        private readonly LoggerInterface $logger,
    ) {}

    public function getCurrentBalance(): Money
    {
        $latestEntry = $this->registry->findLatest();
        return $latestEntry ? $latestEntry->getBalanceAfter() : Money::of(0, 'PLN');
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->emit('recentTransactionsRefreshed');
    }

    #[LiveAction]
    public function more(): void
    {
        $this->logger->info('RecentPaymentsComponent::more() action called, current page: ' . $this->page);
        ++$this->page;
    }

    public function hasMore(): bool
    {
        $totalEntries = count($this->registry->findAllOrderedDesc());
        return $totalEntries > ($this->page * 10);
    }

    /**
     * @return list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string, balanceAfter: Money|null}>
     */
    public function getItems(): array
    {
        $registryEntries = $this->registry->findAllOrderedDesc();

        $transactions = [];
        foreach ($registryEntries as $entry) {
            $transaction = $this->mapRegistryEntryToTransaction($entry);
            if ($transaction) {
                $transactions[] = $transaction;
            }
        }

        // Apply pagination
        $limit = 10;
        $offset = ($this->page - 1) * $limit;

        /** @var list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string, balanceAfter: Money|null}> $items */
        $items = array_slice($transactions, $offset, $limit);
        $this->logger->info(
            sprintf('RecentPaymentsComponent::getItems() page=%d, offset=%d, count=%d', $this->page, $offset, count(
                $items
            ))
        );

        return $items;
    }

    /**
     * @return array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string, balanceAfter: Money|null}|null
     */
    private function mapRegistryEntryToTransaction(CashStateRegistry $entry): ?array
    {
        if ($entry->getPayment()) {
            $payment = $entry->getPayment();
            // Find student payment linked to this payment
            $studentPayment = $this->studentPayments->findByPayment($payment);
            if (count($studentPayment) > 0) {
                $sp = $studentPayment[0];
                return [
                    'type' => 'income',
                    'description' => $sp->getLabel(),
                    'amount' => $entry->getTransactionAmount(),
                    'date' => $entry->getTransactionDate(),
                    'student' => $sp->getStudent(),
                    'method' => $this->getPaymentMethod($sp),
                    'methodClass' => $this->getPaymentMethodClass($sp),
                    'balanceAfter' => $entry->getBalanceAfter(),
                ];
            }

            // General payment (not linked to student)
            return [
                'type' => 'income',
                'description' => 'Wpłata ogólna',
                'amount' => $entry->getTransactionAmount(),
                'date' => $entry->getTransactionDate(),
                'student' => null,
                'method' => 'Ręcznie',
                'methodClass' => 'bg-blue-50 text-blue-600',
                'balanceAfter' => $entry->getBalanceAfter(),
            ];
        }

        if ($entry->getExpense()) {
            $expense = $entry->getExpense();
            return [
                'type' => 'expense',
                'description' => $expense->getLabel(),
                'amount' => $entry->getTransactionAmount(),
                'date' => $entry->getTransactionDate(),
                'student' => null,
                'method' => 'Wydatek',
                'methodClass' => 'bg-red-50 text-red-600',
                'balanceAfter' => $entry->getBalanceAfter(),
            ];
        }

        return null;
    }

    public function getPaymentMethod(StudentPayment $payment): string
    {
        $payment = $payment->getPayment();
        if (! $payment) {
            return 'Ręcznie';
        }

        return 'Auto';
    }

    public function getPaymentMethodClass(StudentPayment $payment): string
    {
        $method = $this->getPaymentMethod($payment);

        return match ($method) {
            'Auto' => 'bg-emerald-50 text-emerald-600',
            'Ręcznie' => 'bg-blue-50 text-blue-600',
            default => 'bg-gray-50 text-gray-600'
        };
    }
}
