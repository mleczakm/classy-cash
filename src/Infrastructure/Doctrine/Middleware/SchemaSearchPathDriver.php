<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Connection;
use SensitiveParameter;

final class SchemaSearchPathDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly string $schema
    ) {
        parent::__construct($driver);
    }

    #[\Override]
    public function connect(#[SensitiveParameter] array $params): Connection
    {
        $connection = parent::connect($params);

        return new SchemaSearchPathConnection($connection, $this->schema);
    }
}
