<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Server\Task;
use Psr\Log\LoggerInterface;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

/**
 * A throw out of a Swoole `task` callback has no caller to catch it: PHP's uncaught-exception
 * path under Swoole's coroutine hooks becomes a process-level crash, not a single failed
 * task. One bad task then takes the whole task worker (and everything queued behind it) down.
 *
 * Swallow and log instead - the worker keeps serving, and `worker_max_request` still recycles
 * it on a normal schedule.
 */
final readonly class ExceptionLoggingTaskHandler implements TaskHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private TaskHandler $innerHandler,
    ) {}

    public function handle(Server $server, Task $task): void
    {
        try {
            $this->innerHandler->handle($server, $task);
        } catch (\Throwable $e) {
            $this->logger->critical(sprintf('Task worker exception: %s', $e->getMessage()), [
                'exception' => $e,
            ]);
        }
    }
}
