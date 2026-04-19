<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Middleware;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware;

final readonly class SchemaSearchPathMiddleware implements Middleware
{
    public function __construct(
        private string $schema
    ) {}

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new SchemaSearchPathDriver($driver, $this->schema);
    }
}
