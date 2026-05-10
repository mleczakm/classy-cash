<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Entity\ClassCouncil\ClassRoom;
use App\Repository\ClassCouncil\ClassRoomRepository;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/setup')]
#[IsGranted('ROLE_USER')]
final class OnboardSetupController extends AbstractController
{
    public function __construct(
        private readonly ClassRoomRepository $classRooms,
        private readonly Settings $settings,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/', name: 'onboard_setup', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        // Check if setup is already completed
        if ($this->classRooms->count() > 0) {
            return $this->redirectToRoute('treasurer_dashboard');
        }

        if ($request->isMethod('POST')) {
            $className = trim((string) $request->request->get('class_name', ''));
            $schoolName = trim((string) $request->request->get('school_name', ''));
            $blikPhone = trim((string) $request->request->get('blik_phone', ''));
            $bankAccount = trim((string) $request->request->get('bank_account', ''));
            $email = trim((string) $request->request->get('email', ''));
            $appPassword = trim((string) $request->request->get('app_password', ''));

            if ($className === '' || $schoolName === '' || $blikPhone === '' || $bankAccount === '' || $email === '' || $appPassword === '') {
                $this->addFlash('error', 'Wszystkie pola są wymagane');
                return $this->redirectToRoute('onboard_setup');
            }

            // Create class room
            $classRoom = new ClassRoom($className);
            $this->em->persist($classRoom);

            // Save settings
            $this->settings->set('app_name', $schoolName);
            $this->settings->set('blik_phone', $blikPhone);
            $this->settings->set('transfer_account', $bankAccount);
            $this->settings->set('setup_email', $email);
            $this->settings->set('setup_password', $appPassword);

            $this->em->flush();

            $this->addFlash('success', 'System został pomyślnie skonfigurowany');
            return $this->redirectToRoute('treasurer_dashboard');
        }

        return $this->render('onboard_setup/index.html.twig', [
            'step' => $request->query->get('step', 1),
        ]);
    }
}
