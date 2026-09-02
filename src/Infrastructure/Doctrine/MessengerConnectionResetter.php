<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

/**
 * A Swoole task worker consuming async messages keeps its Doctrine connection between
 * messages. If it has been idle long enough for Postgres to hang up, the first query of
 * the next message fails; ensure the connection before the handler runs.
 */
final readonly class MessengerConnectionResetter
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    #[AsEventListener(event: WorkerMessageReceivedEvent::class)]
    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->connectionEnsurer->ensureConnection();
    }
}
