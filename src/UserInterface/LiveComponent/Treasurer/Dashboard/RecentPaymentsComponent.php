<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\Entity\User;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use Brick\Money\Money;
use App\Entity\ClassCouncil\StudentPayment;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\ClassExpenseRepository;
use App\Repository\PaymentRepository;
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
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ClassExpenseRepository $expenses,
        private readonly PaymentRepository $payments,
        private readonly LoggerInterface $logger,
    ) {}

    public function getCurrentBalance(): Money
    {
        $class = $this->getClassRoom();
        if (! $class) {
            return Money::of(0, 'PLN');
        }

        $sumPaid = Money::of(0, 'PLN');
        $sumExpenses = Money::of(0, 'PLN');

        // Calculate total paid student payments
        $payments = $this->studentPayments->findByClass($class);
        foreach ($payments as $payment) {
            if ($payment->getStatus() === StudentPayment::STATUS_PAID) {
                $sumPaid = $sumPaid->plus($payment->getAmount());
            }
        }

        // Calculate total expenses
        $expenses = $this->expenses->findByClass($class);
        foreach ($expenses as $expense) {
            $sumExpenses = $sumExpenses->plus($expense->getAmount());
        }

        return $sumPaid->minus($sumExpenses);
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
        $totalTransactions = count($this->getAllTransactions());
        return $totalTransactions > ($this->page * 10);
    }

    /**
     * @return list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string}>
     */
    public function getItems(): array
    {
        $transactions = $this->getAllTransactions();

        // Apply pagination
        $limit = 10;
        $offset = ($this->page - 1) * $limit;

        /** @var list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string}> $items */
        $items = array_slice($transactions, $offset, $limit);
        $this->logger->info(
            sprintf('RecentPaymentsComponent::getItems() page=%d, offset=%d, count=%d', $this->page, $offset, count(
                $items
            ))
        );

        return $items;
    }

    private function getClassRoom(): ?ClassRoom
    {
        if ($this->classRoom) {
            return $this->classRoom;
        }

        // Fallback: find first class room
        return $this->classRooms->findOneBy([]);
    }

    /**
     * @return list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string}>
     */
    private function getAllTransactions(): array
    {
        $class = $this->getClassRoom();
        if (! $class) {
            $this->logger->warning(
                'RecentPaymentsComponent::getAllTransactions() - classRoom is NULL even after fallback'
            );
            return [];
        }

        $transactions = [];

        // Get recent paid student payments
        $studentPayments = $this->studentPayments->findRecentPaid($class, 100);
        $this->logger->info('RecentPaymentsComponent - found ' . count($studentPayments) . ' student payments');
        foreach ($studentPayments as $payment) {
            $transactions[] = [
                'type' => 'income',
                'description' => $payment->getLabel(),
                'amount' => $payment->getAmount(),
                'date' => $payment->getPaidAt() ?? $payment->getCreatedAt(),
                'student' => $payment->getStudent(),
                'method' => $this->getPaymentMethod($payment),
                'methodClass' => $this->getPaymentMethodClass($payment),
            ];
        }

        // Get recent expenses
        $expenses = $this->expenses->findByClass($class);
        $this->logger->info('RecentPaymentsComponent - found ' . count($expenses) . ' expenses');
        foreach ($expenses as $expense) {
            $transactions[] = [
                'type' => 'expense',
                'description' => $expense->getLabel(),
                'amount' => $expense->getAmount(),
                'date' => $expense->getSpentAt(),
                'student' => null,
                'method' => 'Wydatek',
                'methodClass' => 'bg-red-50 text-red-600',
            ];
        }

        // Get recent general payments (not linked to student payments)
        // We use the user from security context
        $user = $this->getUser();
        if ($user instanceof User) {
            $generalPayments = $this->payments->findRecentByUser($user, 100);
            $this->logger->info('RecentPaymentsComponent - found ' . count($generalPayments) . ' general payments');
            foreach ($generalPayments as $payment) {
                // Check if this payment is already linked to a student payment to avoid duplicates
                $isLinked = count($this->studentPayments->findByPayment($payment)) > 0;
                if (! $isLinked) {
                    $transactions[] = [
                        'type' => 'income',
                        'description' => 'Wpłata ogólna',
                        'amount' => $payment->getAmount(),
                        'date' => $payment->getCreatedAt(),
                        'student' => null,
                        'method' => 'Ręcznie',
                        'methodClass' => 'bg-blue-50 text-blue-600',
                    ];
                }
            }
        }

        // Sort all transactions by date (most recent first)
        usort($transactions, fn($a, $b) => $b['date'] <=> $a['date']);

        $this->logger->info('RecentPaymentsComponent - total transactions sorted: ' . count($transactions));

        /** @var list<array{type: string, description: string, amount: Money, date: \DateTimeInterface, student: Student|null, method: string, methodClass: string}> $transactions */
        return $transactions;
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
