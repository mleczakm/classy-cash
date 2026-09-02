<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole;

use Swoole\Server\Task;
use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use Swoole\Server;
use SwooleBundle\SwooleBundle\Server\TaskHandler\TaskHandler;

/**
 * A task worker holds its Doctrine connection between tasks. If it has been idle long enough
 * for Postgres to drop the socket, the first query of the next task fails. Prove the
 * connection before handing off to the real handler.
 */
final readonly class TaskWorkerConnectionEnsuringHandler implements TaskHandler
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
        private TaskHandler $innerHandler,
    ) {}

    public function handle(Server $server, Task $task): void
    {
        $this->connectionEnsurer->ensureConnection();
        $this->innerHandler->handle($server, $task);
    }
}
