<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

final class SchemaSearchPathConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, string $schema)
    {
        parent::__construct($connection);

        $this->exec("SET search_path TO {$schema}");
    }
}
