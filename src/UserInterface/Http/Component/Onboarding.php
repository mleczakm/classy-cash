<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\User;
use App\Form\Model\OnboardingDto;
use App\Form\Type\OnboardingType;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Flow\FormFlowInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Onboarding extends AbstractController
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;

    #[LiveProp]
    public ?OnboardingDto $initialFormData = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Settings $settings,
    ) {}

    /**
     * @return FormInterface<OnboardingDto>
     */
    protected function instantiateForm(): FormInterface
    {
        /** @var FormInterface<OnboardingDto> */
        return $this->createForm(OnboardingType::class, $this->initialFormData ?? new OnboardingDto());
    }

    public function getCurrentStepName(): string
    {
        $form = $this->getForm();
        if ($form instanceof FormFlowInterface) {
            return $form->getCursor()
                ->getCurrentStep();
        }
        return 'identity';
    }

    #[LiveAction]
    public function submit(#[LiveArg] ?string $direction = null): ?Response
    {
        // Manually inject the clicked button into formValues so the Form Flow detects it
        if ($direction) {
            $this->formValues['navigator'][$direction] = '';
        }

        $this->isValidated = true;

        try {
            $this->submitForm();
        } catch (UnprocessableEntityHttpException) {
            return null;
        }

        $flow = $this->getForm();
        if ($flow instanceof FormFlowInterface && ! $flow->isFinished()) {
            $form = $flow->getStepForm();
        } else {
            $form = $flow;
        }

        if ($flow instanceof FormFlowInterface && ! $flow->isFinished()) {
            /** @var OnboardingDto $data */
            $data = $flow->getData();
            $data->step = $flow->getCursor()
                ->getCurrentStep();
            $this->initialFormData = $data;
            $this->isValidated = false;
            $this->resetForm(false);
            return null;
        }

        /** @var OnboardingDto $dto */
        $dto = $form->getData();

        // Final Submit: Persistence
        // 1. Create User
        $user = new User();
        $user->setName(($dto->firstName ?? '') . ' ' . ($dto->lastName ?? ''));
        $user->setEmail($dto->email ?? '');
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $dto->password ?? ''));
        $user->setConfirmedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);

        // 2. Create ClassRoom
        $classRoom = new ClassRoom($dto->className ?? '');
        $this->entityManager->persist($classRoom);

        // 3. Create Membership
        $membership = new ClassMembership($user, $classRoom, ClassRole::TREASURER);
        $this->entityManager->persist($membership);

        // 4. Save Settings
        $this->settings->set('app_name', 'Classy Cash ' . ($dto->className ?? ''));
        $this->settings->set('blik_phone', $dto->blikPhone ?? '');
        $this->settings->set('transfer_account', $dto->accountNumber ?? '');
        $this->settings->set('school_name', $dto->schoolName ?? '');

        if ($dto->automationEmail !== null && $dto->automationEmail !== '') {
            $this->settings->set('automation_email', $dto->automationEmail);
            $this->settings->set('automation_password', $dto->automationPassword);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('homepage');
    }
}
