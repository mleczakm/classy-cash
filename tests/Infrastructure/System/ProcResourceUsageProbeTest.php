<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\System;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use SwooleBundle\Observability\System\ProcResourceUsageProbe;
use Symfony\Component\Filesystem\Filesystem;

#[Group('unit')]
final class ProcResourceUsageProbeTest extends TestCase
{
    private string $procPath;

    private Filesystem $filesystem;

    #[\Override]
    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->procPath = sys_get_temp_dir() . '/classycash-proc-' . bin2hex(random_bytes(6));
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->filesystem->remove($this->procPath);
    }

    public function testAggregatesProcessMemoryFileDescriptorsAndSockets(): void
    {
        $this->writeProcess(1, 100_000, 2);
        $this->writeProcess(2, 205_000, 3);
        $this->writeFile('net/sockstat', "TCP: inuse 41 orphan 2 tw 14 alloc 3191 mem 0\n");
        $this->writeFile('self/limits', "Max open files            1024                 524288               files\n");

        $snapshot = new ProcResourceUsageProbe($this->procPath)
            ->capture();

        static::assertSame(2, $snapshot->processCount);
        static::assertSame(305_000, $snapshot->totalRssKib);
        static::assertSame(205_000, $snapshot->maxProcessRssKib);
        static::assertSame(5, $snapshot->totalOpenFds);
        static::assertSame(3, $snapshot->maxProcessOpenFds);
        static::assertSame(1024, $snapshot->fdSoftLimit);
        static::assertSame(41, $snapshot->tcpInUse);
        static::assertSame(2, $snapshot->tcpOrphan);
        static::assertSame(14, $snapshot->tcpTimeWait);
        static::assertSame(3191, $snapshot->tcpAllocated);
        static::assertSame(305_000 * 1024, $snapshot->totalRssBytes());
        static::assertSame(205_000 * 1024, $snapshot->maxProcessRssBytes());
    }

    public function testMissingAndVanishedProcFilesDegradeToZero(): void
    {
        $this->writeProcess(1, 50_000, 1);
        $this->filesystem->mkdir($this->procPath . '/2');

        $snapshot = new ProcResourceUsageProbe($this->procPath)
            ->capture();

        static::assertSame(1, $snapshot->processCount);
        static::assertSame(0, $snapshot->fdSoftLimit);
        static::assertSame(0, $snapshot->tcpAllocated);
    }

    private function writeProcess(int $pid, int $rssKib, int $fdCount): void
    {
        $this->writeFile(sprintf('%d/status', $pid), sprintf("VmRSS:\t%d kB\n", $rssKib));

        for ($fd = 0; $fd < $fdCount; $fd++) {
            $this->writeFile(sprintf('%d/fd/%d', $pid, $fd), '');
        }
    }

    private function writeFile(string $relativePath, string $contents): void
    {
        $this->filesystem->dumpFile($this->procPath . '/' . $relativePath, $contents);
    }
}
