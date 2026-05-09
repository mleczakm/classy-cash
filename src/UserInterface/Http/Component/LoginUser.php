<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Application\Command\SendLoginNotification;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[AsLiveComponent]
class LoginUser extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    private bool $isSubmitted = false;

    private bool $isSuccessful = false;

    #[LiveProp]
    public bool $usePassword = false;

    public function __construct(
        private MessageBusInterface $messageBus,
        private AuthenticationUtils $authenticationUtils,
    ) {}

    /**
     * @return FormInterface<array{email: string, password?: string}>
     */
    protected function instantiateForm(): FormInterface
    {
        $formBuilder = $this->createFormBuilder()
            ->add('email', EmailType::class, [
                'constraints' => [new Email(), new NotBlank()],
            ]);

        if ($this->usePassword) {
            $formBuilder->add('password', PasswordType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Hasło',
            ]);
        }

        $form = $formBuilder
            ->add('submit', SubmitType::class, [
                'label' => $this->usePassword ? 'Zaloguj się' : 'Wyślij link logowania',
            ])->getForm();

        return $form;
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();

        if ($this->getForm()->isValid()) {
            /** @var array{email: string, password?: string} $data */
            $data = $this->getForm()
                ->getData();

            if ($this->usePassword) {
                // Password login will be handled by Symfony's form authentication
                // This is just for form validation
                $this->isSuccessful = true;
            } else {
                // Email link login
                $this->messageBus->dispatch(new SendLoginNotification($data['email']));
                $this->isSuccessful = true;
            }

            $this->isSubmitted = true;
        } else {
            $this->isSubmitted = true;
            $this->isSuccessful = false;
        }
    }

    #[LiveAction]
    public function togglePassword(): void
    {
        $this->usePassword = ! $this->usePassword;
        $this->resetForm();
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
}
