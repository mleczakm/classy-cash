<?php

declare(strict_types=1);

namespace App\Tests\UserInterface\Http\Component;

use App\Entity\ClassCouncil\ClassRole;
use App\UserInterface\Http\Component\FastPaymentModalComponent;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

#[Group('functional')]
final class FastPaymentModalComponentTest extends FunctionalTestCase
{
    use InteractsWithLiveComponents;

    public function testOpenModalOpensWithNoOutstandingPaymentsInstead(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('parent@example.com', 'password123');
        $this->createMembership($user, $classRoom, ClassRole::PARENT);

        $student = $this->createTestStudent($classRoom);
        $student->addParent($user);
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(FastPaymentModalComponent::class, [])
            ->actingAs($user);
        $result = $testComponent->call('openModal');

        /** @var FastPaymentModalComponent $component */
        $component = $result->component();

        self::assertTrue(
            $component->modalOpened,
            'Modal should open even when the user has no outstanding payments, so the "no dues" message can be shown.'
        );
        self::assertNull($component->paymentCode);
    }

    public function testOpenModalGeneratesPaymentForOutstandingBalance(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('parent2@example.com', 'password123');
        $this->createMembership($user, $classRoom, ClassRole::PARENT);

        $student = $this->createTestStudent($classRoom);
        $student->addParent($user);
        $this->createStudentPayment($student, 'Wycieczka', Money::of(50, 'PLN'));
        $this->getEntityManager()
            ->flush();

        $testComponent = $this->createLiveComponent(FastPaymentModalComponent::class, [])
            ->actingAs($user);
        $result = $testComponent->call('openModal');

        /** @var FastPaymentModalComponent $component */
        $component = $result->component();

        self::assertTrue($component->modalOpened);
        self::assertNotNull($component->paymentCode);
    }
}
