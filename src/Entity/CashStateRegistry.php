<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\ClassCouncil\ClassExpense;
use App\Repository\CashStateRegistryRepository;
use Brick\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity(repositoryClass: CashStateRegistryRepository::class)]
#[ORM\Table(schema: 'classycash')]
#[ORM\Index(columns: ['transaction_date'], name: 'idx_cash_state_transaction_date')]
#[ORM\Index(columns: ['payment_id'], name: 'idx_cash_state_payment')]
#[ORM\Index(columns: ['transfer_id'], name: 'idx_cash_state_transfer')]
#[ORM\Index(columns: ['expense_id'], name: 'idx_cash_state_expense')]
class CashStateRegistry
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\Column(type: 'datetime_immutable')]
        private \DateTimeImmutable $transactionDate,
        #[ORM\Column(type: 'json_document')]
        private Money $balanceAfter,
        #[ORM\Column(type: 'json_document')]
        private Money $transactionAmount,
        #[ORM\Column(type: 'string', length: 20)]
        private string $transactionType,
        #[ORM\ManyToOne(targetEntity: Payment::class)]
        #[ORM\JoinColumn(name: 'payment_id', referencedColumnName: 'id', nullable: true)]
        private ?Payment $payment = null,
        #[ORM\ManyToOne(targetEntity: Transfer::class)]
        #[ORM\JoinColumn(name: 'transfer_id', referencedColumnName: 'id', nullable: true)]
        private ?Transfer $transfer = null,
        #[ORM\ManyToOne(targetEntity: ClassExpense::class)]
        #[ORM\JoinColumn(name: 'expense_id', referencedColumnName: 'id', nullable: true)]
        private ?ClassExpense $expense = null
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getTransactionDate(): \DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function getBalanceAfter(): Money
    {
        return $this->balanceAfter;
    }

    public function getTransactionAmount(): Money
    {
        return $this->transactionAmount;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function getTransfer(): ?Transfer
    {
        return $this->transfer;
    }

    public function getExpense(): ?ClassExpense
    {
        return $this->expense;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
