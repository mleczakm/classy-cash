<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

// Declared in "require" and genuinely run in production, but the analyser only
// sees them referenced from a class-string in YAML config or from Symfony's
// runtime bootstrap, so it can't tell they're used outside dev/test paths.
$config->ignoreErrorsOnPackages(
    [
        // Loaded internally by symfony/runtime's bootstrap (Dotenv::bootEnv()), not
        // via a `use` statement in project code; .env/.env.prod are shipped in every
        // environment, including prod.
        'symfony/dotenv',
        // The concrete symfony/http-client implementation is wired by the
        // `framework.http_client` config (config/packages/http_client.yaml);
        // production code only type-hints against HttpClientInterface (from the
        // separate http-client-contracts package, pulled in via symfony/contracts).
        'symfony/http-client',
    ],
    [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV],
);

// A custom Rector rule (src/Infrastructure/Rector/AddPhpUnitGroupAttributeRector.php)
// lives under src/ so it's autoloaded via rector.php without a dev-only autoload
// entry, but it's only ever invoked through `vendor/bin/rector` in dev/CI, never at
// application runtime.
$config->ignoreErrorsOnPackages(
    ['nikic/php-parser', 'phpunit/phpunit', 'rector/rector'],
    [ErrorType::DEV_DEPENDENCY_IN_PROD],
);

// Symfony bundles registered in config/bundles.php, Composer plugins, Doctrine
// custom types/DQL functions and Monolog handlers referenced by class name in
// config/packages/*.yaml, and packages used only internally by another
// dependency. All are genuinely used, just never `use`-imported by name in PHP
// the analyser scans.
$config->ignoreErrorsOnPackages(
    [
        // Monolog handler registered by class name in config/services.yaml.
        'bgalati/monolog-sentry-handler',
        // Bundle: config/bundles.php + bin/console doctrine:migrations:migrate.
        'doctrine/doctrine-migrations-bundle',
        // Backs symfony/scheduler's cron expression trigger; never `use`-imported
        // directly by project code.
        'dragonmantank/cron-expression',
        // Doctrine JSON ODM type referenced by class name in doctrine.yaml.
        'dunglas/doctrine-json-odm',
        // Class-name wired in config/services.yaml (Logdash monolog handler).
        'logdash/php-sdk',
        // Bundle: config/bundles.php + config/packages/symfony_health_check.yaml.
        'macpaw/symfony-health-check-bundle',
        // Doctrine DQL functions registered by class name in doctrine.yaml.
        'martin-georgiev/postgresql-for-doctrine',
        'scienta/doctrine-json-functions',
        // Bundle: config/bundles.php (swoole task scheduler integration).
        'mleczakm/swoole-bundle-scheduler',
        // Service registered by class name in config/services.yaml.
        'mleczakm/zip-bomb-honeypot',
        // PSR-7 implementation auto-discovered by symfony/http-client and the
        // Sentry SDK's PSR-18 client; never `use`-imported directly.
        'nyholm/psr7',
        // Used internally by symfony/property-info to read docblock types.
        'phpdocumentor/reflection-docblock',
        // Bundle: config/bundles.php (Sentry error reporting).
        'sentry/sentry-symfony',
        // Bundle: config/bundles.php (Swoole coroutine-safe service resetting).
        'swoole-bundle/resetter-bundle',
        // Used internally by swoole-bundle to proxify stateful services; never
        // `use`-imported directly.
        'swoole-bundle/z-engine',
        // Bundle/config features enabled via framework.yaml and asset_mapper.yaml,
        // not `use`-imported directly.
        'symfony/asset',
        'symfony/asset-mapper',
        // Mailer transport registered by its DSN scheme (`brevo+...`).
        'symfony/brevo-mailer',
        // Messenger's Doctrine transport, selected by the `doctrine://` DSN scheme
        // in messenger.yaml.
        'symfony/doctrine-messenger',
        // Used by Symfony's security voters/access-control expressions, not
        // `use`-imported directly by project code.
        'symfony/expression-language',
        // Composer plugin (Symfony Flex recipes), not a runtime dependency.
        'symfony/flex',
        // Bundle feature (HtmlSanitizer service), config-wired.
        'symfony/html-sanitizer',
        // Backs Twig's intl extra / ICU-based formatting, not `use`-imported
        // directly.
        'symfony/intl',
        // Configured via `framework.lock` in config/packages/lock.yaml.
        'symfony/lock',
        // Bundle: config/packages/monolog.yaml.
        'symfony/monolog-bundle',
        // Used internally by Symfony components (e.g. Messenger's worker
        // supervision), not `use`-imported directly.
        'symfony/process',
        // Used internally by the Form component for property mapping.
        'symfony/property-access',
        // Used internally by the Serializer/Form components for property
        // introspection.
        'symfony/property-info',
        // composer.json `extra.runtime` bootstrap (symfony/runtime is the actual
        // entrypoint, never `use`-imported).
        'symfony/runtime',
        // UX bundle: asset_mapper-wired Stimulus controllers.
        'symfony/stimulus-bundle',
        // Used internally by many Symfony components; not `use`-imported directly.
        'symfony/string',
        // Bundle + translation files under translations/, not `use`-imported
        // directly.
        'symfony/translation',
        // Bundle: config/packages/twig.yaml.
        'symfony/twig-bundle',
        // UX bundle: Turbo Stream/Drive integration, asset-wired.
        'symfony/ux-turbo',
        // Backs the `dump()` Twig/PHP helper, not `use`-imported directly.
        'symfony/var-dumper',
        // Used internally by Symfony's config cache warmup, not `use`-imported
        // directly.
        'symfony/var-exporter',
        // Bundle feature (Link component), config-wired.
        'symfony/web-link',
        // Used internally to parse config/*.yaml itself, not `use`-imported by
        // project code.
        'symfony/yaml',
        // Bundle: config/packages/tailwind.yaml.
        'symfonycasts/tailwind-bundle',
        // Bundle: config/packages/flowbite.yaml.
        'tales-from-a-dev/flowbite-bundle',
        // Twig extension bundle: registered by config/packages/twig.yaml, provides
        // the `cssinliner`/`inky`/`intl` Twig filters used in email templates.
        'twig/cssinliner-extra',
        'twig/extra-bundle',
        'twig/inky-extra',
        'twig/intl-extra',
    ],
    [ErrorType::UNUSED_DEPENDENCY],
);

// Baseline PHP extension requirements for the Symfony/Doctrine stack, never
// `use`-imported by name.
$config->ignoreErrorsOnExtension('ext-ctype', [ErrorType::UNUSED_DEPENDENCY]);
$config->ignoreErrorsOnExtension('ext-iconv', [ErrorType::UNUSED_DEPENDENCY]);
$config->ignoreErrorsOnExtension('ext-zlib', [ErrorType::UNUSED_DEPENDENCY]);

return $config;
