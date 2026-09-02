<?php

declare(strict_types=1);

namespace App\Infrastructure\Swoole\Configurator;

use App\Infrastructure\Symfony\Scheduler;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server;
use Swoole\Timer;
use SwooleBundle\SwooleBundle\Server\Configurator\Configurator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Drives the Symfony Scheduler from a 1s Swoole timer on the master process.
 *
 * A `Timer::tick` callback has no caller, so an exception escaping it is handled by PHP's
 * uncaught-exception path, which under Swoole crashes the whole server (seen in production:
 * stop the database and the container restart-loops purely from a failing tick). Every tick
 * is therefore wrapped:
 *
 *  - a reentrancy guard so a slow `run()` never lets ticks pile up;
 *  - a `Timer::after` watchdog that force-clears the guard if a tick hangs past its deadline,
 *    so scheduling resumes on the next tick instead of wedging forever;
 *  - a catch-all that logs and lets the next tick retry.
 */
final class WithScheduler implements Configurator
{
    private int $tickId;

    private bool $running = false;

    /**
     * Bumped when a tick starts, and again by the watchdog if it force-releases that tick.
     * The tick's own cleanup only runs while this still matches the value it took, so a
     * `run()` that finally returns after the watchdog fired does not clobber a newer tick.
     */
    private int $tickGeneration = 0;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly ResetInterface $reset,
        private readonly LoggerInterface $logger,
        private readonly int $tickTimeoutSeconds = 15,
    ) {}

    public function __destruct()
    {
        if (isset($this->tickId)) {
            Timer::clear($this->tickId);
        }
    }

    public function configure(Server $server): void
    {
        $this->tickId = Timer::tick(1000, $this->tick(...));

        $server->on('shutdown', function (): void {
            if (isset($this->tickId)) {
                Timer::clear($this->tickId);
            }
        });
    }

    public function tick(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $generation = ++$this->tickGeneration;

        $watchdogId = Timer::after($this->tickTimeoutSeconds * 1000, function () use ($generation): void {
            if (! $this->running || $this->tickGeneration !== $generation) {
                return;
            }

            ++$this->tickGeneration;
            $this->running = false;

            $this->logger->error('Scheduler tick exceeded its timeout; released the guard so scheduling can resume', [
                'timeout_seconds' => $this->tickTimeoutSeconds,
            ]);
        });

        try {
            $this->scheduler->run();
            $this->reset->reset();
        } catch (\Throwable $e) {
            $this->logger->error('Scheduler tick failed', [
                'exception' => $e,
            ]);
        } finally {
            Timer::clear($watchdogId);

            if ($this->tickGeneration === $generation) {
                $this->running = false;
            }
        }
    }
}
