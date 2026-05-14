<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\RedirectResponse;
use PHPUnit\Framework\Attributes\Group;

#[Group('smoke')]
class TreasurerSmokeTest extends FunctionalTestCase
{
    public function testDashboardPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Panel Główny');
        $this->assertSelectorTextContains('p', 'Podsumowanie finansów klasy');
        $this->assertSelectorExists('treasurer:dashboard:total_cash');
        $this->assertSelectorExists('treasurer:dashboard:monthly_collected');
        $this->assertSelectorExists('treasurer:dashboard:outstanding_payments');
    }

    public function testPaymentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Lista Operacji Bankowych');
        $this->assertSelectorTextContains('p', 'Przegląd zaksięgowanych przelewów i obsługa błędnych tytułów');
        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('th', 'Tytuł przelewu / Nadawca');
    }

    public function testContributionsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/contributions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Zarządzanie Składkami');
        $this->assertSelectorTextContains(
            'p',
            'Twórz nowe cele i edytuj obowiązek wpłat dla poszczególnych uczniów'
        );
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('label', 'Tytuł składki');
        $this->assertSelectorTextContains('label', 'Kwota (PLN)');
    }

    public function testStudentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/students');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Uczniowie i Rodzice');
        $this->assertSelectorTextContains(
            'p',
            'Zarządzaj danymi uczniów i łącz wiele kont rodziców z jednym dzieckiem'
        );
        $this->assertSelectorExists('table');
        $this->assertSelectorTextContains('th', 'Lista Uczniów');
    }

    public function testManualTransactionsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/manual-transactions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Kasa Ręczna');
        $this->assertSelectorTextContains('p', 'Zaksięguj gotówkę od rodzica lub wpisz wydatek z kasy klasowej');
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('h3', 'Wpłata (Gotówka)');
        $this->assertSelectorTextContains('h3', 'Koszty / Wypłata');
    }

    public function testDashboardPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/dashboard');

        $response = $this->client->getResponse();
        /** @var RedirectResponse $response */
        // Should redirect to login for non-authenticated users
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testPaymentsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/payments');

        $response = $this->client->getResponse();
        /** @var RedirectResponse $response */
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testContributionsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/contributions');

        $response = $this->client->getResponse();
        /** @var RedirectResponse $response */
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testStudentsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/students');

        $response = $this->client->getResponse();
        /** @var RedirectResponse $response */
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    public function testManualTransactionsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/manual-transactions');

        $response = $this->client->getResponse();
        /** @var RedirectResponse $response */
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/login', $response->getTargetUrl());
    }

    private function createTreasurerUser(): void
    {
        // Create a basic user for testing
        $user = $this->createUser('treasurer@example.com', 'password');

        // Log in the user
        $this->client->loginUser($user);
    }
}
