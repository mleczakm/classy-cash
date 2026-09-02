<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole;

use Swoole\Server\Task;
use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Swoole\TaskWorkerConnectionEnsuringHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

#[Group('unit')]
final class TaskWorkerConnectionEnsuringHandlerTest extends TestCase
{
    public function testEnsuresConnectionBeforeDelegating(): void
    {
        $server = $this->createMock(Server::class);
        $task = new Task();

        $calls = [];

        $ensurer = $this->createMock(ConnectionEnsurerInterface::class);
        $ensurer->method('ensureConnection')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'ensure';
            });

        $inner = $this->createMock(TaskHandler::class);
        $inner->method('handle')
            ->willReturnCallback(static function () use (&$calls): void {
                $calls[] = 'handle';
            });

        new TaskWorkerConnectionEnsuringHandler($ensurer, $inner)
            ->handle($server, $task);

        self::assertSame(['ensure', 'handle'], $calls);
    }
}
