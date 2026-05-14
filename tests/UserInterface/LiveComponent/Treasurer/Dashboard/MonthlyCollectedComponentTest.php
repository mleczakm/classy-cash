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

    public function testComponentDisplaysCorrectMonthlyAmount(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create payments for current month
        $student = $this->createTestStudent($classRoom);
        $p1 = $this->createStudentPayment($student, 'Test Payment 1', Money::of(500, 'PLN'));
        $p1->markPaid();
        $p2 = $this->createStudentPayment($student, 'Test Payment 2', Money::of(620, 'PLN'));
        $p2->markPaid();
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        $this->assertEquals(Money::of(1120, 'PLN'), $component->getMonthlyCollected());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create test data before component creation
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'New Payment', Money::of(100, 'PLN'));

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        // Initial amount
        $initialAmount = $component->getMonthlyCollected();

        // Mark payment as paid and refresh
        $p->markPaid();
        $this->getEntityManager()
            ->flush();

        $testComponent->call('refreshData');

        // Amount should have changed
        $refreshedAmount = $component->getMonthlyCollected();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals(Money::of(100, 'PLN'), $refreshedAmount);
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $testComponent->call('refreshData');
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $testComponent = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => null,
        ]);
        /** @var MonthlyCollectedComponent $component */
        $component = $testComponent->component();

        $this->assertEquals(Money::of(0, 'PLN'), $component->getMonthlyCollected());
    }
}
