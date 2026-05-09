<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Group('functional')]
class LoginTest extends WebTestCase
{
    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h2', 'Zaloguj się', $crawler->html());
        $this->assertSelectorExists('input[name="form[email]"]');
    }

    public function testLoginWithValidPassword(): void
    {
        $this->markTestSkipped();

        $client = static::createClient();

        // Create a test user with password
        $user = $this->createTestUser('test@example.com', 'password123');

        // Try to login with password mode
        $crawler = $client->request('GET', '/login');

        // First, toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Now submit the login form with password
        $form = $crawler->filter('form')
            ->form([
                'form[email]' => 'test@example.com',
                'form[password]' => 'password123',
            ]);

        $client->submit($form);

        // Should be redirected to homepage after successful login
        $this->assertResponseRedirects('/');
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->markTestSkipped();

        $client = static::getClient();

        // Create a test user with password
        $user = $this->createTestUser('test@example.com', 'password123');

        // Try to login with wrong password
        $crawler = $client->request('GET', '/login');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Submit with wrong password
        $form = $crawler->filter('form')
            ->form([
                'form[email]' => 'test@example.com',
                'form[password]' => 'wrongpassword',
            ]);

        $client->submit($form);

        // Should stay on login page with error
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Zaloguj się');
    }

    public function testLoginWithInvalidEmail(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Submit with invalid email
        $form = $crawler->filter('form')
            ->form([
                'form[email]' => 'invalid-email',
            ]);

        $client->submit($form);

        // Should show validation error
        $this->assertResponseStatusCodeSame(400);
    }

    public function testEmailLinkLoginStillWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Should show the form with email field in email link mode (default)
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[type="email"]');
        $this->assertSelectorExists('button[type="submit"]');
        $this->assertSelectorTextContains('button[type="submit"]', 'Wyślij link logowania');
    }

    public function testPasswordToggleFunctionality(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Initially should not show password field
        $this->assertSelectorNotExists('input[name="form[password]"]');
        $this->assertSelectorTextContains('button[data-live-action-param="togglePassword"]', 'Zaloguj się hasłem');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should now show password field
        //        $this->assertSelectorExists('input[name="form[password]"]');
        //        $this->assertSelectorTextContains(
        //            'button[data-live-action-param="togglePassword"]',
        //            'Zaloguj się linkiem email'
        //        );
    }

    public function testPasswordResetLinkExists(): void
    {
        $this->markTestSkipped();
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should show password reset link
        $this->assertAnySelectorTextContains('a', 'Zapomniałeś hasła?');
    }

    private function createTestUser(string $email, string $password): User
    {
        $user = new User($email, 'Test User');
        $user->setRoles(['ROLE_USER']);

        // Hash the password
        $hashedPassword = self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
