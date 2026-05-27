<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\Payment;
use App\Application\Command\RecalculateCashState;
use App\Application\CommandHandler\RecalculateCashStateHandler;
use App\Repository\CashStateRegistryRepository;
use App\Tests\Functional\FunctionalTestCase;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
class RecalculateCashStateHandlerTest extends FunctionalTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getEntityManager();
    }

    protected function tearDown(): void
    {
        // No cleanup needed - transactions are rolled back automatically
        parent::tearDown();
    }

    public function testHandlerInvokesWithoutError(): void
    {
        $class = $this->createClassRoom('Test Class');
        $user = $this->createUser('test@example.com', 'password');
        $student = $this->createTestStudent($class);
        $studentPayment = $this->createStudentPayment($student, 'Test Payment', Money::of(100, 'PLN'));

        $payment = new Payment($user, Money::of(100, 'PLN'));
        $studentPayment->setPayment($payment);
        $studentPayment->markPaid();
        $this->entityManager->persist($payment);
        $this->entityManager->flush();


        /** @var RecalculateCashStateHandler $handler */
        $handler =   $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState());

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);

        $allEntries = $registryRepository->findAllOrdered();
        $this->assertNotEmpty($allEntries, 'Registry should be populated after handler execution');
    }
}
