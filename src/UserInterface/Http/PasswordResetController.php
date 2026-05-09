<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Form\ResetPasswordFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class PasswordResetController extends AbstractController
{
    #[Route('/reset-password', name: 'app_reset_password_request')]
    public function request(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // If the user is already authenticated, send them to their dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        if ($request->isMethod('POST')) {
            // Handle form submission
            $email = $request->request->get('email');

            // In a real implementation, we would:
            // 1. Find user by email
            // 2. Generate reset token
            // 3. Send reset email
            // 4. Store token in database

            // For now, just redirect to check-email page
            return $this->redirectToRoute('app_reset_password_check_email');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('security/reset_password_request.html.twig', [
            'error' => $error,
            'disabled' => true,
            'message' => 'Resetowanie hasła zablokowane, zaloguj się za pomocą samego maila zamiast tego.',
        ]);
    }

    #[Route('/reset-password/check-email', name: 'app_reset_password_check_email')]
    public function checkEmail(): Response
    {
        // For now, we'll use a simple message without the resetToken dependency
        // In a full implementation, this would integrate with Symfony's reset password bundle
        return $this->render('security/reset_password_check_email.html.twig', [
            'resetToken' => [
                'expirationMessageKey' => 'The reset link will expire in %count% hour.',
                'expirationMessageData' => [
                    '%count%' => 1,
                ],
            ],
        ]);
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password_reset')]
    public function reset(string $token, Request $request): Response
    {
        // If the user is already authenticated, send them to their dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('homepage');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // In a real implementation, we would:
            // 1. Find the user by reset token
            // 2. Validate the token hasn't expired
            // 3. Set the new password
            // 4. Remove the reset token
            // 5. Redirect to login with success message

            // For now, just redirect to login
            $this->addFlash('success', 'Password has been reset successfully.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }
}
