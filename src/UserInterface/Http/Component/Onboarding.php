<?php

declare(strict_types=1);

namespace App\UserInterface\Http\Component;

use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\User;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class Onboarding extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp]
    public int $step = 1;

    // Step 1: User
    #[LiveProp(writable: true)]
    public string $firstName = '';

    #[LiveProp(writable: true)]
    public string $lastName = '';

    #[LiveProp(writable: true)]
    public string $email = '';

    #[LiveProp(writable: true)]
    public string $password = '';

    // Step 2: Class
    #[LiveProp(writable: true)]
    public string $className = '';

    #[LiveProp(writable: true)]
    public string $schoolName = '';

    #[LiveProp(writable: true)]
    public string $blikPhone = '';

    #[LiveProp(writable: true)]
    public string $accountNumber = '';

    // Step 3: Automation
    #[LiveProp(writable: true)]
    public string $automationEmail = '';

    #[LiveProp(writable: true)]
    public string $automationPassword = '';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Settings $settings,
    ) {}

    #[LiveAction]
    public function setStep(#[LiveArg] int $step): void
    {
        $this->step = $step;
    }

    #[LiveAction]
    public function submit(): \Symfony\Component\HttpFoundation\Response
    {
        if ($this->firstName === '' || $this->lastName === '' || $this->email === '' || $this->password === '') {
            $this->step = 1;
            return $this->render('onboarding/index.html.twig');
        }

        if ($this->className === '') {
            $this->step = 2;
            return $this->render('onboarding/index.html.twig');
        }
        // 1. Create User
        $user = new User();
        $user->setName($this->firstName . ' ' . $this->lastName);
        $user->setEmail($this->email);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $user->setPassword($this->passwordHasher->hashPassword($user, $this->password));
        $user->setConfirmedAt(new \DateTimeImmutable());
        $this->entityManager->persist($user);

        // 2. Create ClassRoom
        $classRoom = new ClassRoom($this->className);
        $this->entityManager->persist($classRoom);

        // 3. Create Membership
        $membership = new ClassMembership($user, $classRoom, ClassRole::TREASURER);
        $this->entityManager->persist($membership);

        // 4. Save Settings
        $this->settings->set('app_name', 'Classy Cash ' . $this->className);
        $this->settings->set('blik_phone', $this->blikPhone);
        $this->settings->set('transfer_account', $this->accountNumber);
        $this->settings->set('school_name', $this->schoolName);
        
        if ($this->automationEmail !== '') {
            $this->settings->set('automation_email', $this->automationEmail);
            $this->settings->set('automation_password', $this->automationPassword);
        }

        $this->entityManager->flush();

        return $this->redirectToRoute('homepage');
    }
}
