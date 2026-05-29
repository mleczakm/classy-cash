<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ClassCouncil\ClassRole;
use App\FeatureFlag\FeatureName;
use App\Settings\Settings;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class TreasurerSettingsTest extends FunctionalTestCase
{
    use FunctionalTestSettingsTrait;

    public function testTreasurerCanAccessSettingsPage(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer@example.com', 'password');
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);
        $this->client->loginUser($user);

        $this->client->request('GET', '/treasurer/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ustawienia aplikacji');
    }

    public function testTreasurerCanDisableParentRegistration(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        $classRoom = $this->createClassRoom('4B');
        $user = $this->createUser('treasurer@example.com', 'password');
        $this->createMembership($user, $classRoom, ClassRole::TREASURER);
        $this->client->loginUser($user);

        $this->client->request('POST', '/treasurer/settings', [
            'settings' => [
                'appName' => 'Classy Cash 4B',
                'emailFrom' => 'noreply@example.com',
                'blikPhone' => '+48123456789',
                'transferAccount' => '12 3456 7890 1234 5678 9012 3456',
                'schoolName' => 'SP 1',
                'save' => '',
            ],
        ]);

        self::assertResponseRedirects('/treasurer/settings');

        /** @var FeatureManager $featureManager */
        $featureManager = self::getContainer()->get(FeatureManager::class);
        self::assertFalse($featureManager->isEnabled(FeatureName::PARENT_REGISTRATION));
    }

    public function testParentRegistrationRedirectWhenDisabled(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        /** @var Settings $settings */
        $settings = self::getContainer()->get(Settings::class);
        $settings->set(FeatureName::SETTING_PARENT_REGISTRATION, false);

        $this->client->request('GET', '/register');
        self::assertResponseRedirects('/login');
    }
}
