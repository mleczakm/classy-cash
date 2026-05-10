<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\StudentPayment;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('treasurer:dashboard:outstanding_payments')]
class OutstandingPaymentsComponent extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $refreshKey = null;

    private Money $outstandingAmount;
    private int $outstandingCount;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
    ) {
        $this->outstandingAmount = Money::of(0, 'PLN');
        $this->outstandingCount = 0;
    }

    public function mount(): void
    {
        $this->calculateOutstandingPayments();
    }

    public function getOutstandingAmount(): Money
    {
        return $this->outstandingAmount;
    }

    public function getOutstandingCount(): int
    {
        return $this->outstandingCount;
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->calculateOutstandingPayments();
        $this->emit('outstandingPaymentsRefreshed');
    }

    private function calculateOutstandingPayments(): void
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            $this->outstandingAmount = Money::of(0, 'PLN');
            $this->outstandingCount = 0;
            return;
        }

        $outstandingAmount = Money::of(0, 'PLN');
        $outstandingCount = 0;

        $payments = $this->studentPayments->findByClass($class);
        foreach ($payments as $payment) {
            if ($payment->getStatus() !== StudentPayment::STATUS_PAID) {
                $outstandingAmount = $outstandingAmount->plus($payment->getAmount());
                $outstandingCount++;
            }
        }

        $this->outstandingAmount = $outstandingAmount;
        $this->outstandingCount = $outstandingCount;
    }

    public function getFormattedAmount(): string
    {
        $amount = $this->outstandingAmount->getAmount();
        return number_format($amount, 2, ',', ' ');
    }

    public function hasOutstanding(): bool
    {
        return !$this->outstandingAmount->isZero();
    }
}
