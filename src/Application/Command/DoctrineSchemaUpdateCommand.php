<?php

declare(strict_types=1);

namespace App\Application\Command;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:doctrine:schema-update',
    description: 'Doctrine schema update with automatic schema creation'
)]
final class DoctrineSchemaUpdateCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly string $schemaName
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Dump the SQL instead of executing')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force the schema update')
            ->addOption('complete', null, InputOption::VALUE_NONE, 'Complete the schema update')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            // First ensure the schema exists
            $this->ensureSchemaExists($output);

            // Build the doctrine command arguments
            $args = ['bin/console', 'doctrine:schema:update'];

            if ($input->getOption('dump-sql')) {
                $args[] = '--dump-sql';
            }

            if ($input->getOption('force')) {
                $args[] = '--force';
            }

            if ($input->getOption('complete')) {
                $args[] = '--complete';
            }

            // Run the actual doctrine command
            $process = new Process($args);
            $process->run();

            $output->write($process->getOutput());
            $output->write($process->getErrorOutput());

            // After Doctrine schema update, create the sessions table
            if ($process->isSuccessful()) {
                $this->ensureSessionsTableExists($output);
            }

            return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to update schema: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to update schema', [
                'schema' => $this->schemaName,
                'error' => $e->getMessage(),
            ]);
            return Command::FAILURE;
        }
    }

    private function ensureSchemaExists(OutputInterface $output): void
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

        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to ensure schema exists: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to ensure schema exists', [
                'schema' => $this->schemaName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function ensureSessionsTableExists(OutputInterface $output): void
    {
        try {
            $tableName = $this->schemaName . '.sessions';

            // Check if sessions table exists
            $tableExists = $this->connection->fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$this->schemaName, 'sessions']
            );

            if (! $tableExists) {
                $output->writeln('Creating sessions table: ' . $tableName);

                // Create sessions table with proper schema for Symfony PDO session handler
                $this->connection->executeStatement("
                    CREATE TABLE {$this->connection->quoteIdentifier($tableName)} (
                        sess_id VARCHAR(128) NOT NULL PRIMARY KEY,
                        sess_data BYTEA NOT NULL,
                        sess_time INTEGER NOT NULL,
                        sess_lifetime INTEGER NOT NULL
                    )
                ");

                // Create index for session cleanup
                $this->connection->executeStatement("
                    CREATE INDEX sessions_sess_lifetime_idx
                    ON {$this->connection->quoteIdentifier(
                    $tableName
                )} (sess_lifetime)
                ");

                $output->writeln('Sessions table created successfully');
                $this->logger->info('Sessions table created successfully', [
                    'table' => $tableName,
                ]);
            } else {
                $output->writeln('Sessions table already exists: ' . $tableName);
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>Failed to ensure sessions table exists: ' . $e->getMessage() . '</error>');
            $this->logger->error('Failed to ensure sessions table exists', [
                'schema' => $this->schemaName,
                'error' => $e->getMessage(),
            ]);
            // Don't throw here - the sessions table is not critical for schema updates
        }
    }
}
