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
}
