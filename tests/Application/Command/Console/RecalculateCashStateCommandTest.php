<?php

declare(strict_types=1);

namespace App\Tests\Application\Command\Console;

use Brick\Money\Money;
use App\Entity\Payment;
use App\Repository\CashStateRegistryRepository;
use App\Tests\Functional\FunctionalTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('functional')]
class RecalculateCashStateCommandTest extends FunctionalTestCase
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

    public function testCommandExecutesSuccessfully(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:recalculate-cash-state');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Recalculating cash state for all historical transactions...', $output);
        $this->assertStringContainsString('Cash state recalculation completed.', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testCommandPopulatesRegistry(): void
    {
        // Create test data
        $class = $this->createClassRoom('Test Class');
        $user = $this->createUser('test@example.com', 'password');
        $student = $this->createTestStudent($class);
        $studentPayment = $this->createStudentPayment($student, 'Test Payment', Money::of(100, 'PLN'));

        $payment = new Payment($user, Money::of(100, 'PLN'));
        $studentPayment->setPayment($payment);
        $studentPayment->markPaid();
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:recalculate-cash-state');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        /** @var CashStateRegistryRepository $registryRepository */
        $registryRepository = $this->getService(CashStateRegistryRepository::class);

        $allEntries = $registryRepository->findAllOrdered();
        $this->assertNotEmpty($allEntries, 'Registry should be populated after command execution');
    }
}
