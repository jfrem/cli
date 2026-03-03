<?php

declare(strict_types=1);

namespace AudFact\Cli\Command;

use AudFact\Cli\Support\EnvReader;
use AudFact\Cli\Support\ProjectContext;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class DbMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';

    protected function configure(): void
    {
        $this->setDescription('Ejecuta migraciones SQL segun DB_TYPE');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());

        $env = EnvReader::read(getcwd() . '/.env');
        $dbType = $env['DB_TYPE'] ?? 'mysql';
        $host = $env['DB_HOST'] ?? 'localhost';
        $port = $env['DB_PORT'] ?? ($dbType === 'mysql' ? '3306' : '1433');
        $name = $env['DB_NAME'] ?? 'app_db';
        $user = $env['DB_USER'] ?? ($dbType === 'mysql' ? 'root' : 'sa');
        $pass = $env['DB_PASS'] ?? '';

        $migrationsDir = getcwd() . '/database/migrations/' . $dbType;
        if (!is_dir($migrationsDir)) {
            $output->writeln("<error>No existe {$migrationsDir}</error>");
            return Command::FAILURE;
        }

        $files = glob($migrationsDir . '/*.sql') ?: [];
        sort($files);
        if ($files === []) {
            $output->writeln('<error>No hay migraciones .sql</error>');
            return Command::FAILURE;
        }

        try {
            if ($dbType === 'sqlsrv') {
                $dsn = "sqlsrv:Server={$host},{$port};Database={$name};Encrypt=no;TrustServerCertificate=yes";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            }

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            foreach ($files as $file) {
                $sql = trim((string) file_get_contents($file));
                if ($sql === '') {
                    continue;
                }
                $output->writeln('<comment>Ejecutando ' . basename($file) . '</comment>');
                $pdo->exec($sql);
            }

            $output->writeln('<info>Migraciones completadas.</info>');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error en migraciones: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
