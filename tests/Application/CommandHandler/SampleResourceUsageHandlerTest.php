<?php

declare(strict_types=1);

namespace App\Tests\Application\CommandHandler;

use App\Application\Command\SampleResourceUsage;
use App\Application\CommandHandler\SampleResourceUsageHandler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sentry\Unit;
use SwooleBundle\Observability\Metrics\MetricsRecorderInterface;
use SwooleBundle\Observability\System\ProcResourceUsageProbe;

#[Group('unit')]
final class SampleResourceUsageHandlerTest extends TestCase
{
    public function testEmitsRuntimeMetricsAndStructuredLog(): void
    {
        $names = [];
        $metrics = $this->createMock(MetricsRecorderInterface::class);
        $metrics->expects(static::exactly(6))->method('distribution')->willReturnCallback(
            static function (string $name, int|float $_value, array $_attributes = [], ?Unit $_unit = null) use (
                &$names
            ): void {
                $names[] = $name;
            },
        );
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('info')->with(
            'Resource usage sampled',
            static::callback(
                static fn(array $context): bool => isset($context['total_rss_mib'], $context['tcp_allocated'])
            ),
        );

        new SampleResourceUsageHandler(new ProcResourceUsageProbe(), $metrics, $logger)(new SampleResourceUsage());

        static::assertSame([
            'runtime.memory.rss_total',
            'runtime.memory.rss_max_process',
            'runtime.process.count',
            'runtime.fd.open_total',
            'runtime.tcp.in_use',
            'runtime.tcp.allocated',
        ], $names);
    }
}
