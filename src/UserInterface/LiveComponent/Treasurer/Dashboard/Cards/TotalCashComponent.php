<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\StudentPayment;
use App\Repository\ClassCouncil\ClassExpenseRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('treasurer:dashboard:total_cash')]
class TotalCashComponent extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    private Money $totalCash;

    #[LiveProp]
    public ClassRoom $classRoom;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
        private readonly ClassExpenseRepository $expenses,
    ) {
        $this->totalCash = Money::of(0, 'PLN');
    }

    public function getTotalCash(): Money
    {
        $this->calculateTotalCash();

        return $this->totalCash;
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->calculateTotalCash();
        $this->emit('totalCashRefreshed');
    }

    private function calculateTotalCash(): void
    {
        error_log(
            'DEBUG: TotalCashComponent classRoom is ' . ($this->classRoom ? $this->classRoom->getName() : 'NULL')
        );
        $class = $this->classRoom;
        if (! $class) {
            $this->totalCash = Money::of(0, 'PLN');
            return;
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

        $this->totalCash = $sumPaid->minus($sumExpenses);
    }

    public function isPositive(): bool
    {
        return $this->totalCash->isPositive();
    }

    public function isNegative(): bool
    {
        return $this->totalCash->isNegativeOrZero();
    }
}
