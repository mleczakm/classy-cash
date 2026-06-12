<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Settings\Settings;
use App\Entity\ClassCouncil\ClassRole;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class TreasurerPaymentsTest extends FunctionalTestCase
{
    public function testPaymentsPageDisplaysLastSuccessfulImportDateWhenSet(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer@example.com', 'password');
        $user->setRoles(['ROLE_TREASURER', 'ROLE_USER']);
        $this->getEntityManager()
            ->flush();
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);

        // Set up the last successful import date
        /** @var Settings $settings */
        $settings = $this->getService(Settings::class);
        $testDate = new \DateTimeImmutable('2024-01-15 10:30:00');
        $settings->set('last_successful_transfer_import_date', $testDate->format(\DateTimeInterface::ATOM));

        // Log in and request the payments page
        $this->client->loginUser($user);
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()
            ->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('Ostatni udany import: 15.01.2024 10:30', $content);
    }

    public function testPaymentsPageDoesNotDisplayLastSuccessfulImportDateWhenNotSet(): void
    {
        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer@example.com', 'password');
        $user->setRoles(['ROLE_TREASURER', 'ROLE_USER']);
        $this->getEntityManager()
            ->flush();
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);

        // Log in and request the payments page without setting the last import date
        $this->client->loginUser($user);
        $this->client->request('GET', '/treasurer/payments');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()
            ->getContent();
        $this->assertIsString($content);
        $this->assertStringNotContainsString('Ostatni udany import:', $content);
    }
}
