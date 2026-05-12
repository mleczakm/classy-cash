<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards;

use App\Entity\ClassCouncil\ClassRoom;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('treasurer:dashboard:monthly_collected')]
class MonthlyCollectedComponent extends AbstractController
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    private Money $monthlyCollected;

    #[LiveProp]
    public ?ClassRoom $classRoom = null;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
    ) {
        $this->monthlyCollected = Money::of(0, 'PLN');
    }

    public function getMonthlyCollected(): Money
    {
        $this->calculateMonthlyCollected();

        return $this->monthlyCollected;
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->calculateMonthlyCollected();
        $this->emit('monthlyCollectedRefreshed');
    }

    private function calculateMonthlyCollected(): void
    {
        if (! $this->classRoom) {
            $this->monthlyCollected = Money::of(0, 'PLN');
            return;
        }

        $monthlyCollected = Money::of(0, 'PLN');

        // Get payments from current month
        $currentMonth = new \DateTimeImmutable('first day of this month midnight');
        $payments = $this->studentPayments->findPaidSince($this->classRoom, $currentMonth);

        foreach ($payments as $payment) {
            $monthlyCollected = $monthlyCollected->plus($payment->getAmount());
        }

        $this->monthlyCollected = $monthlyCollected;
    }
}
