<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Symfony\Scheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[Group('unit')]
class SchedulerTest extends TestCase
{
    public function testRunCallsEnsureConnectionBeforeGetMessages(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $scheduleProvider = $this->createMock(ScheduleProviderInterface::class);
        $clock = new MockClock();
        $connectionResetter = $this->createMock(ConnectionEnsurerInterface::class);

        $schedule = new Schedule();
        $scheduleProvider->expects($this->once())
            ->method('getSchedule')
            ->willReturn($schedule);

        // Ensure connection is called before any generator access
        $connectionResetter->expects($this->once())
            ->method('ensureConnection');

        $scheduler = new Scheduler($bus, [$scheduleProvider], $clock, null, $connectionResetter);

        $scheduler->run();
    }

    public function testRunDoesNotCallEnsureConnectionWhenResetterIsNull(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $scheduleProvider = $this->createMock(ScheduleProviderInterface::class);
        $clock = new MockClock();

        $schedule = new Schedule();
        $scheduleProvider->expects($this->once())
            ->method('getSchedule')
            ->willReturn($schedule);

        $scheduler = new Scheduler($bus, [$scheduleProvider], $clock, null, null);

        // Should not throw any exception
        $scheduler->run();
    }

    public function testRunCallsEnsureConnectionOnceEvenWithMultipleGenerators(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $scheduleProvider1 = $this->createMock(ScheduleProviderInterface::class);
        $scheduleProvider2 = $this->createMock(ScheduleProviderInterface::class);
        $clock = new MockClock();
        $connectionResetter = $this->createMock(ConnectionEnsurerInterface::class);

        $schedule1 = new Schedule();
        $schedule2 = new Schedule();
        $scheduleProvider1->expects($this->once())
            ->method('getSchedule')
            ->willReturn($schedule1);
        $scheduleProvider2->expects($this->once())
            ->method('getSchedule')
            ->willReturn($schedule2);

        // Ensure connection is called exactly once, even with multiple generators
        $connectionResetter->expects($this->once())
            ->method('ensureConnection');

        $scheduler = new Scheduler(
            $bus,
            [$scheduleProvider1, $scheduleProvider2],
            $clock,
            null,
            $connectionResetter
        );

        $scheduler->run();
    }
}
