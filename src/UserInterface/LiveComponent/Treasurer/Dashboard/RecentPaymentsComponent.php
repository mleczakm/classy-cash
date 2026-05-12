<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\Entity\ClassCouncil\ClassRoom;
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

    #[LiveProp(useSerializerForHydration: true)]
    public array $recentTransactions = [];

    #[LiveProp]
    public int $page = 1;

    #[LiveProp]
    public bool $hasMoreData = true;

    #[LiveProp]
    public ?ClassRoom $classRoom = null;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ClassExpenseRepository $expenses,
        private readonly PaymentRepository $payments,
        private readonly LoggerInterface $logger,

    ) {}

    public function mount(): void
    {
        $this->loadRecentTransactions();
    }

    public function getRecentTransactions(): array
    {
        $this->loadRecentTransactions();
        return $this->recentTransactions;
    }

    public function getCurrentBalance(): Money
    {
        if (! $this->classRoom) {
            return Money::of(0, 'PLN');
        }

        $sumPaid = Money::of(0, 'PLN');
        $sumExpenses = Money::of(0, 'PLN');

        // Calculate total paid student payments
        $payments = $this->studentPayments->findByClass($this->classRoom);
        foreach ($payments as $payment) {
            if ($payment->getStatus() === StudentPayment::STATUS_PAID) {
                $sumPaid = $sumPaid->plus($payment->getAmount());
            }
        }

        // Calculate total expenses
        $expenses = $this->expenses->findByClass($this->classRoom);
        foreach ($expenses as $expense) {
            $sumExpenses = $sumExpenses->plus($expense->getAmount());
        }

        return $sumPaid->minus($sumExpenses);
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->loadRecentTransactions();
        $this->emit('recentTransactionsRefreshed');
    }

    #[LiveAction]
    public function loadMore(): void
    {
        $this->page++;
        $this->loadRecentTransactions();
        $this->emit('moreTransactionsLoaded');
    }

    private function loadRecentTransactions(): void
    {
        if (! $this->classRoom) {
            $this->recentTransactions = [];
            $this->hasMoreData = false;
            return;
        }

        $transactions = [];
        $this->logger->debug('ClassRoom: ' . $this->classRoom->getName());

        // Calculate pagination limits
        $limit = 10;
        $offset = ($this->page - 1) * $limit;

        // Get recent paid student payments
        $studentPayments = $this->studentPayments->findRecentPaid($this->classRoom, 50); // Get more for pagination
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
        $expenses = $this->expenses->findByClass($this->classRoom);
        $this->logger->debug('Found ' . count($expenses) . ' expenses');

        $expenses = array_slice($expenses, 0, 50); // Get more for pagination
        foreach ($expenses as $expense) {
            $this->logger->debug('Adding expense: ' . $expense->getLabel());
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
        $generalPayments = $this->payments->findRecentByUser($this->getUser(), 50); // Get more for pagination
        foreach ($generalPayments as $payment) {
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

        // Sort all transactions by date (most recent first)
        usort($transactions, fn($a, $b) => $b['date'] <=> $a['date']);

        // Apply pagination
        $paginatedTransactions = array_slice($transactions, $offset, $limit);
        
        // Check if there's more data
        $totalTransactions = count($transactions);
        $this->hasMoreData = ($offset + $limit) < $totalTransactions;

        // For first page, replace all transactions. For subsequent pages, append
        if ($this->page === 1) {
            $this->recentTransactions = $paginatedTransactions;
        } else {
            $this->recentTransactions = array_merge($this->recentTransactions, $paginatedTransactions);
        }

        $this->logger->debug('Final transactions count: ' . count($this->recentTransactions));
        $this->logger->debug('Has more data: ' . ($this->hasMoreData ? 'true' : 'false'));
    }

    public function getPaymentMethod(StudentPayment $payment): string
    {
        $payment = $payment->getPayment();
        if (! $payment) {
            return 'Ręcznie';
        }

        // Try to determine method from transfer title or other logic
        return 'Auto'; // This would be enhanced with actual transfer analysis
    }

    public function getPaymentMethodClass(StudentPayment $payment): string
    {
        $method = $this->getPaymentMethod($payment);

        return match ($method) {
            'Auto (BLIK)' => 'bg-emerald-50 text-emerald-600',
            'Auto (Przelew)' => 'bg-emerald-50 text-emerald-600',
            'Ręcznie' => 'bg-blue-50 text-blue-600',
            default => 'bg-gray-50 text-gray-600'
        };
    }
}
