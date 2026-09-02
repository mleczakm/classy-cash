<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Scheduler\Event\PreRunEvent;

/**
 * Canonical {@see ConnectionEnsurerInterface} implementation.
 *
 * Every request/message/tick boundary in the Swoole runtime can hand back a
 * connection that Postgres has since dropped (idle timeout, a failed coroutine
 * that left a transaction open, a `services_resetter` close between ticks).
 * Before any DB work, discard a dangling transaction and prove the socket with
 * `SELECT 1`, reconnecting up to three times.
 */
final readonly class SchedulerConnectionResetter implements ConnectionEnsurerInterface
{
    private const int MAX_RETRIES = 3;

    private const int RETRY_DELAY_MS = 1000;

    public function __construct(
        private Connection $connection,
    ) {}

    #[AsEventListener(event: PreRunEvent::class)]
    public function onPreRun(PreRunEvent $event): void
    {
        $this->ensureConnection();
    }

    public function ensureConnection(): void
    {
        $this->discardActiveTransaction();

        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                // Pings the connection (auto-connecting if needed) and proves the socket is usable.
                $this->connection->executeQuery('SELECT 1');

                return;
            } catch (Exception $e) {
                if ($attempt >= self::MAX_RETRIES - 1) {
                    throw $e;
                }

                if ($this->connection->isConnected()) {
                    $this->connection->close();
                }

                usleep(self::RETRY_DELAY_MS * 1000);
            }
        }
    }

    /**
     * A coroutine that dies mid-transaction leaves its pooled connection dirty; the next
     * consumer then trips "There is already an active transaction". Roll it back, and if
     * even that fails, drop the connection so a fresh one is opened on the next query.
     */
    private function discardActiveTransaction(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        try {
            $this->connection->rollBack();
        } catch (\Throwable) {
            if ($this->connection->isConnected()) {
                $this->connection->close();
            }
        }
    }
}
