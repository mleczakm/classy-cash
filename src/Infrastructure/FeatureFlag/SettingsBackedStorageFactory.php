<?php

declare(strict_types=1);

namespace App\Infrastructure\FeatureFlag;

use App\Settings\Settings;
use Novaway\Bundle\FeatureFlagBundle\Factory\AbstractStorageFactory;
use Novaway\Bundle\FeatureFlagBundle\Storage\Storage;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SettingsBackedStorageFactory extends AbstractStorageFactory
{
    public function __construct(
        private readonly Settings $settings,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function createStorage(string $storageName, array $options = []): Storage
    {
        /** @var array{features: array<string, array{setting_key: string, default?: bool, description?: string}>} $validated */
        $validated = $this->validate($storageName, $options);

        return new SettingsBackedStorage($this->settings, $validated['features']);
    }

    protected function configureOptionResolver(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('features')
            ->setAllowedTypes('features', 'array');
    }
}
