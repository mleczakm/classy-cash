<?php

declare(strict_types=1);

namespace App\Tests\UserInterface;

use App\Tests\Assembler\UserAssembler;
use App\Tests\Functional\FunctionalTestSettingsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('smoke')]
class HomepageTest extends WebTestCase
{
    use FunctionalTestSettingsTrait;

    #[DataProvider('pagesAccessibleWithoutAuthorizationDataProvider')]
    public function testPagesAccessibleWithoutAuthorization(string $path, int $code = 200): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->setupDefaultSettings($em);

        $client->request('GET', $path);
        $this->assertResponseStatusCodeSame($code);
    }

    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function pagesAccessibleWithoutAuthorizationDataProvider(): array
    {
        return [
            'Homepage' => ['/', 302],
            'Healthcheck' => ['/health'],
            'Ping' => ['/ping'],
            'Login' => ['/login'],
            'Register' => ['/register'],
            'Logout' => ['/logout', 302],
        ];
    }

    #[DataProvider('pagesAccessibleForUsersDataProvider')]
    public function testPagesAccessibleForUsers(string $path, int $code = 200): void
    {
        $client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->setupDefaultSettings($em);

        $user = UserAssembler::new()
            ->withRoles('ROLE_USER')
            ->assemble();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', $path);
        $this->assertResponseStatusCodeSame($code);
    }

    /**
     * @return array<string, array{0: string, 1?: int}>
     */
    public static function pagesAccessibleForUsersDataProvider(): array
    {
        return [
            'Panel' => ['/'],
            'Admin' => ['/treasurer', 302],
        ];
    }
}
