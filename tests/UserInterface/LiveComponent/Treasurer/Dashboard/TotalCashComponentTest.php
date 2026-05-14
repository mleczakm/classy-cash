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
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        $this->assertEquals(Money::of(800, 'PLN'), $component->getTotalCash());
        $this->assertTrue($component->isPositive());
        $this->assertFalse($component->isNegative());
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $testComponent->call('refreshData');
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        // No class room exists
        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => null,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        $this->assertEquals(Money::of(0, 'PLN'), $component->getTotalCash());
        $this->assertFalse($component->isPositive());
        $this->assertTrue($component->isNegative());
    }

    public function testComponentHandlesNegativeBalance(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Create more expenses than payments
        $student = $this->createTestStudent($classRoom);
        $p = $this->createStudentPayment($student, 'Test Payment', Money::of(100, 'PLN'));
        $p->markPaid();
        $this->createClassExpense($classRoom, 'Test Expense', Money::of(200, 'PLN'));
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        $this->assertEquals(Money::of(-100, 'PLN'), $component->getTotalCash());
        $this->assertFalse($component->isPositive());
        $this->assertTrue($component->isNegative());
    }
}
