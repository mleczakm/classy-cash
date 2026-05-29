<?php

declare(strict_types=1);

namespace App\Infrastructure\FeatureFlag;

use App\Settings\Settings;
use Novaway\Bundle\FeatureFlagBundle\Model\Feature;
use Novaway\Bundle\FeatureFlagBundle\Model\FeatureFlag;
use Novaway\Bundle\FeatureFlagBundle\Storage\FeatureUndefinedException;
use Novaway\Bundle\FeatureFlagBundle\Storage\Storage;

final readonly class SettingsBackedStorage implements Storage
{
    /**
     * @param array<string, array{setting_key: string, default?: bool, description?: string}> $features
     */
    public function __construct(
        private Settings $settings,
        private array $features,
    ) {}

    public function all(): array
    {
        $result = [];
        foreach ($this->features as $name => $config) {
            $result[$name] = $this->buildFeature($name, $config);
        }

        return $result;
    }

    public function get(string $feature): Feature
    {
        if (! isset($this->features[$feature])) {
            throw new FeatureUndefinedException(sprintf("Feature '%s' does not exist.", $feature));
        }

        return $this->buildFeature($feature, $this->features[$feature]);
    }

    /**
     * @param array{setting_key: string, default?: bool, description?: string} $config
     */
    private function buildFeature(string $name, array $config): FeatureFlag
    {
        $default = $config['default'] ?? false;
        $value = $this->settings->getOptional($config['setting_key'], $default);

        return new FeatureFlag(
            $name,
            $value === true || $value === '1' || $value === 1,
            $config['description'] ?? '',
        );
    }
}
