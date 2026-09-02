<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine;

use App\Infrastructure\Doctrine\ConnectionEnsurerInterface;
use App\Infrastructure\Doctrine\RequestConnectionEnsurer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[Group('unit')]
final class RequestConnectionEnsurerTest extends TestCase
{
    public function testEnsuresConnectionOnMainRequest(): void
    {
        $ensurer = $this->createMock(ConnectionEnsurerInterface::class);
        $ensurer->expects($this->once())
            ->method('ensureConnection');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );

        new RequestConnectionEnsurer($ensurer)
            ->onKernelRequest($event);
    }

    public function testIgnoresSubRequest(): void
    {
        $ensurer = $this->createMock(ConnectionEnsurerInterface::class);
        $ensurer->expects($this->never())
            ->method('ensureConnection');

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::SUB_REQUEST,
        );

        new RequestConnectionEnsurer($ensurer)
            ->onKernelRequest($event);
    }
}
