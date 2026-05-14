<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use Brick\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Contribution
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * @var Collection<int, Student>
     */
    #[ORM\ManyToMany(targetEntity: Student::class, inversedBy: 'contributions')]
    #[ORM\JoinTable(name: 'contribution_students')]
    private Collection $students;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'integer')]
    private int $paidCount = 0;

    #[ORM\Column(type: 'json_document')]
    private Money $totalPaid;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ClassRoom::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private ClassRoom $classRoom,
        #[ORM\Column(type: 'string', length: 255)]
        private string $title,
        #[ORM\Column(type: 'json_document')]
        private Money $amount,
        #[ORM\Column(type: 'datetime_immutable', nullable: true)]
        private ?\DateTimeImmutable $dueAt = null
    ) {
        $this->id = new Ulid();
        $this->createdAt = new \DateTimeImmutable();
        $this->students = new ArrayCollection();
        $this->totalPaid = Money::of(0, 'PLN');
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getClassRoom(): ClassRoom
    {
        return $this->classRoom;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getDueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?\DateTimeImmutable $dueAt): void
    {
        $this->dueAt = $dueAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Student>
     */
    public function getStudents(): Collection
    {
        return $this->students;
    }

    public function addStudent(Student $student): void
    {
        if (! $this->students->contains($student)) {
            $this->students->add($student);
        }
    }

    public function removeStudent(Student $student): void
    {
        $this->students->removeElement($student);
    }

    public function getPaidCount(): int
    {
        return $this->paidCount;
    }

    public function setPaidCount(int $paidCount): void
    {
        $this->paidCount = $paidCount;
    }

    public function getTotalPaid(): Money
    {
        return $this->totalPaid;
    }

    public function setTotalPaid(Money $totalPaid): void
    {
        $this->totalPaid = $totalPaid;
    }

    public function getProgressPercentage(): float
    {
        if ($this->students->isEmpty()) {
            return 0.0;
        }

        $expectedAmount = $this->amount->multipliedBy($this->students->count());
        if ($expectedAmount->isZero()) {
            return 0.0;
        }

        return $this->totalPaid->getAmount()
            ->dividedBy($expectedAmount->getAmount())
            ->toFloat() * 100;
    }

    public function getRemainingAmount(): Money
    {
        $expectedAmount = $this->amount->multipliedBy($this->students->count());
        return $expectedAmount->minus($this->totalPaid);
    }

    public function isOverdue(): bool
    {
        return $this->dueAt && $this->dueAt < new \DateTimeImmutable();
    }
}
