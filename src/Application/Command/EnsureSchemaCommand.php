<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ensure-schema', description: 'Ensures the database schema exists')]
final class EnsureSchemaCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly string $schemaName
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // Check if schema exists
            $schemaExists = $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.schemata WHERE schema_name = ?',
                [$this->schemaName]
            );

            if (! $schemaExists) {
                $output->writeln('Creating database schema: ' . $this->schemaName);
                $this->connection->executeStatement(
                    'CREATE SCHEMA ' . $this->connection->quoteIdentifier($this->schemaName)
                );
                $output->writeln('Database schema created successfully');
                $this->logger->info('Database schema created successfully', [
                    'schema' => $this->schemaName,
                ]);
            } else {
                $output->writeln('Database schema already exists: ' . $this->schemaName);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to ensure schema exists: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to ensure schema exists', [
                'schema' => $this->schemaName,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }
}
