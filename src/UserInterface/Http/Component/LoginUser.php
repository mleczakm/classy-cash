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

#[AsLiveComponent]
class LoginUser extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public bool $isSubmitted = false;

    #[LiveProp]
    public bool $isSuccessful = false;

    #[LiveProp]
    public bool $usePassword = false;

    #[LiveProp]
    public string $submittedEmail = '';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    /**
     * @return FormInterface<array{email: string, password?: string}>
     */
    protected function instantiateForm(): FormInterface
    {
        $formBuilder = $this->createFormBuilder(null, [
            'csrf_protection' => false,
        ])
            ->add('email', EmailType::class, [
                'constraints' => [new Email(), new NotBlank()],
            ]);

        if ($this->usePassword) {
            $formBuilder->add('password', PasswordType::class, [
                'constraints' => [new NotBlank()],
                'label' => 'Hasło',
            ]);
        }

        /** @var FormInterface<array{email: string, password?: string}> $form */
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
            $this->submittedEmail = $data['email'];
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
        $this->submittedEmail = '';
    }

    #[LiveAction]
    public function resendEmail(): void
    {
        // Resend email immediately if we have the submitted email
        if ($this->submittedEmail) {
            $this->messageBus->dispatch(new SendLoginNotification($this->submittedEmail));
            // Keep component in success state to show confirmation again
            $this->isSubmitted = true;
            $this->isSuccessful = true;
        }
    }

    #[LiveAction]
    public function changeEmail(): void
    {
        // Reset component state to allow changing email
        $this->isSubmitted = false;
        $this->isSuccessful = false;
        $this->submittedEmail = '';
        $this->resetForm();
    }
}
