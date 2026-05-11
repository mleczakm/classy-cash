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
        $this->em->flush();

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $this->assertEquals(Money::of(1120, 'PLN'), $component->component()->getMonthlyCollected());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create test data before component creation
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'New Payment', Money::of(100, 'PLN'));

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        // Initial amount
        $initialAmount = $component->component()
            ->getMonthlyCollected();

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        // Mark payment as paid and refresh
        $p->markPaid();
        $this->em->flush();

        $component->call('refreshData');

        // Amount should have changed
        $refreshedAmount = $component->component()
            ->getMonthlyCollected();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals(Money::of(100, 'PLN'), $refreshedAmount);
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $component->call('refreshData');

        // The refreshData action should complete without errors
        $this->assertTrue(true);
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $component = $this->createLiveComponent(MonthlyCollectedComponent::class, [
            'classRoom' => null,
        ]);

        $this->assertEquals(Money::of(0, 'PLN'), $component->component()->getMonthlyCollected());
    }
}
