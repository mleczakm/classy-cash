<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * A Swoole task worker cannot call `Server->task()` again, so a handler that runs inside an
 * async message (or directly inside a task worker) must not dispatch a further message onto
 * the async transport - the nested `task()` call throws and the message is silently lost.
 *
 * When that situation is detected, force the outgoing message onto the `sync` transport so
 * it runs inline instead.
 *
 * Inert in the test environment: PHPUnit drives the async transport synchronously and some
 * tests deliberately route nested commands to a fake transport to assert on them.
 */
final class PreventAsyncDispatchInTaskWorkerMiddleware implements MiddlewareInterface
{
    private int $asyncHandlerDepth = 0;

    public function __construct(
        private readonly TaskWorkerContextInterface $taskWorkerContext,
        private readonly bool $enabled = true,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (! $this->enabled) {
            return $stack->next()
                ->handle($envelope, $stack);
        }

        if (
            $envelope->last(ReceivedStamp::class) === null
            && $envelope->all(TransportNamesStamp::class) === []
            && ($this->asyncHandlerDepth > 0 || $this->taskWorkerContext->isInTaskWorker())
        ) {
            $envelope = $envelope->with(new TransportNamesStamp('sync'));
        }

        $receivedStamp = $envelope->last(ReceivedStamp::class);
        $isHandlingAsyncMessage = $receivedStamp !== null && $receivedStamp->getTransportName() === 'async';

        if ($isHandlingAsyncMessage) {
            $this->asyncHandlerDepth++;
        }

        try {
            return $stack->next()
                ->handle($envelope, $stack);
        } finally {
            if ($isHandlingAsyncMessage) {
                $this->asyncHandlerDepth--;
            }
        }
    }
}
