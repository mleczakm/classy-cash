<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use App\UserInterface\Http\Component\LoginUser;

#[Group('functional')]
class LoginModeToggleTest extends WebTestCase
{
    use InteractsWithLiveComponents;
    public function testLoginModeToggleFunctionality(): void
    {
        $testComponent = $this->createLiveComponent(LoginUser::class);
        $component = $testComponent->component();

        // Initial state - should be in email link mode
        $this->assertFalse($component->usePassword);
        $this->assertFalse($component->isSubmitted);
        $this->assertFalse($component->isSuccessful);

        $crawler = $testComponent->render();
        $this->assertCount(0, $crawler->crawler()->filter('input[name="login_user[password]"]'));
        $this->assertStringContainsString('Zaloguj się hasłem', $crawler->crawler()->filter('button[data-live-action-param="togglePassword"]')->text());

        // Toggle to password mode
        $testComponent->call('togglePassword');
        $component = $testComponent->component();
        $this->assertTrue($component->usePassword);
        $this->assertFalse($component->isSubmitted);
        $this->assertFalse($component->isSuccessful);

        $crawler = $testComponent->render();
        $this->assertCount(1, $crawler->crawler()->filter('input[name*="password"]'));
        $this->assertStringContainsString('Zaloguj się linkiem email', $crawler->crawler()->filter('button[data-live-action-param="togglePassword"]')->text());
        $this->assertStringContainsString('Zaloguj się', $crawler->crawler()->filter('button[type="submit"]')->text());

        // Toggle back to email link mode

        $testComponent->call('togglePassword');
        $component = $testComponent->component();
        $this->assertFalse($component->usePassword);

        $crawler = $testComponent->render();
        $this->assertCount(0, $crawler->crawler()->filter('input[name*="password"]'));
        $this->assertStringContainsString('Zaloguj się hasłem', $crawler->crawler()->filter('button[data-live-action-param="togglePassword"]')->text());
        $this->assertStringContainsString('Wyślij link logowania', $crawler->crawler()->filter('button[type="submit"]')->text());
    }

    public function testLoginModeToggleResetsFormState(): void
    {
        // Skip the form submission test for now and focus on the toggle functionality
        // The form submission requires complex mocking that's beyond the scope of this test
        $testComponent = $this->createLiveComponent(LoginUser::class);

        // Simulate a successful submission by setting the component state directly
        $loginComponent = $testComponent->component();
        $loginComponent->isSubmitted = true;
        $loginComponent->isSuccessful = true;
        $loginComponent->submittedEmail = 'test@example.com';

        // Verify the state is set correctly
        $this->assertTrue($loginComponent->isSubmitted);
        $this->assertTrue($loginComponent->isSuccessful);
        $this->assertEquals('test@example.com', $loginComponent->submittedEmail);

        // Toggle to password mode
        $testComponent = $testComponent->call('togglePassword');

        $loginComponent = $testComponent->component();
        // Should reset to normal form state
        $this->assertFalse($loginComponent->isSubmitted);
        $this->assertFalse($loginComponent->isSuccessful);
        $this->assertEquals('', $loginComponent->submittedEmail);
        $this->assertTrue($loginComponent->usePassword);
    }

    public function testLoginModeToggleButtonStyling(): void
    {
        $testComponent = $this->createLiveComponent(LoginUser::class);

        $renderedComponent = $testComponent->render();
        $toggleButton = $renderedComponent->crawler()->filter('button[data-live-action-param="togglePassword"]');

        // Check that the button has the correct styling classes
        $this->assertStringContainsString('cursor-pointer', $toggleButton->attr('class'));
        $this->assertStringContainsString('text-sm', $toggleButton->attr('class'));
        $this->assertStringContainsString('font-bold', $toggleButton->attr('class'));
    }

    public function testPasswordResetLinkOnlyVisibleInPasswordMode(): void
    {
        $testComponent = $this->createLiveComponent(LoginUser::class);

        // In email mode - should not show reset link
        $crawler = $testComponent->render();
        $this->assertCount(0, $crawler->crawler()->filter('a[href="/reset-password"]'));

        // Toggle to password mode - should show reset link
        $testComponent->call('togglePassword');
        $crawler = $testComponent->render();
        $this->assertCount(1, $crawler->crawler()->filter('a[href="/reset-password"]'));
        $this->assertStringContainsString('Zapomniałeś hasła?', $crawler->crawler()->filter('a[href="/reset-password"]')->text());

        // Check reset link styling
        $resetLink = $crawler->crawler()->filter('a[href="/reset-password"]');
        $this->assertStringContainsString('cursor-not-allowed', $resetLink->attr('class'));
        $this->assertStringContainsString('text-[10px]', $resetLink->attr('class'));
    }

    public function testSubmitButtonTextChangesWithMode(): void
    {
        $testComponent = $this->createLiveComponent(LoginUser::class);

        // In email mode
        $crawler = $testComponent->render();
        $submitButton = $crawler->crawler()->filter('button[type="submit"]');
        $this->assertStringContainsString('cursor-pointer', $submitButton->attr('class'));
        $this->assertStringContainsString('Wyślij link logowania', $submitButton->text());

        // Toggle to password mode
        $testComponent->call('togglePassword');
        $crawler = $testComponent->render();
        $submitButton = $crawler->crawler()->filter('button[type="submit"]');
        $this->assertStringContainsString('cursor-pointer', $submitButton->attr('class'));
        $this->assertStringContainsString('Zaloguj się', $submitButton->text());
    }

    public function testLoginFormStructureIntegrity(): void
    {
        $testComponent = $this->createLiveComponent(LoginUser::class);

        $crawler = $testComponent->render();

        // Check that the form has the correct structure
        $form = $crawler->crawler()->filter('form');
        $this->assertGreaterThan(0, $form->count());
        
        // Debug: check what attributes the form actually has
        $formHtml = $form->outerHtml();
        $this->assertStringContainsString('space-y-5', $formHtml, 'Form should contain space-y-5 class');

        // Check that email field has correct structure
        $emailContainer = $crawler->crawler()->filter('div:contains("Adres Email")')
            ->closest('div');
        $this->assertGreaterThan(0, $emailContainer->count());
        
        // Debug: check what attributes the email container actually has
        $emailContainerHtml = $emailContainer->outerHtml();
        $this->assertStringContainsString('space-y-5', $emailContainerHtml, 'Email container should contain space-y-5 class');

        // Toggle to password mode and check password field structure
        $testComponent->call('togglePassword');
        $crawler = $testComponent->render();

        $passwordContainer = $crawler->crawler()->filter('div:contains("Hasło")')
            ->closest('div');
        $this->assertGreaterThan(0, $passwordContainer->count());
        
        // Debug: check what attributes of password container actually has
        $passwordContainerHtml = $passwordContainer->outerHtml();
        $this->assertStringContainsString('mt-5', $passwordContainerHtml, 'Password container should contain mt-5 class');
    }
}
