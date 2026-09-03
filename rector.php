<?php

declare(strict_types=1);

use App\Infrastructure\Rector\AddPhpUnitGroupAttributeRector;
use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/config', __DIR__ . '/public', __DIR__ . '/src', __DIR__ . '/tests'])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRootFiles()
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withRules([AddPhpUnitGroupAttributeRector::class])
    ->withSkip([
        // These resettable services must stay non-readonly: the swoole-bundle coroutine
        // proxifier strips the final flag from stateful services and cannot on a readonly class.
        ReadOnlyClassRector::class => [
            __DIR__ . '/src/Infrastructure/Doctrine/EntityManagerResetter.php',
            __DIR__ . '/src/Infrastructure/ImapEngine/ConnectionCloserOnResetSubscriber.php',
        ],
    ])
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
