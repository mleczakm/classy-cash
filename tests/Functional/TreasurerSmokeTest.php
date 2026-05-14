<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassRole;
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
        $this->assertSelectorTextContains('main p', 'Podsumowanie finansów klasy');
        $this->assertSelectorExists('div[data-live-name-value="treasurer:dashboard:total_cash"]');
        $this->assertSelectorExists('div[data-live-name-value="treasurer:dashboard:monthly_collected"]');
        $this->assertSelectorExists('div[data-live-name-value="treasurer:dashboard:outstanding_payments"]');
    }

    public function testPaymentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Lista Operacji Bankowych');
        $this->assertSelectorTextContains(
            'main p',
            'Przegląd zaksięgowanych przelewów i obsługa błędnych tytułów'
        );
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
            'main p',
            'Twórz nowe cele i edytuj obowiązek wpłat dla poszczególnych uczniów'
        );
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('form', 'Tytuł składki');
        $this->assertSelectorTextContains('form', 'Kwota (PLN)');
    }

    public function testStudentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/students');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Uczniowie i Rodzice');
        $this->assertSelectorTextContains(
            'main p',
            'Zarządzaj danymi uczniów i łącz wiele kont rodziców z jednym dzieckiem'
        );
        $this->assertSelectorTextContains('h3', 'Lista Uczniów');
    }

    public function testManualTransactionsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/manual-transactions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Kasa Ręczna');
        $this->assertSelectorTextContains(
            'main p',
            'Zaksięguj gotówkę od rodzica lub wpisz wydatek z kasy klasowej'
        );
        $this->assertSelectorExists('form');
        $this->assertSelectorTextContains('h3', 'Wpłata (Gotówka)');
        // Use a more specific selector or check all h3
        $this->assertSelectorTextContains('div.grid > div:nth-child(2) h3', 'Koszty / Wypłata');
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
        // Create class room
        $classRoom = $this->createClassRoom('4B');

        // Create a basic user for testing
        $user = $this->createUser('treasurer@example.com', 'password');

        // Assign treasurer role
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);

        // Log in the user
        $this->client->loginUser($user);
    }
}
