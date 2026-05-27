<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\RecalculateCashState;
use App\Entity\Payment;
use App\Entity\Transfer;
use App\Entity\ClassCouncil\ClassExpense;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
class RecalculateCashStateTest extends TestCase
{
    public function testConstructWithNoParameters(): void
    {
        $command = new RecalculateCashState();
        $this->assertNull($command->payment);
        $this->assertNull($command->transfer);
        $this->assertNull($command->expense);
        $this->assertNull($command->fromDate);
    }

    public function testConstructWithPayment(): void
    {
        $payment = $this->createMock(Payment::class);
        $command = new RecalculateCashState(payment: $payment);
        $this->assertSame($payment, $command->payment);
        $this->assertNull($command->transfer);
        $this->assertNull($command->expense);
        $this->assertNull($command->fromDate);
    }

    public function testConstructWithTransfer(): void
    {
        $transfer = $this->createMock(Transfer::class);
        $command = new RecalculateCashState(transfer: $transfer);
        $this->assertNull($command->payment);
        $this->assertSame($transfer, $command->transfer);
        $this->assertNull($command->expense);
        $this->assertNull($command->fromDate);
    }

    public function testConstructWithExpense(): void
    {
        $expense = $this->createMock(ClassExpense::class);
        $command = new RecalculateCashState(expense: $expense);
        $this->assertNull($command->payment);
        $this->assertNull($command->transfer);
        $this->assertSame($expense, $command->expense);
        $this->assertNull($command->fromDate);
    }

    public function testConstructWithFromDate(): void
    {
        $date = new \DateTimeImmutable('2024-01-01');
        $command = new RecalculateCashState(fromDate: $date);
        $this->assertNull($command->payment);
        $this->assertNull($command->transfer);
        $this->assertNull($command->expense);
        $this->assertSame($date, $command->fromDate);
    }
}
