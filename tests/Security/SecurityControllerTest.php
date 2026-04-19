<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Tests\Functional\FunctionalTestSettingsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Zenstruck\Mailer\Test\InteractsWithMailer;

#[Group('functional')]
class SecurityControllerTest extends WebTestCase
{
    use InteractsWithMailer;
    use FunctionalTestSettingsTrait;

    private const string LOGIN_URL = '/login';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->setupDefaultSettings($em);
    }

    public function testLoginPageLoadsSuccessfully(): void
    {
        $this->client->request(Request::METHOD_GET, self::LOGIN_URL);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="form"]');
        $this->assertSelectorExists('input[type="email"]');
        $this->assertSelectorExists('button[type="submit"]');
    }
}
