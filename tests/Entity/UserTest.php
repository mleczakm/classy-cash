<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use PHPUnit\Framework\Attributes\Group;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UserTest extends TestCase
{
    public function testNameIsSplitIntoFirstAndLastName(): void
    {
        $user = new User('parent@example.com', 'Jan Kowalski');

        self::assertSame('Jan', $user->getFirstName());
        self::assertSame('Kowalski', $user->getLastName());
    }

    public function testSingleWordNameHasEmptyLastName(): void
    {
        $user = new User('parent@example.com', 'Jan');

        self::assertSame('Jan', $user->getFirstName());
        self::assertSame('', $user->getLastName());
    }
}
