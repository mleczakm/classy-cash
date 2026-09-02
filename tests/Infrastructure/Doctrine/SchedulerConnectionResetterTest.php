<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Event\PreRunEvent;

#[Group('unit')]
class SchedulerConnectionResetterTest extends TestCase
{
    public function testEnsureConnectionPingsConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(false);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        new SchedulerConnectionResetter($connection)
            ->ensureConnection();
    }

    public function testOnPreRunEnsuresConnection(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(false);
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        new SchedulerConnectionResetter($connection)
            ->onPreRun($this->createMock(PreRunEvent::class));
    }

    public function testEnsureConnectionRollsBackDanglingTransaction(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(true);
        $connection->expects($this->once())
            ->method('rollBack');
        $connection->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($this->createMock(Result::class));

        new SchedulerConnectionResetter($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionClosesConnectionWhenRollbackFails(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(true);
        $connection->method('rollBack')
            ->willThrowException(new \RuntimeException('rollback failed'));
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->expects($this->once())
            ->method('close');
        $connection->method('executeQuery')
            ->willReturn($this->createMock(Result::class));

        new SchedulerConnectionResetter($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionRetriesOnConnectionFailure(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(false);
        $connection->method('isConnected')
            ->willReturn(true);

        $attempts = 0;
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function () use (&$attempts): Result {
                if (++$attempts < 3) {
                    throw new Exception('no connection to the server');
                }

                return $this->createMock(Result::class);
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        new SchedulerConnectionResetter($connection)
            ->ensureConnection();
    }

    public function testEnsureConnectionThrowsAfterMaxRetries(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('isTransactionActive')
            ->willReturn(false);
        $connection->method('isConnected')
            ->willReturn(true);
        $connection->expects($this->exactly(3))
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturnCallback(function (): Result {
                throw new Exception('no connection to the server');
            });
        $connection->expects($this->exactly(2))
            ->method('close');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no connection to the server');

        new SchedulerConnectionResetter($connection)
            ->ensureConnection();
    }
}
