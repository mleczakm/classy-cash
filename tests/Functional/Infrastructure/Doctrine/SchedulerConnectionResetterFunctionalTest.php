<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\SchedulerConnectionResetter;
use App\Infrastructure\Symfony\Scheduler;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Scheduler\Event\PreRunEvent;

#[Group('functional')]
final class SchedulerConnectionResetterFunctionalTest extends KernelTestCase
{
    private Connection $connection;

    private SchedulerConnectionResetter $resetter;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(Connection::class);
        $this->resetter = self::getContainer()->get(SchedulerConnectionResetter::class);
    }

    public function testOnPreRunEnsuresConnectionIsActive(): void
    {
        // Ensure connection is initially connected
        $this->connection->connect();
        $this->assertTrue($this->connection->isConnected());

        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter
        $this->resetter->onPreRun($event);

        // Verify connection is still connected and can execute queries
        $this->assertTrue($this->connection->isConnected());
        $result = $this->connection->executeQuery('SELECT 1');
        $this->assertEquals(1, $result->fetchOne());
    }

    public function testOnPreRunReconnectsDisconnectedConnection(): void
    {
        // Close the connection to simulate a lost connection
        $this->connection->close();
        $this->assertFalse($this->connection->isConnected());

        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter
        $this->resetter->onPreRun($event);

        // Verify connection is reconnected and can execute queries
        $this->assertTrue($this->connection->isConnected());
        $result = $this->connection->executeQuery('SELECT 1');
        $this->assertEquals(1, $result->fetchOne());
    }

    public function testOnPreRunHandlesMultipleSequentialCalls(): void
    {
        // Create a mock PreRunEvent
        $event = $this->createMock(PreRunEvent::class);

        // Call the resetter multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->resetter->onPreRun($event);
            $this->assertTrue($this->connection->isConnected());
            $result = $this->connection->executeQuery('SELECT 1');
            $this->assertEquals(1, $result->fetchOne());
        }
    }

    public function testSchedulerRunAfterConnectionReset(): void
    {
        // Simulate the Swoole tick lifecycle: close connection, then run scheduler
        $this->connection->close();
        $this->assertFalse($this->connection->isConnected());

        // Get the scheduler from container
        $scheduler = self::getContainer()->get(Scheduler::class);

        // Run the scheduler - this should call ensureConnection() internally
        // before accessing the checkpoint cache
        $scheduler->run();

        // Verify connection is reconnected and can execute queries
        $this->assertTrue($this->connection->isConnected());
        $result = $this->connection->executeQuery('SELECT 1');
        $this->assertEquals(1, $result->fetchOne());
    }

    public function testSchedulerRunMultipleTicksWithConnectionReset(): void
    {
        // Simulate multiple Swoole tick cycles
        for ($i = 0; $i < 3; $i++) {
            // Close connection (simulating services_resetter->reset())
            $this->connection->close();
            $this->assertFalse($this->connection->isConnected());

            // Get the scheduler from container
            $scheduler = self::getContainer()->get(Scheduler::class);

            // Run the scheduler - should reconnect before checkpoint cache access
            $scheduler->run();

            // Verify connection is reconnected
            $this->assertTrue($this->connection->isConnected());
            $result = $this->connection->executeQuery('SELECT 1');
            $this->assertEquals(1, $result->fetchOne());
        }
    }
}
