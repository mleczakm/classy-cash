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

        // Create some student payments and class expenses
        $student = $this->createTestStudent($classRoom);
        $payment = $this->createStudentPayment($student, 'Paid Payment', Money::of(100, 'PLN'));
        $payment->markPaid();

        $this->createClassExpense($classRoom, 'Expense', Money::of(30, 'PLN'));

        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        // 100 - 30 = 70
        $this->assertTrue(Money::of(70, 'PLN')->isEqualTo($component->getTotalCash()));
    }

    public function testComponentEmitsEventOnRefresh(): void
    {
        $classRoom = $this->createClassRoom('4B');

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);

        $testComponent->call('refreshData');
        $this->assertNotNull($testComponent->getEmittedEvent($testComponent->render(), 'totalCashRefreshed'));
    }

    public function testComponentHandlesNoClassRoom(): void
    {
        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => null,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        $this->assertTrue(Money::of(0, 'PLN')->isEqualTo($component->getTotalCash()));
    }

    public function testComponentHandlesNegativeBalance(): void
    {
        $classRoom = $this->createClassRoom('4B');

        // Expense more than payments
        $this->createClassExpense($classRoom, 'Big Expense', Money::of(50, 'PLN'));
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(TotalCashComponent::class, [
            'classRoom' => $classRoom,
        ]);
        /** @var TotalCashComponent $component */
        $component = $testComponent->component();

        $this->assertTrue(Money::of(-50, 'PLN')->isEqualTo($component->getTotalCash()));
    }
}
