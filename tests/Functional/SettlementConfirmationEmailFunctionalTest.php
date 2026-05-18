<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Application\Command\Notification\SendSettlementConfirmationEmail;
use App\Application\CommandHandler\Notification\SendSettlementConfirmationEmailHandler;
use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\ClassCouncil\Student;
use App\Entity\ClassCouncil\StudentPayment;
use App\Entity\Payment;
use App\Tests\Assembler\PaymentAssembler;
use App\Tests\Assembler\UserAssembler;
use Brick\Money\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Twig\Environment as TwigEnvironment;

#[Group('functional')]
class SettlementConfirmationEmailFunctionalTest extends WebTestCase
{
    use FunctionalTestSettingsTrait;
    use MailerAssertionsTrait;

    public function testTreasurerSettlementNotificationEmailIsSentWithNewStyling(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $em = $container->get(EntityManagerInterface::class);
        $this->setupDefaultSettings($em);

        $parent = UserAssembler::new()
            ->withEmail('parent@example.com')
            ->withName('Anna Kowalska')
            ->withCreatedAt(new \DateTimeImmutable())
            ->assemble();
        $em->persist($parent);

        $treasurer = UserAssembler::new()
            ->withEmail('treasurer@example.com')
            ->withName('Skarbnik Test')
            ->withCreatedAt(new \DateTimeImmutable())
            ->assemble();
        $em->persist($treasurer);

        $amount = Money::of(50, 'PLN');
        $payment = PaymentAssembler::new()
            ->withUser($parent)
            ->withAmount($amount)
            ->withStatus(Payment::STATUS_PAID)
            ->withCreatedAt(new \DateTimeImmutable())
            ->assemble();
        $em->persist($payment);

        $classRoom = new ClassRoom('4B');
        $em->persist($classRoom);
        $student = new Student($classRoom, 'Jan', 'Kowalski');
        $em->persist($student);
        $studentPayment = new StudentPayment($student, 'Składka ogólna', $amount);
        $studentPayment->setStatus(StudentPayment::STATUS_PAID);
        $studentPayment->setPaidAt(new \DateTimeImmutable());
        $studentPayment->setPayment($payment);
        $em->persist($studentPayment);

        $em->persist(new ClassMembership($treasurer, $classRoom, ClassRole::TREASURER));
        $em->flush();

        $handler = $container->get(SendSettlementConfirmationEmailHandler::class);
        $handler(new SendSettlementConfirmationEmail(paymentId: (string) $payment->getId()));

        $this->assertQueuedEmailCount(1);

        $event = $this->getMailerEvent(0);
        self::assertNotNull($event);
        /** @var Email $email */
        $email = $event->getMessage();
        $this->assertEmailHeaderSame($email, 'to', 'treasurer@example.com');
        $this->assertEmailHeaderSame($email, 'subject', 'Nowa wpłata w klasie - podsumowanie zaległości');

        $body = (string) ($email->getHtmlBody() ?? $email->getTextBody());
        self::assertNotSame('', $body);
        $this->assertEmailUsesClassyCashStyling($body);
        self::assertStringContainsString('Nowa wpłata w kasie!', $body);
        self::assertStringContainsString('Anna Kowalska', $body);
        self::assertStringContainsString('parent@example.com', $body);
        self::assertStringContainsString('Jan Kowalski', $body);
        self::assertStringContainsString('Składka ogólna', $body);
    }

    public function testSettlementConfirmationTemplateRendersWithNewStyling(): void
    {
        self::bootKernel();
        $twig = static::getContainer()->get(TwigEnvironment::class);

        $html = $twig->render('email/class_council/settlement_confirmation.html.twig', [
            'items' => [
                [
                    'student' => 'Jan Kowalski',
                    'label' => 'Wycieczka do Zoo',
                    'amount' => Money::of(35, 'PLN'),
                    'progress' => [
                        'paid' => 1,
                        'total' => 1,
                    ],
                ],
            ],
            'previousPayments' => [],
            'sumPrevious' => Money::of(0, 'PLN'),
            'sumIncludingCurrent' => Money::of(35, 'PLN'),
        ]);

        $this->assertEmailUsesClassyCashStyling($html);
        self::assertStringContainsString('Potwierdzenie płatności', $html);
        self::assertStringContainsString('Jan Kowalski', $html);
        self::assertStringContainsString('Wycieczka do Zoo', $html);
    }

    private function assertEmailUsesClassyCashStyling(string $body): void
    {
        self::assertStringContainsString('#1a2a52', $body);
        self::assertStringContainsString('#e8b441', $body);
        self::assertStringContainsString('#f7f6f0', $body);
        self::assertStringContainsString('System zarządzania finansami klasowymi', $body);
    }
}
