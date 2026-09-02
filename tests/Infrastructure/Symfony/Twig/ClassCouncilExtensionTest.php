<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Symfony\Twig;

use App\Entity\ClassCouncil\ClassMembership;
use App\Entity\ClassCouncil\ClassRole;
use App\Entity\ClassCouncil\ClassRoom;
use App\Entity\User;
use App\Infrastructure\Symfony\Twig\ClassCouncilExtension;
use App\Repository\ClassCouncil\ClassMembershipRepository;
use App\Repository\ClassCouncil\ClassRoomRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[Group('unit')]
final class ClassCouncilExtensionTest extends TestCase
{
    public function testRegistersTwigFunctions(): void
    {
        $names = array_map(
            static fn($fn): string => $fn->getName(),
            $this->extension(security: $this->security())
                ->getFunctions(),
        );

        self::assertSame(['cc_is_treasurer', 'cc_is_carer'], $names);
    }

    public function testAdminIsAlwaysTreasurerAndCarer(): void
    {
        $extension = $this->extension(security: $this->security(admin: true));

        self::assertTrue($extension->isTreasurer());
        self::assertTrue($extension->isCarer());
    }

    public function testAnonymousUserIsNeitherTreasurerNorCarer(): void
    {
        $extension = $this->extension(security: $this->security(user: null));

        self::assertFalse($extension->isTreasurer());
        self::assertFalse($extension->isCarer());
    }

    public function testFalseWhenNoClassRoomExists(): void
    {
        $extension = $this->extension(classRoom: null, security: $this->security(user: new User()));

        self::assertFalse($extension->isTreasurer());
        self::assertFalse($extension->isCarer());
    }

    public function testFalseWhenUserHasNoMembership(): void
    {
        $extension = $this->extension(
            classRoom: new ClassRoom('4B'),
            membership: null,
            security: $this->security(user: new User()),
        );

        self::assertFalse($extension->isTreasurer());
        self::assertFalse($extension->isCarer());
    }

    public function testTreasurerMembershipGrantsBothRoles(): void
    {
        $user = new User();
        $classRoom = new ClassRoom('4B');
        $extension = $this->extension(
            classRoom: $classRoom,
            membership: new ClassMembership($user, $classRoom, ClassRole::TREASURER),
            security: $this->security(user: $user),
        );

        self::assertTrue($extension->isTreasurer());
        self::assertTrue($extension->isCarer());
    }

    #[DataProvider('carerOnlyRoles')]
    public function testCarerRolesAreNotTreasurers(ClassRole $role): void
    {
        $user = new User();
        $classRoom = new ClassRoom('4B');
        $extension = $this->extension(
            classRoom: $classRoom,
            membership: new ClassMembership($user, $classRoom, $role),
            security: $this->security(user: $user),
        );

        self::assertFalse($extension->isTreasurer());
        self::assertTrue($extension->isCarer());
    }

    public function testParentRoleIsNeitherTreasurerNorCarer(): void
    {
        $user = new User();
        $classRoom = new ClassRoom('4B');
        $extension = $this->extension(
            classRoom: $classRoom,
            membership: new ClassMembership($user, $classRoom, ClassRole::PARENT),
            security: $this->security(user: $user),
        );

        self::assertFalse($extension->isTreasurer());
        self::assertFalse($extension->isCarer());
    }

    /**
     * @return iterable<string, array{ClassRole}>
     */
    public static function carerOnlyRoles(): iterable
    {
        yield 'president' => [ClassRole::PRESIDENT];
        yield 'vice president' => [ClassRole::VICE_PRESIDENT];
    }

    private function security(bool $admin = false, User|false|null $user = false): Security
    {
        $security = $this->createMock(Security::class);
        $security->method('isGranted')
            ->with('ROLE_ADMIN')
            ->willReturn($admin);

        if ($user !== false) {
            $security->method('getUser')
                ->willReturn($user);
        }

        return $security;
    }

    private function extension(
        ?ClassRoom $classRoom = null,
        ?ClassMembership $membership = null,
        ?Security $security = null,
    ): ClassCouncilExtension {
        $classRooms = $this->createMock(ClassRoomRepository::class);
        $classRooms->method('findOneBy')
            ->willReturn($classRoom);

        $memberships = $this->createMock(ClassMembershipRepository::class);
        $memberships->method('findOneBy')
            ->willReturn($membership);

        return new ClassCouncilExtension($classRooms, $memberships, $security ?? $this->security());
    }
}
