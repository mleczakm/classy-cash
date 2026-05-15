<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'payment_code', schema: 'classycash')]
#[ORM\UniqueConstraint(name: 'classycash_uniq_payment_code', columns: ['code'])]
class PaymentCode
{
    public const CODE_LENGTH = 4;

    public const CHARS = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Exclude I and O for readability

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'SEQUENCE')]
    #[ORM\SequenceGenerator(sequenceName: 'classycash.payment_code_id_seq', allocationSize: 1, initialValue: 1)]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 4, unique: true)]
    private string $code;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\OneToOne(targetEntity: Payment::class, inversedBy: 'paymentCode')]
        #[ORM\JoinColumn(nullable: false)]
        private Payment $payment
    ) {
        $this->payment->setPaymentCode($this);
        $this->createdAt = new \DateTimeImmutable();
        $this->generateCode();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getPayment(): Payment
    {
        return $this->payment;
    }

    public function setPayment(Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function generateCode(): void
    {
        $max = strlen(self::CHARS) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, $max)];
        }

        $this->code = $code;
    }
}
