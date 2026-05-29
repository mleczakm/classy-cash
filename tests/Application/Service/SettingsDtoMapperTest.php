<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\SettingsDtoMapper;
use App\Form\Model\SettingsDto;
use App\Tests\Functional\FunctionalTestCase;
use App\Tests\Functional\FunctionalTestSettingsTrait;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use App\FeatureFlag\FeatureName;
use PHPUnit\Framework\Attributes\Group;

#[Group('functional')]
final class SettingsDtoMapperTest extends FunctionalTestCase
{
    use FunctionalTestSettingsTrait;

    public function testCreateMapsCurrentSettings(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        /** @var SettingsDtoMapper $mapper */
        $mapper = self::getContainer()->get(SettingsDtoMapper::class);
        $dto = $mapper->create();

        self::assertSame('ClassyCash', $dto->appName);
        self::assertSame('noreply@example.com', $dto->emailFrom);
        self::assertTrue($dto->parentRegistrationEnabled);
    }

    public function testSavePersistsDtoValues(): void
    {
        $em = $this->getEntityManager();
        $this->setupDefaultSettings($em);

        /** @var SettingsDtoMapper $mapper */
        $mapper = self::getContainer()->get(SettingsDtoMapper::class);

        $dto = new SettingsDto();
        $dto->appName = 'Classy Cash 4B';
        $dto->schoolName = 'SP 1';
        $dto->emailFrom = 'skarbnik@example.com';
        $dto->blikPhone = '+48987654321';
        $dto->transferAccount = '11 1111 1111 1111 1111 1111 1111';
        $dto->parentRegistrationEnabled = false;

        $mapper->save($dto);

        $saved = $mapper->create();
        self::assertSame('Classy Cash 4B', $saved->appName);
        self::assertSame('SP 1', $saved->schoolName);
        self::assertFalse($saved->parentRegistrationEnabled);

        /** @var FeatureManager $featureManager */
        $featureManager = self::getContainer()->get(FeatureManager::class);
        self::assertFalse($featureManager->isEnabled(FeatureName::PARENT_REGISTRATION));
    }
}
