<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards\OutstandingPaymentsComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[\PHPUnit\Framework\Attributes\Group('functional')]
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

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class);

        $this->assertEquals('150,00', $component->getFormattedAmount());
        $this->assertEquals(2, $component->getOutstandingCount());
        $this->assertTrue($component->hasOutstanding());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class);

        // Initial amount
        $initialAmount = $component->getFormattedAmount();

        // Add new unpaid payment and refresh
        $student = $this->createTestStudent($classRoom);
        $this->createStudentPayment($student, 'New Unpaid Payment', Money::of(25, 'PLN'));
        $component->refreshData();

        // Amount should have changed
        $refreshedAmount = $component->getFormattedAmount();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals('175,00', $refreshedAmount);
        $this->assertEquals(3, $component->getOutstandingCount());
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class);

        $component->refreshData();

        $this->assertEventEmitted('outstandingPaymentsRefreshed');
    }

    public function testComponentHandlesNoOutstanding(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // No unpaid payments
        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class);

        $this->assertEquals('0,00', $component->getFormattedAmount());
        $this->assertEquals(0, $component->getOutstandingCount());
        $this->assertFalse($component->hasOutstanding());
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $component = $this->createLiveComponent(OutstandingPaymentsComponent::class);

        $this->assertEquals('0,00', $component->getFormattedAmount());
        $this->assertEquals(0, $component->getOutstandingCount());
        $this->assertFalse($component->hasOutstanding());
    }
}
