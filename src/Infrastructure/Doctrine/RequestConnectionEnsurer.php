<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Under Swoole the PHP process is long-lived, so the first query of a request can land on a
 * connection Postgres closed while the worker was idle. Prove it is alive before the
 * controller (or anything earlier in the kernel) touches the database.
 */
final readonly class RequestConnectionEnsurer
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    #[AsEventListener(event: RequestEvent::class, priority: 10)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $this->connectionEnsurer->ensureConnection();
    }
}
