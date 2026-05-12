<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class OnboardingController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {}

    #[Route('/onboarding', name: 'onboarding')]
    public function index(): Response
    {
        // Redirect if users already exist
        if ($this->userRepository->countUsers() > 0) {
            return $this->redirectToRoute('homepage');
        }

        return $this->render('onboarding/index.html.twig');
    }
}
