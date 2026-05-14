<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Students;

use App\Entity\User;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ComponentToolsTrait;

#[AsLiveComponent('treasurer:students:add_student')]
class AddStudentComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    #[LiveProp(writable: true)]
    public ?string $firstName = null;

    #[LiveProp(writable: true)]
    public ?string $lastName = null;

    /**
     * @var array<int, int>
     */
    #[LiveProp(writable: true)]
    public array $selectedParents = [];

    private ?Student $student = null;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @return FormInterface<Student>
     */
    protected function instantiateForm(): FormInterface
    {
        $classRoom = $this->getCurrentClassRoom();
        if (! $classRoom) {
            throw new \RuntimeException('No classroom found');
        }

        $this->student = new Student($classRoom, 'placeholder', 'placeholder');

        /** @var FormInterface<Student> $form */
        $form = $this->createFormBuilder($this->student)
            ->add('firstName', TextType::class, [
                'label' => 'Imię',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Imię jest wymagane',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Imię musi mieć co najmniej {{ limit }} znaków',
                        'maxMessage' => 'Imię nie może mieć więcej niż {{ limit }} znaków',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nazwisko',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Nazwisko jest wymagane',
                    ]),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Nazwisko musi mieć co najmniej {{ limit }} znaków',
                        'maxMessage' => 'Nazwisko nie może mieć więcej niż {{ limit }} znaków',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Dodaj ucznia',
            ])
            ->getForm();

        return $form;
    }

    #[LiveAction]
    public function openModal(): void
    {
        $this->modalOpened = true;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalOpened = false;
    }

    #[LiveAction]
    public function addStudent(): void
    {
        $this->submitForm();

        if ($this->getForm()->isValid()) {
            /** @var Student $student */
            $student = $this->getForm()
                ->getData();

            // Link parents
            foreach ($this->selectedParents as $parentId) {
                $parent = $this->users->find($parentId);
                if ($parent) {
                    $student->addParent($parent);
                }
            }

            // Save student
            $this->em->persist($student);
            $this->em->flush();

            $this->emit('studentAdded', [
                'student' => $student,
            ]);
            $this->isSuccessful = true;
            $this->isSubmitted = true;
            $this->modalOpened = false;

            $this->resetForm();
            $this->firstName = null;
            $this->lastName = null;
            $this->selectedParents = [];
        } else {
            $this->isSubmitted = true;
            $this->isSuccessful = false;
        }
    }

    /**
     * @return array<int, User>
     */
    #[LiveAction]
    public function searchParents(string $query): array
    {
        if (strlen($query) < 3) {
            return [];
        }

        return $this->users->findAllMatching($query);
    }

    #[LiveAction]
    public function addParent(int $userId): void
    {
        if (! in_array($userId, $this->selectedParents, true)) {
            $this->selectedParents[] = $userId;
        }
    }

    #[LiveAction]
    public function removeParent(int $userId): void
    {
        $this->selectedParents = array_filter($this->selectedParents, fn($id) => $id !== $userId);
    }

    #[LiveAction]
    public function resetForm(): void
    {
        $this->firstName = null;
        $this->lastName = null;
        $this->selectedParents = [];
        $this->isSubmitted = false;
        $this->isSuccessful = false;
    }

    public function isSubmitted(): bool
    {
        return $this->isSubmitted;
    }

    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    public function getClassCode(): string
    {
        $class = $this->getCurrentClassRoom();
        return $class ? $class->getName() : '';
    }

    /**
     * @return array<int, User>
     */
    public function getPotentialParents(): array
    {
        $allParents = $this->users->findByRole('ROLE_USER');

        return array_filter($allParents, fn($parent) => ! $parent->getStudents()->isEmpty());
    }

    private function getCurrentClassRoom(): ?ClassRoom
    {
        return $this->classRooms->findOneBy([]);
    }
}
