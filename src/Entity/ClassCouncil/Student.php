<?php

declare(strict_types=1);

namespace App\Entity\ClassCouncil;

use App\Entity\Contribution;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Ulid;

#[ORM\Entity]
class Student
{
    #[ORM\Id]
    #[ORM\Column(type: 'ulid')]
    private Ulid $id;

    /**
     * Parents linked to this student.
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'students')]
    #[ORM\JoinTable(name: 'student_parent')]
    private Collection $parents;

    /**
     * Student payments for this student.
     * @var Collection<int, StudentPayment>
     */
    #[ORM\OneToMany(targetEntity: StudentPayment::class, mappedBy: 'student')]
    private Collection $studentPayments;

    /**
     * Contributions this student is part of.
     * @var Collection<int, Contribution>
     */
    #[ORM\ManyToMany(targetEntity: Contribution::class, mappedBy: 'students')]
    private Collection $contributions;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: ClassRoom::class, inversedBy: 'students')]
        #[ORM\JoinColumn(nullable: false)]
        private ClassRoom $classRoom,
        #[ORM\Column(length: 64)]
        private string $firstName,
        #[ORM\Column(length: 64)]
        private string $lastName
    ) {
        $this->id = new Ulid();
        $this->parents = new ArrayCollection();
        $this->studentPayments = new ArrayCollection();
        $this->contributions = new ArrayCollection();
    }

    public function getId(): Ulid
    {
        return $this->id;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getClassRoom(): ClassRoom
    {
        return $this->classRoom;
    }

    /**
     * @return Collection<int, User>
     */
    public function getParents(): Collection
    {
        return $this->parents;
    }

    public function addParent(User $user): void
    {
        if (! $this->parents->contains($user)) {
            $this->parents->add($user);
        }
    }

    public function removeParent(User $user): void
    {
        $this->parents->removeElement($user);
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * @return Collection<int, StudentPayment>
     */
    public function getStudentPayments(): Collection
    {
        return $this->studentPayments;
    }

    /**
     * @return Collection<int, Contribution>
     */
    public function getContributions(): Collection
    {
        return $this->contributions;
    }
}
