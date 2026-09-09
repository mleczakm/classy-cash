<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SampleResourceUsage;
use Psr\Log\LoggerInterface;
use Sentry\Unit;
use SwooleBundle\Observability\Metrics\MetricsRecorderInterface;
use SwooleBundle\Observability\System\ProcResourceUsageProbe;

final readonly class SampleResourceUsageHandler
{
    public function __construct(
        private ProcResourceUsageProbe $probe,
        private MetricsRecorderInterface $metrics,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SampleResourceUsage $_command): void
    {
        $snapshot = $this->probe->capture();

        $this->metrics->distribution('runtime.memory.rss_total', $snapshot->totalRssBytes(), [], Unit::byte());
        $this->metrics->distribution(
            'runtime.memory.rss_max_process',
            $snapshot->maxProcessRssBytes(),
            [],
            Unit::byte()
        );
        $this->metrics->distribution('runtime.process.count', $snapshot->processCount);
        $this->metrics->distribution('runtime.fd.open_total', $snapshot->totalOpenFds);
        $this->metrics->distribution('runtime.tcp.in_use', $snapshot->tcpInUse);
        $this->metrics->distribution('runtime.tcp.allocated', $snapshot->tcpAllocated);

        $this->logger->info('Resource usage sampled', $snapshot->toArray());
    }
}
