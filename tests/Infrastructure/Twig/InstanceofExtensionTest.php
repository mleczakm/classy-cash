<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Twig;

use App\Infrastructure\Twig\InstanceofExtension;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Twig\TwigTest;

#[Group('unit')]
final class InstanceofExtensionTest extends TestCase
{
    public function testRegistersInstanceofTest(): void
    {
        $tests = new InstanceofExtension()
            ->getTests();

        self::assertCount(1, $tests);
        self::assertInstanceOf(TwigTest::class, $tests[0]);
        self::assertSame('instanceof', $tests[0]->getName());
    }

    public function testMatchesByClassName(): void
    {
        $extension = new InstanceofExtension();

        self::assertTrue($extension->isInstanceof(new \RuntimeException(), \Throwable::class));
        self::assertFalse($extension->isInstanceof(new \stdClass(), \Throwable::class));
    }

    public function testMatchesByObjectInstance(): void
    {
        $extension = new InstanceofExtension();

        self::assertTrue($extension->isInstanceof(new \InvalidArgumentException(), new \LogicException()));
        self::assertFalse($extension->isInstanceof(new \LogicException(), new \RuntimeException()));
    }
}
