<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FirstAdminSetupController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/setup/admin', name: 'first_admin_setup')]
    public function setup(): Response
    {
        // Redirect if users already exist
        if ($this->userRepository->countUsers() > 0) {
            return $this->redirectToRoute('homepage');
        }

        // Redirect if user is already authenticated
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        return $this->render('first_admin_setup.html.twig');
    }
}
