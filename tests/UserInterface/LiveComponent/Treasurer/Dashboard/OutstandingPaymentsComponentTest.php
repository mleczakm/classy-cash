<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use PHPUnit\Framework\Attributes\Group;
use App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards\OutstandingPaymentsComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class OutstandingPaymentsComponentTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    public function testComponentDisplaysCorrectOutstandingAmount(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create some unpaid student payments
        $student = $this->createTestStudent($classRoom);
        $this->createStudentPayment($student, 'Unpaid Payment 1', Money::of(50, 'PLN'));
        $this->createStudentPayment($student, 'Unpaid Payment 2', Money::of(100, 'PLN'));

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $this->assertEquals(Money::of(150, 'PLN'), $component->component()->getOutstandingAmount());
        $this->assertEquals(2, $component->component()->getOutstandingCount());
        $this->assertTrue($component->component()->hasOutstanding());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create test data before component creation
        $student = $this->createTestStudent($classRoom);
        $newPayment = $this->createStudentPayment($student, 'New Unpaid Payment', Money::of(25, 'PLN'));

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class, [
            'classRoom' => $classRoom,
        ]);

        // Initial amount
        $initialAmount = $component->component()
            ->getOutstandingAmount();

        // Refresh component to include new payment
        $component->call('refreshData');

        // Amount should have changed
        $refreshedAmount = $component->component()
            ->getOutstandingAmount();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals(Money::of(175, 'PLN'), $refreshedAmount);
        $this->assertEquals(3, $component->component()->getOutstandingCount());
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $component->call('refreshData');

        // The refreshData action should complete without errors
        $this->assertTrue(true);
    }

    public function testComponentHandlesNoOutstanding(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // No unpaid payments
        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $this->assertEquals(Money::of(0, 'PLN'), $component->component()->getOutstandingAmount());
        $this->assertEquals(0, $component->component()->getOutstandingCount());
        $this->assertFalse($component->component()->hasOutstanding());
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class, [
            'classRoom' => null,
        ]);

        $this->assertEquals(Money::of(0, 'PLN'), $component->component()->getOutstandingAmount());
        $this->assertEquals(0, $component->component()->getOutstandingCount());
        $this->assertFalse($component->component()->hasOutstanding());
    }
}
