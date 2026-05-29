<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\FeatureFlag\FeatureName;
use App\Form\Model\SettingsDto;
use App\Settings\Settings;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;

final readonly class SettingsDtoMapper
{
    public function __construct(
        private Settings $settings,
        private FeatureManager $featureManager,
    ) {}

    public function create(): SettingsDto
    {
        $dto = new SettingsDto();
        $dto->appName = $this->settings->getName();
        $dto->schoolName = $this->getStringSetting('school_name');
        $dto->emailFrom = $this->getStringSetting('email_from');
        $dto->blikPhone = $this->getStringSetting('blik_phone');
        $dto->transferAccount = $this->getStringSetting('transfer_account');
        $dto->parentRegistrationEnabled = $this->featureManager->isEnabled(FeatureName::PARENT_REGISTRATION);

        return $dto;
    }

    public function save(SettingsDto $dto): void
    {
        $this->settings->set('app_name', $dto->appName);
        $this->settings->set('school_name', $dto->schoolName);
        $this->settings->set('email_from', $dto->emailFrom);
        $this->settings->set('blik_phone', $dto->blikPhone);
        $this->settings->set('transfer_account', $dto->transferAccount);
        $this->settings->set(FeatureName::SETTING_PARENT_REGISTRATION, $dto->parentRegistrationEnabled);
    }

    private function getStringSetting(string $key, string $default = ''): string
    {
        $value = $this->settings->getOptional($key, $default);

        return is_string($value) ? $value : $default;
    }
}
