<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\SchedulerConnectionGuard;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SchedulerConnectionGuardTest extends TestCase
{
    public function testItEnsuresTheConnection(): void
    {
        $connectionEnsurer = $this->createMock(ConnectionEnsurerInterface::class);
        $connectionEnsurer->expects(self::once())
            ->method('ensureConnection');

        new SchedulerConnectionGuard($connectionEnsurer)();
    }
}
