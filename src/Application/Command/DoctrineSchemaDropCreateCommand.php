<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:doctrine:schema-drop-create',
    description: 'Drops and recreates the custom database schema (for test setup)'
)]
final class DoctrineSchemaDropCreateCommand extends Command
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
            $output->writeln("Dropping schema if exists: {$this->schemaName}");
            $this->connection->executeStatement(
                'DROP SCHEMA IF EXISTS ' . $this->connection->quoteIdentifier($this->schemaName) . ' CASCADE'
            );
            $output->writeln("Schema dropped (if existed): {$this->schemaName}");

            $output->writeln("Creating schema: {$this->schemaName}");
            $this->connection->executeStatement(
                'CREATE SCHEMA ' . $this->connection->quoteIdentifier($this->schemaName)
            );
            $output->writeln("Schema created: {$this->schemaName}");
            $this->logger->info('Schema dropped and recreated for test', [
                'schema' => $this->schemaName,
            ]);
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to drop/recreate schema: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to drop/recreate schema', [
                'schema' => $this->schemaName,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }
}
