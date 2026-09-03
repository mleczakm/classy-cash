<?php

declare(strict_types=1);

namespace App\Infrastructure\ImapEngine;

use DirectoryTree\ImapEngine\MailboxInterface;
use Symfony\Contracts\Service\ResetInterface;

// Not readonly: the swoole-bundle coroutine proxifier strips the final flag from stateful
// services and cannot do so on a readonly class.
final class ConnectionCloserOnResetSubscriber implements ResetInterface
{
    public function __construct(
        private readonly MailboxInterface $mailbox
    ) {}

    public function reset(): void
    {
        $this->mailbox->disconnect();
    }
}
