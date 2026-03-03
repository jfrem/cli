<?php

declare(strict_types=1);

namespace AudFact\Cli\Command;

use AudFact\Cli\Support\EnvReader;
use AudFact\Cli\Support\ProjectContext;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

final class DbFreshCommand extends Command
{
    protected static $defaultName = 'db:fresh';

    protected function configure(): void
    {
        $this->setDescription('Elimina y reconstruye esquema')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forzar sin confirmacion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());

        if (!(bool) $input->getOption('force')) {
            $q = new ConfirmationQuestion('Esto destruira tablas/datos. Continuar? (y/N): ', false);
            if (!$this->getHelper('question')->ask($input, $output, $q)) {
                $output->writeln('<comment>Cancelado.</comment>');
                return Command::SUCCESS;
            }
        }

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

        try {
            if ($dbType === 'mysql') {
                $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo->exec("DROP DATABASE IF EXISTS `{$name}`");
                $pdo->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $output->writeln('<info>Base reconstruida (MySQL).</info>');
            } else {
                if (in_array(strtolower($name), ['master', 'model', 'msdb', 'tempdb'], true)) {
                    $output->writeln('<error>Operacion bloqueada: DB_NAME apunta a una base de sistema SQL Server.</error>');
                    return Command::FAILURE;
                }

                $encrypt = $this->envBool($env, 'DB_ENCRYPT', true) ? 'yes' : 'no';
                $trust = $this->envBool($env, 'DB_TRUST_SERVER_CERT', false) ? 'yes' : 'no';
                $pdo = new PDO(
                    "sqlsrv:Server={$host},{$port};Database=master;Encrypt={$encrypt};TrustServerCertificate={$trust}",
                    $user,
                    $pass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $dbEscaped = str_replace(']', ']]', $name);
                $pdo->exec("IF DB_ID('{$dbEscaped}') IS NOT NULL BEGIN ALTER DATABASE [{$dbEscaped}] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [{$dbEscaped}] END");
                $pdo->exec("CREATE DATABASE [{$dbEscaped}]");
                $output->writeln('<info>Base reconstruida (SQL Server).</info>');
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>Error reconstruyendo base: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return (new DbMigrateCommand())->run(new \Symfony\Component\Console\Input\ArrayInput([
            'command' => 'db:migrate',
        ]), $output);
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
