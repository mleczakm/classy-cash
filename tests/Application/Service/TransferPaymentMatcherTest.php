<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\TransferPaymentMatcher;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\TransferAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
final class TransferPaymentMatcherTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private TransferPaymentMatcher $matcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->matcher = self::getContainer()->get(TransferPaymentMatcher::class);
    }

    #[Test]
    public function ranksPaymentsWithHistoricalStudentMatchFirst(): void
    {
        $parent = UserAssembler::new()
            ->withName('Anna Kowalska')
            ->withEmail('anna@example.com')
            ->assemble();
        $this->entityManager->persist($parent);

        $classRoom = new ClassRoom('4A');
        $this->entityManager->persist($classRoom);

        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $student->addParent($parent);
        $this->entityManager->persist($student);

        $historicalPayment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $this->entityManager->persist($historicalPayment);

        $historicalStudentPayment = new StudentPayment($student, 'Wycieczka', Money::of('100.00', 'PLN'));
        $historicalStudentPayment->setPayment($historicalPayment);
        $historicalStudentPayment->setStatus(StudentPayment::STATUS_PAID);
        $this->entityManager->persist($historicalStudentPayment);

        $historicalTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withTitle('Wycieczka')
            ->withAmount('100.00')
            ->assemble();
        $historicalTransfer->setPayment($historicalPayment);
        $this->entityManager->persist($historicalTransfer);

        $otherParent = UserAssembler::new()
            ->withName('Inny Rodzic')
            ->withEmail('inny@example.com')
            ->assemble();
        $this->entityManager->persist($otherParent);

        $otherPayment = PaymentAssembler::new()
            ->withUser($otherParent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($otherPayment);

        $matchedPayment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($matchedPayment);

        $matchedStudentPayment = new StudentPayment($student, 'Obiad', Money::of('100.00', 'PLN'));
        $matchedStudentPayment->setPayment($matchedPayment);
        $this->entityManager->persist($matchedStudentPayment);

        $incomingTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withTitle('Brak kodu')
            ->withAmount('100.00')
            ->assemble();
        $this->entityManager->persist($incomingTransfer);
        $this->entityManager->flush();

        $enriched = $this->matcher->enrichAssignablePayments($incomingTransfer, [$otherPayment, $matchedPayment]);

        self::assertSame($matchedPayment->getId(), $enriched['payments'][0]->getId());
        self::assertArrayHasKey($matchedPayment->getId()->toRfc4122(), $enriched['hints']);
        self::assertStringContainsString(
            'Jan Kowalski',
            $enriched['hints'][$matchedPayment->getId()->toRfc4122()]->getDescription()
        );
    }

    #[Test]
    public function matchesIncomingTransferByHistoricalStudentDataWhenAmountMatches(): void
    {
        $parent = UserAssembler::new()
            ->withName('Anna Kowalska')
            ->withEmail('anna@example.com')
            ->assemble();
        $this->entityManager->persist($parent);

        $classRoom = new ClassRoom('4A');
        $this->entityManager->persist($classRoom);

        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $student->addParent($parent);
        $this->entityManager->persist($student);

        $historicalPayment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $this->entityManager->persist($historicalPayment);

        $historicalStudentPayment = new StudentPayment($student, 'Wycieczka', Money::of('100.00', 'PLN'));
        $historicalStudentPayment->setPayment($historicalPayment);
        $historicalStudentPayment->setStatus(StudentPayment::STATUS_PAID);
        $this->entityManager->persist($historicalStudentPayment);

        $historicalTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withTitle('Wycieczka')
            ->withAmount('100.00')
            ->assemble();
        $historicalTransfer->setPayment($historicalPayment);
        $this->entityManager->persist($historicalTransfer);

        $pendingPayment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($pendingPayment);

        $pendingStudentPayment = new StudentPayment($student, 'Obiad', Money::of('100.00', 'PLN'));
        $pendingStudentPayment->setPayment($pendingPayment);
        $this->entityManager->persist($pendingStudentPayment);

        $incomingTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withTitle('Brak kodu')
            ->withAmount('100.00')
            ->assemble();
        $this->entityManager->persist($incomingTransfer);
        $this->entityManager->flush();

        $matchedPayment = $this->matcher->matchByHistoricalData($incomingTransfer);

        self::assertNotNull($matchedPayment);
        self::assertTrue($pendingPayment->getId()->equals($matchedPayment->getId()));
    }

    #[Test]
    public function doesNotAutoMatchWhenMultipleHistoricalCandidatesExist(): void
    {
        $parent = UserAssembler::new()
            ->withName('Anna Kowalska')
            ->withEmail('anna@example.com')
            ->assemble();
        $this->entityManager->persist($parent);

        $classRoom = new ClassRoom('4A');
        $this->entityManager->persist($classRoom);

        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $student->addParent($parent);
        $this->entityManager->persist($student);

        $historicalPayment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PAID)
            ->assemble();
        $this->entityManager->persist($historicalPayment);

        $historicalStudentPayment = new StudentPayment($student, 'Wycieczka', Money::of('100.00', 'PLN'));
        $historicalStudentPayment->setPayment($historicalPayment);
        $historicalStudentPayment->setStatus(StudentPayment::STATUS_PAID);
        $this->entityManager->persist($historicalStudentPayment);

        $historicalTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withAmount('100.00')
            ->assemble();
        $historicalTransfer->setPayment($historicalPayment);
        $this->entityManager->persist($historicalTransfer);

        $firstPending = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($firstPending);

        $firstStudentPayment = new StudentPayment($student, 'Obiad', Money::of('100.00', 'PLN'));
        $firstStudentPayment->setPayment($firstPending);
        $this->entityManager->persist($firstStudentPayment);

        $secondPending = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount(Money::of('100.00', 'PLN'))
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $this->entityManager->persist($secondPending);

        $secondStudentPayment = new StudentPayment($student, 'Książki', Money::of('100.00', 'PLN'));
        $secondStudentPayment->setPayment($secondPending);
        $this->entityManager->persist($secondStudentPayment);

        $incomingTransfer = TransferAssembler::new()
            ->withAccountNumber('PL61109010140000071219812874')
            ->withSender('Anna Kowalska')
            ->withAmount('100.00')
            ->assemble();
        $this->entityManager->persist($incomingTransfer);
        $this->entityManager->flush();

        self::assertNull($this->matcher->matchByHistoricalData($incomingTransfer));
    }
}
