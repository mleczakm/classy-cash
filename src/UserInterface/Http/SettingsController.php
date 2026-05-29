<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Service\SettingsDtoMapper;
use App\Form\Type\SettingsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly SettingsDtoMapper $settingsDtoMapper,
    ) {}

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $dto = $this->settingsDtoMapper->create();
        $form = $this->createForm(SettingsType::class, $dto);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settingsDtoMapper->save($dto);
            $this->addFlash('success', 'Settings have been updated successfully.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings.html.twig', [
            'form' => $form,
        ]);
    }
}
