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

        if (!$this->isSafeDatabaseName($name)) {
            $output->writeln('<error>DB_NAME invalido. Solo letras, numeros y guion bajo (iniciando en letra).</error>');
            return Command::FAILURE;
        }

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
                $encrypt = $this->envBool($env, 'DB_ENCRYPT', true) ? 'yes' : 'no';
                $trust = $this->envBool($env, 'DB_TRUST_SERVER_CERT', false) ? 'yes' : 'no';
                $dsn = "sqlsrv:Server={$host},{$port};Database={$name};Encrypt={$encrypt};TrustServerCertificate={$trust}";
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

    /**
     * @param array<string,string> $env
     */
    private function envBool(array $env, string $key, bool $default): bool
    {
        $value = strtolower(trim((string) ($env[$key] ?? ($default ? '1' : '0'))));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function isSafeDatabaseName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z][A-Za-z0-9_]{0,127}$/', $name);
    }
}
