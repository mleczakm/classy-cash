<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImapEngine;

use DirectoryTree\ImapEngine\MailboxInterface;
use App\Infrastructure\ImapEngine\AliorNotificationMailProvider;
use App\Infrastructure\Swoole\CurrentWorkerRestarterInterface;
use DirectoryTree\ImapEngine\MessageQueryInterface;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Group('unit')]
class AliorNotificationMailProviderTest extends TestCase
{
    public function testRestartsWorkerOnThrowable(): void
    {
        $testMailbox = new FakeMailbox(folders: [new ThrowingFolder('inbox')]);

        $workerRestarter = $this->createMock(CurrentWorkerRestarterInterface::class);
        $workerRestarter->expects($this->once())
            ->method('restart');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error');

        $provider = new AliorNotificationMailProvider(
            $testMailbox,
            $workerRestarter,
            $logger,
            isFetchingEmailsEnabled: true,
        );

        foreach ($provider() as $message) {
            $this->fail('No messages should be yielded when an exception occurs.');
        }
    }

    public function testReturnsEmptyIterableWhenFetchingEmailsIsDisabled(): void
    {
        $mailbox = $this->createMock(MailboxInterface::class);
        $mailbox->expects($this->never())
            ->method('reconnect');

        $provider = new AliorNotificationMailProvider(
            $mailbox,
            $this->createMock(CurrentWorkerRestarterInterface::class),
            $this->createMock(LoggerInterface::class),
            isFetchingEmailsEnabled: false,
        );

        self::assertSame([], iterator_to_array($provider()));
    }
}

class ThrowingFolder extends FakeFolder
{
    #[\Override]
    public function messages(): MessageQueryInterface
    {
        throw new \ErrorException('Simulated exception');
    }
}
