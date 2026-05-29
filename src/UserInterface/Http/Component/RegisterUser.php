<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\SendLoginNotification;
use App\Entity\User;
use App\FeatureFlag\FeatureName;
use Doctrine\ORM\EntityManagerInterface;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsLiveComponent]
class RegisterUser extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    public ?string $firstName = null;

    public ?string $lastName = null;

    private ?User $user = null;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private FeatureManager $featureManager,
    ) {}

    /**
     * @return FormInterface<User>
     */
    protected function instantiateForm(): FormInterface
    {
        $this->user = new User();

        /** @var FormInterface<User> $form */
        $form = $this->createFormBuilder($this->user)
            ->add('firstName', TextType::class, [
                'mapped' => false,
                'label' => 'form.register.first_name',
                'data' => $this->firstName,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'mapped' => false,
                'label' => 'form.register.last_name',
                'data' => $this->lastName,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],

            ])
            ->add('submit', SubmitType::class, [
                'label' => 'form.register.submit',
            ])
            ->getForm();

        return $form;
    }

    #[LiveAction]
    public function save(): void
    {
        if ($this->featureManager->isDisabled(FeatureName::PARENT_REGISTRATION)) {
            $this->isSubmitted = true;
            $this->isSuccessful = false;

            return;
        }

        $this->submitForm();

        if ($this->getForm()->isValid()) {
            /** @var User $user */
            $user = $this->getForm()
                ->getData();

            $firstNameData = $this->getForm()
                ->get('firstName')
                ->getData();
            $lastNameData = $this->getForm()
                ->get('lastName')
                ->getData();
            $firstName = trim(is_string($firstNameData) ? $firstNameData : '');
            $lastName = trim(is_string($lastNameData) ? $lastNameData : '');
            $user->setName($firstName . ' ' . $lastName);
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new SendLoginNotification($user->getEmail()));

            $this->isSuccessful = true;
            $this->isSubmitted = true;
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

    public function isRegistrationEnabled(): bool
    {
        return $this->featureManager->isEnabled(FeatureName::PARENT_REGISTRATION);
    }
}
