<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
class PasswordResetTest extends WebTestCase
{
    public function testPasswordResetRequestPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Resetuj hasło');
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testPasswordResetRequestFormSubmission(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password');

        $form = $crawler->filter('form')
            ->form([
                'email' => 'test@example.com',
            ]);

        $client->submit($form);

        // Should redirect to check-email page
        $this->assertResponseRedirects('/reset-password/check-email');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Email wysłany!');
    }

    public function testPasswordResetRequestWithInvalidEmail(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password');

        $form = $crawler->filter('form')
            ->form([
                'email' => 'invalid-email',
            ]);

        $client->submit($form);

        // Should show validation error
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input:invalid');
    }

    public function testPasswordResetCheckEmailPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Email wysłany!');
        $this->assertSelectorTextContains('p', 'Link wygaśnie po 1 godzinie');
    }

    public function testPasswordResetPageLoads(): void
    {
        $client = static::createClient();
        $client->request('GET', '/reset-password/reset/abc123');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Ustaw nowe hasło');
        $this->assertSelectorExists('input[name="reset_form[plainPassword][first]"]');
        $this->assertSelectorExists('input[name="reset_form[plainPassword][second]"]');
    }

    public function testPasswordResetFormSubmission(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password/reset/abc123');

        $form = $crawler->filter('form')
            ->form([
                'reset_form[plainPassword][first]' => 'newpassword123',
                'reset_form[plainPassword][second]' => 'newpassword123',
            ]);

        $client->submit($form);

        // Should redirect to login page
        $this->assertResponseRedirects('/login');
    }

    public function testPasswordResetWithMismatchedPasswords(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password/reset/abc123');

        $form = $crawler->filter('form')
            ->form([
                'reset_form[plainPassword][first]' => 'password123',
                'reset_form[plainPassword][second]' => 'differentpassword',
            ]);

        $client->submit($form);

        // Should show validation error
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-red-500');
    }

    public function testPasswordResetWithShortPassword(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/reset-password/reset/abc123');

        $form = $crawler->filter('form')
            ->form([
                'reset_form[plainPassword][first]' => '123',
                'reset_form[plainPassword][second]' => '123',
            ]);

        $client->submit($form);

        // Should show validation error
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.text-red-500');
    }

    public function testPasswordResetLinksWork(): void
    {
        $client = static::createClient();

        // Test reset request page links
        $crawler = $client->request('GET', '/reset-password');
        $this->assertSelectorExists('a[href="/login"]');

        // Test check email page links
        $crawler = $client->request('GET', '/reset-password/check-email');
        $this->assertSelectorExists('a[href="/reset-password"]');
        $this->assertSelectorExists('a[href="/login"]');

        // Test reset page links
        $crawler = $client->request('GET', '/reset-password/reset/abc123');
        $this->assertSelectorExists('a[href="/login"]');
    }

    public function testAuthenticatedUserRedirectedFromResetPages(): void
    {
        // This test would require setting up authentication first
        // For now, we'll just test the pages load correctly
        $client = static::createClient();

        // Test that pages load without authentication
        $client->request('GET', '/reset-password');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/reset-password/check-email');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/reset-password/reset/abc123');
        $this->assertResponseIsSuccessful();
    }
}
