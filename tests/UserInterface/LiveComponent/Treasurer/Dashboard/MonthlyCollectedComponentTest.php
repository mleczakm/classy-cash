<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use PHPUnit\Framework\Attributes\Group;
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

        // Create some student payments paid this month
        $student = $this->createTestStudent($classRoom);
        $payment1 = $this->createStudentPayment($student, 'Paid Payment 1', Money::of(50, 'PLN'));
        $payment1->markPaid();

        $payment2 = $this->createStudentPayment($student, 'Paid Payment 2', Money::of(100, 'PLN'));
        $payment2->markPaid();

        $this->getEntityManager()
            ->flush();

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

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        // Initial amount should be 0
        $this->assertTrue(Money::of(0, 'PLN')->isEqualTo($component->getMonthlyCollected()));

        // Create and mark a payment as paid
        $student = $this->createTestStudent($classRoom);
        $payment = $this->createStudentPayment($student, 'New Paid Payment', Money::of(75, 'PLN'));
        $payment->markPaid();
        $this->getEntityManager()
            ->flush();

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
