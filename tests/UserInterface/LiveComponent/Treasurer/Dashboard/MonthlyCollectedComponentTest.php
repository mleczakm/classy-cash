<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use PHPUnit\Framework\Attributes\Group;
use App\Application\Command\RecalculateCashState;
use App\Application\CommandHandler\RecalculateCashStateHandler;
use App\Entity\Payment;
use App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards\MonthlyCollectedComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class MonthlyCollectedComponentTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    public function testComponentDisplaysCorrectAmount(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('test@example.com', 'password');

        // Create some student payments paid this month
        $student = $this->createTestStudent($classRoom);

        $payment1 = new Payment($user, Money::of(50, 'PLN'));
        $this->getEntityManager()
            ->persist($payment1);

        $studentPayment1 = $this->createStudentPayment($student, 'Paid Payment 1', Money::of(50, 'PLN'));
        $studentPayment1->setPayment($payment1);
        $studentPayment1->markPaid();

        $payment2 = new Payment($user, Money::of(100, 'PLN'));
        $this->getEntityManager()
            ->persist($payment2);

        $studentPayment2 = $this->createStudentPayment($student, 'Paid Payment 2', Money::of(100, 'PLN'));
        $studentPayment2->setPayment($payment2);
        $studentPayment2->markPaid();

        $this->getEntityManager()
            ->flush();

        // Trigger cash state recalculation
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState());

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        $this->assertTrue(Money::of(150, 'PLN')->isEqualTo($component->getMonthlyCollected()));
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('test@example.com', 'password');

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        // Initial amount should be 0
        $this->assertTrue(Money::of(0, 'PLN')->isEqualTo($component->getMonthlyCollected()));

        // Create and mark a payment as paid
        $student = $this->createTestStudent($classRoom);

        $payment = new Payment($user, Money::of(75, 'PLN'));
        $this->getEntityManager()
            ->persist($payment);

        $studentPayment = $this->createStudentPayment($student, 'New Paid Payment', Money::of(75, 'PLN'));
        $studentPayment->setPayment($payment);
        $studentPayment->markPaid();

        $this->getEntityManager()
            ->flush();

        // Trigger cash state recalculation
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState());

        // Refresh component
        $testComponent->call('refreshData');

        // Amount should have updated
        $this->assertTrue(Money::of(75, 'PLN')->isEqualTo($component->getMonthlyCollected()));
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $testComponent->call('refreshData');
        $this->assertComponentEmitEvent($testComponent, 'monthlyCollectedRefreshed');
    }
}
