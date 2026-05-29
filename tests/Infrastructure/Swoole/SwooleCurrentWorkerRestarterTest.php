<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole;

use App\Infrastructure\Swoole\SwooleCurrentWorkerRestarter;
use App\Infrastructure\Swoole\SwooleServerProviderInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Server;
use SwooleBundle\SwooleBundle\Server\Exception\UninitializedException;

#[Group('unit')]
final class SwooleCurrentWorkerRestarterTest extends TestCase
{
    public function testRestartsCurrentWorker(): void
    {
        $server = $this->createMock(Server::class);
        $server->worker_id = 2;
        $server->expects($this->once())
            ->method('stop')
            ->with(2);

        $serverProvider = $this->createMock(SwooleServerProviderInterface::class);
        $serverProvider->expects($this->once())
            ->method('getServer')
            ->willReturn($server);

        new SwooleCurrentWorkerRestarter($serverProvider)
            ->restart();
    }

    public function testDoesNotFailWhenSwooleServerIsNotInitialized(): void
    {
        $serverProvider = $this->createMock(SwooleServerProviderInterface::class);
        $serverProvider->expects($this->once())
            ->method('getServer')
            ->willThrowException(UninitializedException::make());

        new SwooleCurrentWorkerRestarter($serverProvider)
            ->restart();

        $this->addToAssertionCount(1);
    }
}
