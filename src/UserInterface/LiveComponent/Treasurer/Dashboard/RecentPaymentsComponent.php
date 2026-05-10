<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\StudentPayment;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\ClassCouncil\StudentPaymentRepository;
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
    public ?string $refreshKey = null;

    private array $recentPayments = [];

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly StudentPaymentRepository $studentPayments,
    ) {}

    public function mount(): void
    {
        $this->loadRecentPayments();
    }

    public function getRecentPayments(): array
    {
        return $this->recentPayments;
    }

    #[LiveAction]
    public function refreshData(): void
    {
        $this->loadRecentPayments();
        $this->emit('recentPaymentsRefreshed');
    }

    private function loadRecentPayments(): void
    {
        $class = $this->classRooms->findOneBy([]);
        if (!$class) {
            $this->recentPayments = [];
            return;
        }

        $this->recentPayments = $this->studentPayments->findRecentPaid($class, 10);
    }

    public function getPaymentMethod(StudentPayment $payment): string
    {
        $payment = $payment->getPayment();
        if (!$payment) {
            return 'Ręcznie';
        }

        // Try to determine method from transfer title or other logic
        return 'Auto'; // This would be enhanced with actual transfer analysis
    }

    public function getPaymentMethodClass(StudentPayment $payment): string
    {
        $method = $this->getPaymentMethod($payment);
        
        return match($method) {
            'Auto (BLIK)' => 'bg-emerald-50 text-emerald-600',
            'Auto (Przelew)' => 'bg-emerald-50 text-emerald-600',
            'Ręcznie' => 'bg-blue-50 text-blue-600',
            default => 'bg-gray-50 text-gray-600'
        };
    }

    public function formatAmount(\Brick\Money\Money $amount): string
    {
        $formatted = number_format($amount->getAmount(), 2, ',', ' ');
        return $formatted . ' PLN';
    }
}
