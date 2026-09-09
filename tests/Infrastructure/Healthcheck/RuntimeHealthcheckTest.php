<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Healthcheck;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SwooleBundle\Observability\HealthCheck\MemoryUsageHealthCheck;
use SwooleBundle\Observability\HealthCheck\ProcessCountHealthCheck;
use SwooleBundle\Observability\System\ProcResourceUsageProbe;

#[Group('unit')]
final class RuntimeHealthcheckTest extends TestCase
{
    public function testRuntimeChecksReportHealthyValues(): void
    {
        $probe = new ProcResourceUsageProbe();
        $process = new ProcessCountHealthCheck($probe, PHP_INT_MAX)
            ->check();
        $memory = new MemoryUsageHealthCheck($probe, 1_048_576)
            ->check();

        static::assertTrue($process->getResult());
        static::assertGreaterThanOrEqual(1, $process->getParams()['count']);
        static::assertTrue($memory->getResult());
        static::assertArrayHasKey('total_rss_mib', $memory->getParams());
        static::assertArrayHasKey('tcp_allocated', $memory->getParams());
    }

    public function testThresholdsFailAndZeroDisablesMemoryFailure(): void
    {
        $probe = new ProcResourceUsageProbe();

        static::assertFalse(new ProcessCountHealthCheck($probe, 0)->check()->getResult());
        static::assertFalse(new MemoryUsageHealthCheck($probe, 1)->check()->getResult());
        static::assertTrue(new MemoryUsageHealthCheck($probe, 0)->check()->getResult());
    }
}
