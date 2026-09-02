<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Messenger;

use App\Infrastructure\Messenger\SwooleTaskWorkerContext;
use App\Infrastructure\Swoole\SwooleServerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SwooleBundle\SwooleBundle\Server\Exception\UninitializedException;

#[Group('unit')]
final class SwooleTaskWorkerContextTest extends TestCase
{
    public function testReturnsFalseWithoutServerProvider(): void
    {
        self::assertFalse(new SwooleTaskWorkerContext(null)->isInTaskWorker());
    }

    public function testReturnsFalseWhenServerNotInitialised(): void
    {
        $provider = $this->createMock(SwooleServerProviderInterface::class);
        $provider->method('getServer')
            ->willThrowException(UninitializedException::make());

        self::assertFalse(new SwooleTaskWorkerContext($provider)->isInTaskWorker());
    }
}
