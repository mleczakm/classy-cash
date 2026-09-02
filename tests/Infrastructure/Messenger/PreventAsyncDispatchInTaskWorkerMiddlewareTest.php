<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Infrastructure\Messenger\PreventAsyncDispatchInTaskWorkerMiddleware;
use App\Infrastructure\Messenger\TaskWorkerContextInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Test\Middleware\MiddlewareTestCase;

#[Group('unit')]
final class PreventAsyncDispatchInTaskWorkerMiddlewareTest extends MiddlewareTestCase
{
    public function testForcesSyncTransportWhenInsideTaskWorker(): void
    {
        $middleware = new PreventAsyncDispatchInTaskWorkerMiddleware(self::taskWorkerContext(true));

        $envelope = $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        self::assertSame(['sync'], $envelope->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testDoesNotTouchTransportOutsideTaskWorker(): void
    {
        $middleware = new PreventAsyncDispatchInTaskWorkerMiddleware(self::taskWorkerContext(false));

        $envelope = $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        self::assertNull($envelope->last(TransportNamesStamp::class));
    }

    public function testDoesNotOverrideExplicitTransport(): void
    {
        $middleware = new PreventAsyncDispatchInTaskWorkerMiddleware(self::taskWorkerContext(true));

        $envelope = $middleware->handle(
            new Envelope(new \stdClass(), [new TransportNamesStamp('async')]),
            $this->getStackMock(),
        );

        self::assertSame(['async'], $envelope->last(TransportNamesStamp::class)?->getTransportNames());
    }

    public function testDisabledMiddlewareIsPassThrough(): void
    {
        $middleware = new PreventAsyncDispatchInTaskWorkerMiddleware(self::taskWorkerContext(true), enabled: false);

        $envelope = $middleware->handle(new Envelope(new \stdClass()), $this->getStackMock());

        self::assertNull($envelope->last(TransportNamesStamp::class));
    }

    public function testReroutesNestedDispatchWhileHandlingAsyncMessage(): void
    {
        $middleware = new PreventAsyncDispatchInTaskWorkerMiddleware(self::taskWorkerContext(false));

        $spy = new class ($middleware) implements MiddlewareInterface {
            public ?Envelope $nested = null;

            public function __construct(
                private readonly PreventAsyncDispatchInTaskWorkerMiddleware $middleware,
            ) {}

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                // A handler, mid async message, dispatches a fresh message.
                $this->nested = $this->middleware->handle(new Envelope(new \stdClass()), new StackMiddleware());

                return $stack->next()
                    ->handle($envelope, $stack);
            }
        };

        new MessageBus([$middleware, $spy])->dispatch(new \stdClass(), [new ReceivedStamp('async')]);

        self::assertSame(['sync'], $spy->nested?->last(TransportNamesStamp::class)?->getTransportNames());
    }

    private static function taskWorkerContext(bool $inTaskWorker): TaskWorkerContextInterface
    {
        return new readonly class ($inTaskWorker) implements TaskWorkerContextInterface {
            public function __construct(
                private bool $inTaskWorker,
            ) {}

            public function isInTaskWorker(): bool
            {
                return $this->inTaskWorker;
            }
        };
    }
}
