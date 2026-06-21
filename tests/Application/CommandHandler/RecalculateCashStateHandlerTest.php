<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Entity\CashStateRegistry;
use App\Entity\ClassCouncil\ClassExpense;
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

    public function testExpenseIsAlwaysIncludedWhenRecalculatingForSpecificExpense(): void
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

        // Create an expense
        $expense = new ClassExpense($class, 'Test Expense', Money::of(50, 'PLN'));
        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState(expense: $expense));

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);

        $expenseEntries = $registryRepository->findByExpense($expense);
        $this->assertCount(1, $expenseEntries, 'Expense should have exactly one registry entry');
        $this->assertSame('expense', $expenseEntries[0]->getTransactionType());
    }

    public function testHandlerSkipsRecalculationWhenPaymentEntryAlreadyExists(): void
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

        // First recalculation - should create entry
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState(payment: $payment));

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);
        $paymentEntries = $registryRepository->findByPayment($payment);
        $this->assertCount(
            1,
            $paymentEntries,
            'Payment should have exactly one registry entry after first recalculation'
        );

        // Store the entry ID to verify it's not recreated
        $firstEntryId = $paymentEntries[0]->getId();

        // Second recalculation - should skip because entry already exists
        $handler->__invoke(new RecalculateCashState(payment: $payment));

        $paymentEntriesAfterSecondRun = $registryRepository->findByPayment($payment);
        $this->assertCount(
            1,
            $paymentEntriesAfterSecondRun,
            'Payment should still have exactly one registry entry after second recalculation'
        );
        $this->assertSame(
            $firstEntryId,
            $paymentEntriesAfterSecondRun[0]->getId(),
            'The same entry should exist, not a new one'
        );
    }

    public function testHandlerSkipsRecalculationWhenExpenseEntryAlreadyExists(): void
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

        // Create an expense
        $expense = new ClassExpense($class, 'Test Expense', Money::of(50, 'PLN'));
        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        // First recalculation - should create entry
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState(expense: $expense));

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);
        $expenseEntries = $registryRepository->findByExpense($expense);
        $this->assertCount(
            1,
            $expenseEntries,
            'Expense should have exactly one registry entry after first recalculation'
        );

        // Store the entry ID to verify it's not recreated
        $firstEntryId = $expenseEntries[0]->getId();

        // Second recalculation - should skip because entry already exists
        $handler->__invoke(new RecalculateCashState(expense: $expense));

        $expenseEntriesAfterSecondRun = $registryRepository->findByExpense($expense);
        $this->assertCount(
            1,
            $expenseEntriesAfterSecondRun,
            'Expense should still have exactly one registry entry after second recalculation'
        );
        $this->assertSame(
            $firstEntryId,
            $expenseEntriesAfterSecondRun[0]->getId(),
            'The same entry should exist, not a new one'
        );
    }

    public function testHandlerDoesNotCreateDuplicateEntriesDuringTransactionProcessing(): void
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

        // Create an expense
        $expense = new ClassExpense($class, 'Test Expense', Money::of(50, 'PLN'));
        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        // First full recalculation
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState());

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);
        $allEntries = $registryRepository->findAllOrdered();
        $this->assertCount(2, $allEntries, 'Should have exactly 2 registry entries (1 payment, 1 expense)');

        // Second full recalculation - should not create duplicates
        $handler->__invoke(new RecalculateCashState());

        $allEntriesAfterSecondRun = $registryRepository->findAllOrdered();
        $this->assertCount(
            2,
            $allEntriesAfterSecondRun,
            'Should still have exactly 2 registry entries after second recalculation'
        );
    }

    public function testFullRecalculationCleansUpExistingDuplicates(): void
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

        // Manually create duplicate registry entries for the same payment
        $registry1 = new CashStateRegistry(
            new \DateTimeImmutable('2024-01-01'),
            Money::of(100, 'PLN'),
            Money::of(100, 'PLN'),
            'income',
            $payment
        );
        $registry2 = new CashStateRegistry(
            new \DateTimeImmutable('2024-01-01'),
            Money::of(100, 'PLN'),
            Money::of(100, 'PLN'),
            'income',
            $payment
        );
        $this->entityManager->persist($registry1);
        $this->entityManager->persist($registry2);
        $this->entityManager->flush();

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);
        $paymentEntriesBefore = $registryRepository->findByPayment($payment);
        $this->assertCount(2, $paymentEntriesBefore, 'Should have 2 duplicate entries before cleanup');

        // Run full recalculation - should clean up duplicates
        /** @var RecalculateCashStateHandler $handler */
        $handler = $this->getService(RecalculateCashStateHandler::class);
        $handler->__invoke(new RecalculateCashState());

        $paymentEntriesAfter = $registryRepository->findByPayment($payment);
        $this->assertCount(1, $paymentEntriesAfter, 'Should have only 1 entry after cleanup');
    }
}
