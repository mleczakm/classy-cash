<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

/**
 * Adapts the application's connection guard to the scheduler bundle's pre-run hook.
 */
final readonly class SchedulerConnectionGuard
{
    public function __construct(
        private ConnectionEnsurerInterface $connectionEnsurer,
    ) {}

    public function __invoke(): void
    {
        $this->connectionEnsurer->ensureConnection();
    }
}
