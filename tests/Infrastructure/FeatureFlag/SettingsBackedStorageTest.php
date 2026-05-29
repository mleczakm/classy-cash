<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\FeatureFlag;

use App\FeatureFlag\FeatureName;
use App\Infrastructure\FeatureFlag\SettingsBackedStorage;
use App\Settings\Settings;
use App\Tests\Assembler\SettingAssembler;
use App\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class SettingsBackedStorageTest extends FunctionalTestCase
{
    public function testReadsParentRegistrationFromSettings(): void
    {
        $em = $this->getEntityManager();
        $em->persist(SettingAssembler::new()
            ->withKey(FeatureName::SETTING_PARENT_REGISTRATION)
            ->withValue(false)
            ->assemble());
        $em->flush();

        /** @var Settings $settings */
        $settings = self::getContainer()->get(Settings::class);

        $storage = new SettingsBackedStorage($settings, [
            FeatureName::PARENT_REGISTRATION => [
                'setting_key' => FeatureName::SETTING_PARENT_REGISTRATION,
                'default' => true,
            ],
        ]);

        self::assertFalse($storage->get(FeatureName::PARENT_REGISTRATION)->isEnabled());
    }
}
