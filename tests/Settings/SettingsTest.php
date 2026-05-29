<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\FeatureFlag\FeatureName;
use App\Settings\Settings;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\FunctionalTestSettingsTrait;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class SettingsTest extends FunctionalTestCase
{
    use FunctionalTestSettingsTrait;

    public function testParentRegistrationEnabledByDefault(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        /** @var FeatureManager $featureManager */
        $featureManager = self::getContainer()->get(FeatureManager::class);

        self::assertTrue($featureManager->isEnabled(FeatureName::PARENT_REGISTRATION));
    }

    public function testParentRegistrationCanBeDisabled(): void
    {
        /** @var FeatureManager $featureManager */
        $featureManager = self::getContainer()->get(FeatureManager::class);
        $settings = self::getContainer()->get(Settings::class);
        $settings->set('parent_registration_enabled', false);

        self::assertFalse($featureManager->isEnabled(FeatureName::PARENT_REGISTRATION));
    }

    public function testParentRegistrationDefaultsToTrueWhenMissing(): void
    {
        $em = $this->getEntityManager();
        $em->persist(SettingAssembler::new()->withKey('app_name')->withValue('Test')->assemble());
        $em->flush();

        /** @var FeatureManager $featureManager */
        $featureManager = self::getContainer()->get(FeatureManager::class);

        self::assertTrue($featureManager->isEnabled(FeatureName::PARENT_REGISTRATION));
    }
}
