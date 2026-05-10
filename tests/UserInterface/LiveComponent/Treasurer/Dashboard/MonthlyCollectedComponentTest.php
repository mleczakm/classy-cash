<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\LiveComponent\Treasurer\Dashboard;

use App\UserInterface\LiveComponent\Treasurer\Dashboard\Cards\MonthlyCollectedComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[\PHPUnit\Framework\Attributes\Group('functional')]
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

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class);

        $this->assertEquals('1 120,00', $component->getFormattedAmount());
        $this->assertEquals('maja', $component->getCurrentMonth());
    }

    public function testComponentRefreshesData(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class);

        // Initial amount
        $initialAmount = $component->getFormattedAmount();

        // Add new payment for current month and refresh
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'New Payment', Money::of(100, 'PLN'));
        $p->markPaid();
        $this->em->flush();

        $component->refreshData();

        // Amount should have changed
        $refreshedAmount = $component->getFormattedAmount();
        $this->assertNotEquals($initialAmount, $refreshedAmount);
        $this->assertEquals('100,00', $refreshedAmount);
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $component = $this->createLiveComponent(MonthlyCollectedComponent::class);

        $component->refreshData();

        $this->assertEventEmitted('monthlyCollectedRefreshed');
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $component = $this->createLiveComponent(MonthlyCollectedComponent::class);

        $this->assertEquals('0,00', $component->getFormattedAmount());
    }

    public function testComponentDisplaysCorrectCurrentMonth(): void
    {
        $component = $this->createLiveComponent(MonthlyCollectedComponent::class);

        // The month is translated, so it might be 'maja' for May in Polish locale if set
        $this->assertNotEmpty($component->getCurrentMonth());
    }
}
