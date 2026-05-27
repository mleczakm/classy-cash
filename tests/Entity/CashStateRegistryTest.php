<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use Symfony\Component\Uid\Ulid;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\CashStateRegistry;
use App\Entity\ClassCouncil\ClassExpense;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class CashStateRegistryTest extends TestCase
{
    public function testCreateWithPayment(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, 'PLN'));
        $date = new \DateTimeImmutable('2024-01-01');
        $balance = Money::of(150, 'PLN');
        $amount = Money::of(100, 'PLN');

        $registry = new CashStateRegistry($date, $balance, $amount, 'income', $payment);

        $this->assertSame($payment, $registry->getPayment());
        $this->assertNull($registry->getTransfer());
        $this->assertNull($registry->getExpense());
        $this->assertSame($balance, $registry->getBalanceAfter());
        $this->assertSame($amount, $registry->getTransactionAmount());
        $this->assertSame('income', $registry->getTransactionType());
        $this->assertSame($date, $registry->getTransactionDate());
    }

    public function testCreateWithTransfer(): void
    {
        $transfer = new Transfer(
            '123456789',
            'Test Sender',
            'Test Title',
            '100.00',
            new \DateTimeImmutable('2024-01-01')
        );
        $date = new \DateTimeImmutable('2024-01-01');
        $balance = Money::of(150, 'PLN');
        $amount = Money::of(100, 'PLN');

        $registry = new CashStateRegistry($date, $balance, $amount, 'income', null, $transfer);

        $this->assertNull($registry->getPayment());
        $this->assertSame($transfer, $registry->getTransfer());
        $this->assertNull($registry->getExpense());
        $this->assertSame($balance, $registry->getBalanceAfter());
        $this->assertSame($amount, $registry->getTransactionAmount());
        $this->assertSame('income', $registry->getTransactionType());
    }

    public function testCreateWithExpense(): void
    {
        $expense = new ClassExpense($this->createMock(ClassRoom::class), 'Test Expense', Money::of(50, 'PLN'));
        $date = new \DateTimeImmutable('2024-01-01');
        $balance = Money::of(100, 'PLN');
        $amount = Money::of(50, 'PLN');

        $registry = new CashStateRegistry($date, $balance, $amount, 'expense', null, null, $expense);

        $this->assertNull($registry->getPayment());
        $this->assertNull($registry->getTransfer());
        $this->assertSame($expense, $registry->getExpense());
        $this->assertSame($balance, $registry->getBalanceAfter());
        $this->assertSame($amount, $registry->getTransactionAmount());
        $this->assertSame('expense', $registry->getTransactionType());
    }

    public function testGetId(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, 'PLN'));
        $registry = new CashStateRegistry(
            new \DateTimeImmutable('2024-01-01'),
            Money::of(150, 'PLN'),
            Money::of(100, 'PLN'),
            'income',
            $payment
        );

        $this->assertInstanceOf(Ulid::class, $registry->getId());
    }

    public function testGetCreatedAt(): void
    {
        $payment = new Payment(UserAssembler::new()->assemble(), Money::of(100, 'PLN'));
        $before = new \DateTimeImmutable();
        $registry = new CashStateRegistry(
            new \DateTimeImmutable('2024-01-01'),
            Money::of(150, 'PLN'),
            Money::of(100, 'PLN'),
            'income',
            $payment
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $registry->getCreatedAt());
        $this->assertLessThanOrEqual($after, $registry->getCreatedAt());
    }
}
