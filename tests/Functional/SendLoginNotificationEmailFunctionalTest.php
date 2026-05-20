<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Command\SendLoginNotification;
use App\Application\CommandHandler\SendLoginNotificationHandler;
use App\Tests\Assembler\UserAssembler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zenstruck\Messenger\Test\InteractsWithMessenger;
use Zenstruck\Mailer\Test\InteractsWithMailer;
use Zenstruck\Mailer\Test\TestEmail;

#[Group('functional')]
final class SendLoginNotificationEmailFunctionalTest extends WebTestCase
{
    use InteractsWithMessenger;
    use FunctionalTestSettingsTrait;
    use InteractsWithMailer;

    public function testLoginLinkEmailIsSentWithNewStyling(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $container->get(RequestStack::class)->push(Request::create('/login'));

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')
            ->getManager();
        $this->setupDefaultSettings($em);

        $user = UserAssembler::new()
            ->withEmail('parent@example.com')
            ->withName('Jan Kowalski')
            ->assemble();
        $user->setConfirmedAt(new \DateTimeImmutable());
        $em->persist($user);
        $em->flush();

        $handler = $container->get(SendLoginNotificationHandler::class);
        $handler(new SendLoginNotification('parent@example.com'));
        $this->transport('async')
            ->process();

        $this->assertEmailCount(1);
        /** @var TestEmail $email */
        $email = $this->mailer()
            ->sentEmails()
            ->first();
        $email->assertTo('parent@example.com');
        $email->assertSubject('Zaloguj się do swojego konta');

        $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
        self::assertStringContainsString('#1a2a52', $body);
        self::assertStringContainsString('#e8b441', $body);
        self::assertStringContainsString('System zarządzania finansami klasowymi', $body);
        self::assertStringContainsString('Jan Kowalski', $body);
        $email->assertContains('Zaloguj się');
    }

    public function testUnknownEmailNotificationUsesNewStyling(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $container = $client->getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine')
            ->getManager();
        $this->setupDefaultSettings($em);

        $handler = $container->get(SendLoginNotificationHandler::class);
        $handler(new SendLoginNotification('unknown@example.com'));
        $this->transport('async')
            ->process();

        $this->assertEmailCount(1);
        /** @var TestEmail $email */
        $email = $this->mailer()
            ->sentEmails()
            ->first();
        $email->assertTo('unknown@example.com');
        $email->assertSubject('Nie znaleziono konta zarejestrowanego na podany adres email!');

        $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
        self::assertStringContainsString('#1a2a52', $body);
        self::assertStringContainsString('System zarządzania finansami klasowymi', $body);
    }
}
