<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine;

final class SchemaAssetsFilter
{
    public function __invoke(string $assetName): bool
    {
        // Exclude sessions table from Doctrine management
        // Allow all other tables
        return !str_contains($assetName, 'sessions');
    }
}
