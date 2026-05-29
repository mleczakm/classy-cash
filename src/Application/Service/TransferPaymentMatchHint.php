<?php

declare(strict_types=1);

namespace App\Application\Service;

final readonly class TransferPaymentMatchHint
{
    /**
     * @param list<string> $studentNames
     */
    public function __construct(
        public array $studentNames,
        public bool $sameAccountNumber,
        public bool $sameSender,
        public int $historicalMatchCount,
        public int $score,
    ) {}

    public function hasStudentMatch(): bool
    {
        return $this->studentNames !== [];
    }

    public function getDescription(): string
    {
        $source = match (true) {
            $this->sameAccountNumber && $this->sameSender => 'Ten sam nadawca i numer konta',
            $this->sameAccountNumber => 'Ten sam numer konta',
            default => 'Ten sam nadawca',
        };

        if ($this->hasStudentMatch()) {
            return sprintf(
                '%s — wcześniej przypisano do: %s',
                $source,
                implode(', ', array_unique($this->studentNames))
            );
        }

        return sprintf('%s — wcześniej przypisano do tego rodzica', $source);
    }
}
