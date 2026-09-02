<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Swoole\Configurator;

use Symfony\Component\Scheduler\Schedule;
use App\Infrastructure\Swoole\Configurator\WithScheduler;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Swoole\Http\Server;
use Swoole\Timer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Group('unit')]
final class WithSchedulerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Leave no Swoole timers behind: a pending timer makes the extension's
        // shutdown handler call the deprecated Event::wait().
        Timer::clearAll();

        parent::tearDown();
    }

    public function testRegisterSwooleTick(): void
    {
        self::assertEmpty(iterator_to_array(Timer::list()));

        $withScheduler = new WithScheduler(
            $this->scheduler(),
            $this->createMock(ResetInterface::class),
            $this->createMock(LoggerInterface::class),
        );
        $withScheduler->configure($this->createMock(Server::class));

        self::assertNotEmpty(iterator_to_array(Timer::list()));

        $withScheduler->__destruct();

        self::assertEmpty(iterator_to_array(Timer::list()));
    }

    public function testTickRunsSchedulerAndResets(): void
    {
        $scheduleProvider = $this->createMock(ScheduleProviderInterface::class);
        $scheduleProvider->method('getSchedule')
            ->willReturn(new Schedule());
        $scheduler = new Scheduler($this->createMock(MessageBusInterface::class), [$scheduleProvider]);

        $reset = $this->createMock(ResetInterface::class);
        $reset->expects($this->once())
            ->method('reset');

        new WithScheduler($scheduler, $reset, $this->createMock(LoggerInterface::class))->tick();
    }

    public function testTickSwallowsAndLogsSchedulerFailure(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $scheduler->method('run')
            ->willThrowException(new \RuntimeException('db down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Scheduler tick failed', $this->arrayHasKey('exception'));

        // Must not throw - a Timer::tick callback that throws crashes the whole Swoole server.
        new WithScheduler($scheduler, $this->createMock(ResetInterface::class), $logger)->tick();
    }

    public function testTickIsReentrancyGuarded(): void
    {
        $scheduler = $this->createMock(Scheduler::class);
        $configurator = new WithScheduler(
            $scheduler,
            $this->createMock(ResetInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $scheduler->expects($this->once())
            ->method('run')
            ->willReturnCallback(function () use ($configurator): void {
                // A second tick firing while the first is still running is a no-op.
                $configurator->tick();
            });

        $configurator->tick();
    }

    private function scheduler(): Scheduler
    {
        return new Scheduler($this->createMock(MessageBusInterface::class), []);
    }
}
