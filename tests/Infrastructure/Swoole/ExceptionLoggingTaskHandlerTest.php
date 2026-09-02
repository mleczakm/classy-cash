<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole;

use Swoole\Server\Task;
use App\Infrastructure\Swoole\ExceptionLoggingTaskHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

#[Group('unit')]
final class ExceptionLoggingTaskHandlerTest extends TestCase
{
    public function testDelegatesToInnerHandler(): void
    {
        $server = $this->createMock(Server::class);
        $task = new Task();

        $inner = $this->createMock(TaskHandler::class);
        $inner->expects($this->once())
            ->method('handle')
            ->with($server, $task);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method('critical');

        new ExceptionLoggingTaskHandler($logger, $inner)
            ->handle($server, $task);
    }

    public function testSwallowsAndLogsInnerException(): void
    {
        $inner = $this->createMock(TaskHandler::class);
        $inner->method('handle')
            ->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('critical')
            ->with(
                $this->stringContains('boom'),
                $this->callback(static fn(array $context): bool => $context['exception'] instanceof \RuntimeException),
            );

        // Must not throw - an exception out of a Swoole task callback crashes the worker process.
        new ExceptionLoggingTaskHandler($logger, $inner)
            ->handle($this->createMock(Server::class), new Task());
    }
}
