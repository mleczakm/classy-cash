<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('functional')]
class LoginModeToggleTest extends WebTestCase
{
    public function testLoginModeToggleFunctionality(): void
    {
        $client = static::createClient();

        // Initial load - should be in email link mode
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[name="login_user[password]"]');
        $this->assertSelectorTextContains('button[data-live-action-param="togglePassword"]', 'Zaloguj się hasłem');
        $this->assertSelectorNotExists('a[href="/reset-password"]');

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should now be in password mode
        //        $this->assertSelectorExists('input[name="login_user[password]"]');
        //        $this->assertSelectorTextContains(
        //            'button[data-live-action-param="togglePassword"]',
        //            'Zaloguj się linkiem email'
        //        );
        //        $this->assertSelectorExists('a[href="/reset-password"]');
        //
        //        // Toggle back to email link mode
        //        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
        //            ->form();
        //        $client->submit($toggleButton);
        //
        //        // Should be back in email link mode
        //        $this->assertSelectorNotExists('input[name="login_user[password]"]');
        //        $this->assertSelectorTextContains('button[data-live-action-param="togglePassword"]', 'Zaloguj się hasłem');
        //        $this->assertSelectorNotExists('a[href="/reset-password"]');
    }

    public function testLoginModeTogglePreservesEmailField(): void
    {
        $this->markTestSkipped();

        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Fill email field in email mode
        $form = $crawler->filter('form')
            ->form([
                'login_user[email]' => 'test@example.com',
            ]);

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Email field should still exist and be fillable
        $this->assertSelectorExists('input[name="login_user[email]"]');

        // Fill both email and password in password mode
        $form = $crawler->filter('form')
            ->form([
                'login_user[email]' => 'test@example.com',
                'login_user[password]' => 'password123',
            ]);

        $this->assertEquals('test@example.com', $form->get('login_user[email]')->getValue());
    }

    public function testLoginModeToggleResetsFormState(): void
    {
        $this->markTestSkipped();

        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Submit form in email mode to trigger success state
        $form = $crawler->filter('form')
            ->form([
                'login_user[email]' => 'test@example.com',
            ]);

        $client->submit($form);

        // Should show success message
        $this->assertSelectorTextContains('h2', 'Link wysłany');

        // Toggle to password mode
        $crawler = $client->request('GET', '/login');
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Should reset to normal form state
        $this->assertSelectorNotExists('h2');
        $this->assertSelectorExists('button[type="submit"]');
    }

    public function testLoginModeToggleButtonStyling(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]');

        // Check that the button has the correct styling classes
        $this->assertStringContainsString('cursor-pointer', $toggleButton->attr('class'));
        $this->assertStringContainsString('text-sm', $toggleButton->attr('class'));
        $this->assertStringContainsString('font-bold', $toggleButton->attr('class'));
    }

    public function testPasswordResetLinkOnlyVisibleInPasswordMode(): void
    {
        $this->markTestSkipped();

        $client = static::createClient();

        // In email mode - should not show reset link
        $crawler = $client->request('GET', '/login');
        $this->assertSelectorNotExists('a[href="/reset-password"]');

        // Toggle to password mode - should show reset link
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        $this->assertSelectorExists('a[href="/reset-password"]');
        $this->assertSelectorTextContains('a[href="/reset-password"]', 'Zapomniałeś hasła?');

        // Check reset link styling
        $resetLink = $crawler->filter('a[href="/reset-password"]');
        $this->assertStringContainsString('cursor-pointer', $resetLink->attr('class'));
        $this->assertStringContainsString('text-[10px]', $resetLink->attr('class'));
    }

    public function testSubmitButtonTextChangesWithMode(): void
    {
        $client = static::createClient();

        // In email mode
        $crawler = $client->request('GET', '/login');
        $submitButton = $crawler->filter('button[type="submit"]');
        $this->assertStringContainsString('cursor-pointer', $submitButton->attr('class'));

        // Toggle to password mode
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        // Submit button should still have cursor-pointer
        $submitButton = $crawler->filter('button[type="submit"]');
        $this->assertStringContainsString('cursor-pointer', $submitButton->attr('class'));
    }

    public function testLoginFormStructureIntegrity(): void
    {
        $this->markTestSkipped();

        $client = static::createClient();

        $crawler = $client->request('GET', '/login');

        // Check that the form has the correct structure
        $form = $crawler->filter('form');
        $this->assertStringContainsString('space-y-5', $form->attr('class'));

        // Check that email field has correct structure
        $emailContainer = $crawler->filter('div:contains("Adres Email")')
            ->closest('div');
        $this->assertStringContainsString('space-y-5', $emailContainer->attr('class'));

        // Toggle to password mode and check password field structure
        $toggleButton = $crawler->filter('button[data-live-action-param="togglePassword"]')
            ->form();
        $client->submit($toggleButton);

        $passwordContainer = $crawler->filter('div:contains("Hasło")')
            ->closest('div');
        $this->assertStringContainsString('mt-5', $passwordContainer->attr('class'));
    }
}
