<?php

declare(strict_types=1);

namespace App\UserInterface\LiveComponent\Treasurer\Students;

use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Repository\ClassCouncil\StudentRepository;
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

#[AsLiveComponent('treasurer:students:add_student')]
class AddStudentComponent extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp(writable: true)]
    public bool $modalOpened = false;

    private ?Student $student = null;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    public function __construct(
        private readonly StudentRepository $students,
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

            // Save student
            $this->em->persist($student);
            $this->em->flush();

            $this->emit('studentAdded', [
                'student' => $student,
            ]);
            $this->isSuccessful = true;
            $this->isSubmitted = true;
            $this->modalOpened = false;
        } else {
            $this->isSubmitted = true;
            $this->isSuccessful = false;
        }
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
