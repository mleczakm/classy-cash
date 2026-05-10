<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards;

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

    #[LiveProp]
    public ?string $refreshKey = null;

    private Money $monthlyCollected;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
    ) {
        $this->monthlyCollected = Money::of(0, 'PLN');
    }

    public function mount(): void
    {
        $this->calculateMonthlyCollected();
    }

    public function getMonthlyCollected(): Money
    {
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
        $class = $this->classRooms->findOneBy([]);
        if (! $class) {
            $this->monthlyCollected = Money::of(0, 'PLN');
            return;
        }

        $monthlyCollected = Money::of(0, 'PLN');

        // Get payments from current month
        $currentMonth = new \DateTimeImmutable('first day of this month midnight');
        $payments = $this->studentPayments->findPaidSince($class, $currentMonth);

        foreach ($payments as $payment) {
            $monthlyCollected = $monthlyCollected->plus($payment->getAmount());
        }

        $this->monthlyCollected = $monthlyCollected;
    }

    public function getFormattedAmount(): string
    {
        $amount = $this->monthlyCollected->getAmount();
        return number_format($amount, 2, ',', ' ');
    }

    public function getCurrentMonth(): string
    {
        $now = new \DateTimeImmutable();
        $months = [
            'styczeń', 'luty', 'marzec', 'kwiecień', 'maj', 'czerwiec',
            'lipiec', 'sierpień', 'wrzesień', 'październik', 'listopad', 'grudzień',
        ];

        return $months[(int) $now->format('n') - 1];
    }
}
