<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassRole;
use PHPUnit\Framework\Attributes\Group;

#[Group('smoke')]
class TreasurerSmokeTest extends FunctionalTestCase
{
    public function testDashboardPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/dashboard');

        $this->assertResponseIsSuccessful();
    }

    public function testPaymentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseIsSuccessful();
    }

    public function testContributionsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/contributions');

        $this->assertResponseIsSuccessful();
    }

    public function testStudentsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/students');

        $this->assertResponseIsSuccessful();
    }

    public function testManualTransactionsPageLoads(): void
    {
        $this->createTreasurerUser();
        $this->client->request('GET', '/treasurer/manual-transactions');

        $this->assertResponseIsSuccessful();
    }

    public function testDashboardPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/dashboard');

        $this->assertResponseRedirects('/login');
    }

    public function testPaymentsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseRedirects('/login');
    }

    public function testContributionsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/contributions');

        $this->assertResponseRedirects('/login');
    }

    public function testStudentsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/students');

        $this->assertResponseRedirects('/login');
    }

    public function testManualTransactionsPageRedirectsNonAuthenticatedUsers(): void
    {
        $this->client->request('GET', '/treasurer/manual-transactions');

        $this->assertResponseRedirects('/login');
    }

    private function createTreasurerUser(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer@example.com', 'password');
        $user->setRoles(['ROLE_TREASURER', 'ROLE_USER']);
        $this->getEntityManager()
            ->flush();

        // Assign treasurer role
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);

        // Log in the user
        $this->client->loginUser($user);
    }
}
