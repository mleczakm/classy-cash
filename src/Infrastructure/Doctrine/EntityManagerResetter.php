<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ServicesResetterInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

// Not readonly: the swoole-bundle coroutine proxifier strips the final flag from stateful
// services and cannot do so on a readonly class.
#[AsEventListener(event: 'kernel.terminate', method: 'reset')]
final class EntityManagerResetter implements ServicesResetterInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $env,
    ) {}

    public function reset(): void
    {
        if ($this->env === 'test') {
            return;
        }

        $this->entityManager->close();
    }
}
