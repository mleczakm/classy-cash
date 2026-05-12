<?php

declare(strict_types=1);

namespace App\Tests\Repository\ClassCouncil;

use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\ClassCouncil\ClassRoom;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[\PHPUnit\Framework\Attributes\Group('functional')]
final class ClassExpenseRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    private $repository;

    private ClassRoom $classRoom;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = $this->em->getRepository(ClassExpense::class);

        // Create a test class room
        $this->classRoom = new ClassRoom('Test Class 4A');
        $this->em->persist($this->classRoom);
        $this->em->flush();
    }

    public function testFindByClassReturnsExpensesForCorrectClass(): void
    {
        // Create expenses for test class
        $expense1 = new ClassExpense($this->classRoom, 'Test Expense 1', Money::of(100, 'PLN'));
        $expense2 = new ClassExpense($this->classRoom, 'Test Expense 2', Money::of(50, 'PLN'));

        // Create another class and expense for it
        $otherClass = new ClassRoom('Other Class 4B');
        $this->em->persist($otherClass);
        $this->em->flush();

        $otherExpense = new ClassExpense($otherClass, 'Other Expense', Money::of(75, 'PLN'));

        $this->em->persist($expense1);
        $this->em->persist($expense2);
        $this->em->persist($otherExpense);
        $this->em->flush();

        // Test findByClass method
        $expenses = $this->repository->findByClass($this->classRoom);

        // Should return only expenses for our test class
        $this->assertCount(2, $expenses);

        $labels = array_map(fn($expense) => $expense->getLabel(), $expenses);
        $this->assertContains('Test Expense 1', $labels);
        $this->assertContains('Test Expense 2', $labels);
        $this->assertNotContains('Other Expense', $labels);

        // Verify amounts
        foreach ($expenses as $expense) {
            $this->assertInstanceOf(ClassExpense::class, $expense);
            $this->assertSame($this->classRoom, $expense->getClassRoom());
            $this->assertInstanceOf(Money::class, $expense->getAmount());
        }
    }

    public function testFindByClassReturnsEmptyArrayForClassWithNoExpenses(): void
    {
        // Create a new class with no expenses
        $emptyClass = new ClassRoom('Empty Class');
        $this->em->persist($emptyClass);
        $this->em->flush();

        $expenses = $this->repository->findByClass($emptyClass);

        $this->assertIsArray($expenses);
        $this->assertCount(0, $expenses);
    }

    public function testFindByClassOrdersBySpentAtDesc(): void
    {
        // Create expenses with different dates
        $oldDate = new \DateTimeImmutable('2024-01-01');
        $newDate = new \DateTimeImmutable('2024-02-01');
        $newestDate = new \DateTimeImmutable('2024-03-01');

        $expense1 = new ClassExpense($this->classRoom, 'Old Expense', Money::of(100, 'PLN'));
        $expense1->setSpentAt($oldDate);

        $expense2 = new ClassExpense($this->classRoom, 'New Expense', Money::of(50, 'PLN'));
        $expense2->setSpentAt($newDate);

        $expense3 = new ClassExpense($this->classRoom, 'Newest Expense', Money::of(75, 'PLN'));
        $expense3->setSpentAt($newestDate);

        $this->em->persist($expense1);
        $this->em->persist($expense2);
        $this->em->persist($expense3);
        $this->em->flush();

        $expenses = $this->repository->findByClass($this->classRoom);

        $this->assertCount(3, $expenses);

        // Should be ordered by spentAt DESC (newest first)
        $this->assertSame('Newest Expense', $expenses[0]->getLabel());
        $this->assertSame('New Expense', $expenses[1]->getLabel());
        $this->assertSame('Old Expense', $expenses[2]->getLabel());
    }

    public function testExpensePropertiesAreAccessible(): void
    {
        $testDate = new \DateTimeImmutable('2024-01-15');
        $expense = new ClassExpense($this->classRoom, 'Test Expense', Money::of(150, 'PLN'));
        $expense->setSpentAt($testDate);
        $expense->setDescription('Test description');

        $this->em->persist($expense);
        $this->em->flush();

        $expenses = $this->repository->findByClass($this->classRoom);
        $this->assertCount(1, $expenses);

        $foundExpense = $expenses[0];

        // Test all properties
        $this->assertSame('Test Expense', $foundExpense->getLabel());
        $this->assertSame('Test description', $foundExpense->getDescription());
        $this->assertEquals(Money::of(150, 'PLN'), $foundExpense->getAmount());
        $this->assertEquals($testDate, $foundExpense->getSpentAt());
        $this->assertSame($this->classRoom, $foundExpense->getClassRoom());
        $this->assertInstanceOf(\DateTimeImmutable::class, $foundExpense->getCreatedAt());
    }
}
