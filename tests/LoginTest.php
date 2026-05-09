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
    private EntityManagerInterface $entityManager;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    public function testLoginPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Zaloguj się');
        $this->assertSelectorExists('input[name="login_user[email]"]');
    }

    public function testLoginWithValidPassword(): void
    {
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
                'login_user[email]' => 'test@example.com',
                'login_user[password]' => 'password123',
            ]);

        $client->submit($form);

        // Should be redirected to homepage after successful login
        $this->assertResponseRedirects('/');
    }

    public function testLoginWithInvalidPassword(): void
    {
        $client = static::createClient();

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
                'login_user[email]' => 'test@example.com',
                'login_user[password]' => 'wrongpassword',
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
                'login_user[email]' => 'invalid-email',
                'login_user[password]' => 'password123',
            ]);

        $client->submit($form);

        // Should show validation error
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-red-500');
    }

    public function testEmailLinkLoginStillWorks(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Submit with email link mode (default)
        $form = $crawler->filter('form')
            ->form([
                'login_user[email]' => 'test@example.com',
            ]);

        $client->submit($form);

        // Should show success message for email link
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Link wysłany');
    }

    public function testPasswordToggleFunctionality(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Initially should not show password field
        $this->assertSelectorNotExists('input[name="login_user[password]"]');
        $this->assertSelectorTextContains('button[data-live-action-param="togglePassword"]', 'Zaloguj się hasłem');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should now show password field
        $this->assertSelectorExists('input[name="login_user[password]"]');
        $this->assertSelectorTextContains(
            'button[data-live-action-param="togglePassword"]',
            'Zaloguj się linkiem email'
        );
    }

    public function testPasswordResetLinkExists(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should show password reset link
        $this->assertSelectorExists('a[href="/reset-password"]');
        $this->assertSelectorTextContains('a[href="/reset-password"]', 'Zapomniałeś hasła?');
    }

    private function createTestUser(string $email, string $password): User
    {
        $user = new User($email, 'Test User');
        $user->setRoles(['ROLE_USER']);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up database
        $this->entityManager->createQuery('DELETE FROM App\Entity\User u WHERE u.email LIKE :test')
            ->setParameter('test', '%@example.com')
            ->execute();
    }
}
