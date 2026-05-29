<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\FeatureFlag\FeatureName;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegisterUserAction extends AbstractController
{
    public function __construct(
        private readonly FeatureManager $featureManager,
    ) {}

    #[Route('/register', name: 'user_register')]
    public function form(): Response
    {
        if ($this->featureManager->isDisabled(FeatureName::PARENT_REGISTRATION)) {
            $this->addFlash('warning', 'Rejestracja rodziców jest obecnie wyłączona.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('register.html.twig');
    }
}
