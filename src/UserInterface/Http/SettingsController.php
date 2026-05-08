<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Settings\Settings;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SettingsController extends AbstractController
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    #[Route('/admin/settings', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid('submit', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Nieprawidłowy token CSRF.');
            }

            $appName = trim((string) $request->request->get('app_name', ''));
            $emailFrom = trim((string) $request->request->get('email_from', ''));
            $blikPhone = trim((string) $request->request->get('blik_phone', ''));
            $transferAccount = trim((string) $request->request->get('transfer_account', ''));

            // Update app_name
            if ($appName !== '') {
                $this->settings->set('app_name', $appName);
            }

            // Update email_from
            if ($emailFrom !== '') {
                $this->settings->set('email_from', $emailFrom);
            }

            // Update blik_phone
            if ($blikPhone !== '') {
                $this->settings->set('blik_phone', $blikPhone);
            }

            // Update transfer_account
            if ($transferAccount !== '') {
                $this->settings->set('transfer_account', $transferAccount);
            }

            $this->addFlash('success', 'Settings have been updated successfully.');
            return $this->redirectToRoute('admin_settings');
        }

        // Get current settings with defaults
        $currentSettings = [
            'app_name' => $this->settings->getName(),
            'email_from' => $this->settings->getOptional('email_from', ''),
            'blik_phone' => $this->settings->getOptional('blik_phone', ''),
            'transfer_account' => $this->settings->getOptional('transfer_account', ''),
        ];

        return $this->render('admin/settings.html.twig', $currentSettings);
    }
}
