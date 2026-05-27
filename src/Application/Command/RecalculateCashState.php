<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Entity\Payment;
use App\Entity\Transfer;
use App\Entity\ClassCouncil\ClassExpense;

final readonly class RecalculateCashState
{
    public function __construct(
        public ?Payment $payment = null,
        public ?Transfer $transfer = null,
        public ?ClassExpense $expense = null,
        public ?\DateTimeImmutable $fromDate = null
    ) {}
}
