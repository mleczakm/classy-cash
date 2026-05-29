<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\Payment;
use App\Repository\PaymentRepository;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\PaymentCodeAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('functional')]
class PaymentRepositoryTest extends KernelTestCase
{
    private PaymentRepository $paymentRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->paymentRepository = $container->get(PaymentRepository::class);
    }

    public function testFindAssignableWithSearch(): void
    {
        $this->preparePendingPayment();
        $this->prepareExpiredPayment();

        // Test cases
        $this->assertCount(1, $this->paymentRepository->findAssignableWithSearch('Test User'), 'Search by name');
        $this->assertCount(
            1,
            $this->paymentRepository->findAssignableWithSearch('test@example.com'),
            'Search by email'
        );
        $this->assertCount(1, $this->paymentRepository->findAssignableWithSearch('1234'), 'Search by payment code');
        $this->assertCount(1, $this->paymentRepository->findAssignableWithSearch('150'), 'Search by amount');
        $this->assertCount(
            2,
            $this->paymentRepository->findAssignableWithSearch(''),
            'Empty search should return all assignable payments'
        );
        $this->assertCount(
            0,
            $this->paymentRepository->findAssignableWithSearch('nonexistent'),
            'Search with no match'
        );
        $this->assertCount(
            1,
            $this->paymentRepository->findAssignableWithSearch('Expired User'),
            'Search expired by name'
        );
    }

    public function testCountPendingPayments(): void
    {
        self::assertSame(0, $this->paymentRepository->countPendingPayments());

        $this->preparePendingPayment();

        self::assertSame(1, $this->paymentRepository->countPendingPayments());
    }

    private function preparePendingPayment(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        // Setup test data
        $user = UserAssembler::new()
            ->withName('Test User')
            ->withEmail('test@example.com')
            ->assemble();
        $em->persist($user);

        $paymentCode = PaymentCodeAssembler::new()
            ->withCode('1234')
            ->assemble();
        $em->persist($paymentCode);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withPaymentCode($paymentCode)
            ->withAmount(Money::of(150, 'PLN')) // 150.00 PLN
            ->withStatus(Payment::STATUS_PENDING)
            ->assemble();
        $em->persist($payment);

        $em->flush();
    }

    private function prepareExpiredPayment(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        $user = UserAssembler::new()
            ->withName('Expired User')
            ->withEmail('expired@example.com')
            ->assemble();
        $em->persist($user);

        $paymentCode = PaymentCodeAssembler::new()
            ->withCode('5678')
            ->assemble();
        $em->persist($paymentCode);

        $payment = PaymentAssembler::new()
            ->withUser($user)
            ->withPaymentCode($paymentCode)
            ->withAmount(Money::of(200, 'PLN'))
            ->withStatus(Payment::STATUS_EXPIRED)
            ->assemble();
        $em->persist($payment);

        $em->flush();
    }
}
