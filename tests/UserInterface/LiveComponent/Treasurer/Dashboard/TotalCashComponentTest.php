<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use PHPUnit\Framework\Attributes\Group;
use App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards\TotalCashComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
class TotalCashComponentTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    public function testComponentDisplaysCorrectAmount(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create some test payments and expenses
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'Test Payment', Money::of(1000, 'PLN'));
        $p->markPaid();
        $this->createClassExpense($classRoom, 'Test Expense', Money::of(200, 'PLN'));
        $this->em->flush();

        $component = $this->createLiveComponent(TotalCashComponent::class);

        $this->assertEquals('800,00', $component->getFormattedAmount());
        $this->assertTrue($component->isPositive());
        $this->assertFalse($component->isNegative());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(TotalCashComponent::class);

        // Initial amount
        $initialAmount = $component->getFormattedAmount();

        // Add new expense and refresh
        $this->createClassExpense($classRoom, 'New Expense', Money::of(100, 'PLN'));
        $this->em->flush();
        $component->refreshData();

        // Amount should have changed
        $refreshedAmount = $component->getFormattedAmount();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals('-100,00', $refreshedAmount);
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(TotalCashComponent::class);

        $component->refreshData();

        $this->assertEventEmitted('totalCashRefreshed');
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $component = $this->createLiveComponent(TotalCashComponent::class);

        $this->assertEquals('0,00', $component->component()->getFormattedAmount());
        $this->assertFalse($component->isPositive());
        $this->assertFalse($component->isNegative());
    }

    public function testComponentHandlesNegativeBalance(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create more expenses than payments
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'Test Payment', Money::of(100, 'PLN'));
        $p->markPaid();
        $this->createClassExpense($classRoom, 'Test Expense', Money::of(200, 'PLN'));
        $this->em->flush();

        $component = $this->createLiveComponent(TotalCashComponent::class);

        $this->assertEquals('-100,00', $component->getFormattedAmount());
        $this->assertFalse($component->isPositive());
        $this->assertTrue($component->isNegative());
    }
}
