<?php

declare(strict_types=1);

namespace PhpInit\Cli\Command;

use PhpInit\Cli\Support\NameSanitizer;
use PhpInit\Cli\Support\ProjectScaffolder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

final class NewProjectCommand extends Command
{
    protected static $defaultName = 'new';

    protected function configure(): void
    {
        $this
            ->setDescription('Crea un nuevo proyecto PHP MVC')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del proyecto')
            ->addOption('preset', null, InputOption::VALUE_REQUIRED, 'Preset: api-basic|api-auth-jwt|api-enterprise')
            ->addOption('db-mode', null, InputOption::VALUE_REQUIRED, 'Modalidad DB: docker|connection-string')
            ->addOption('database', null, InputOption::VALUE_REQUIRED, 'Driver de base de datos: mysql|sqlsrv')
            ->addOption('db-dsn', null, InputOption::VALUE_REQUIRED, 'Cadena de conexion PDO para instancia existente')
            ->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Host de base de datos')
            ->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Puerto de base de datos')
            ->addOption('db-name', null, InputOption::VALUE_REQUIRED, 'Nombre de base de datos')
            ->addOption('db-user', null, InputOption::VALUE_REQUIRED, 'Usuario de base de datos')
            ->addOption('db-pass', null, InputOption::VALUE_REQUIRED, 'Contrasena de base de datos')
            ->addOption('allowed-origins', null, InputOption::VALUE_REQUIRED, 'Lista CORS separada por comas o *')
            ->addOption('jwt-access-exp', null, InputOption::VALUE_REQUIRED, 'JWT access token (segundos)')
            ->addOption('jwt-refresh-exp', null, InputOption::VALUE_REQUIRED, 'JWT refresh token (segundos)')
            ->addOption('with-docker', null, InputOption::VALUE_NONE, 'Generar Docker')
            ->addOption('no-tests', null, InputOption::VALUE_NONE, 'No generar estructura de tests')
            ->addOption('run-composer', null, InputOption::VALUE_NONE, 'Ejecutar composer install al finalizar')
            ->addOption('run-migrate', null, InputOption::VALUE_NONE, 'Ejecutar db:migrate al finalizar')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Entorno: development|production', 'development');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $name = NameSanitizer::projectName((string) $input->getArgument('name'));

        $preset = (string) ($input->getOption('preset') ?: '');
        if ($preset === '' && $input->isInteractive()) {
            $q = new ChoiceQuestion('Preset del proyecto', ['api-basic', 'api-auth-jwt', 'api-enterprise'], 'api-auth-jwt');
            $preset = (string) $helper->ask($input, $output, $q);
        }
        $preset = $preset !== '' ? $preset : 'api-auth-jwt';

        $dbMode = (string) ($input->getOption('db-mode') ?: '');
        if ($dbMode === '' && $input->isInteractive()) {
            $q = new ChoiceQuestion('Modalidad de configuracion DB', ['docker', 'connection-string'], 'docker');
            $dbMode = (string) $helper->ask($input, $output, $q);
        }
        $dbMode = $dbMode !== '' ? $dbMode : 'docker';

        $dbType = (string) ($input->getOption('database') ?: '');
        if ($dbType === '' && $input->isInteractive()) {
            $q = new ChoiceQuestion('Motor de base de datos', ['mysql', 'sqlsrv'], 'sqlsrv');
            $dbType = (string) $helper->ask($input, $output, $q);
        }
        $dbType = $dbType !== '' ? $dbType : 'sqlsrv';

        if (!in_array($preset, ['api-basic', 'api-auth-jwt', 'api-enterprise'], true)) {
            $output->writeln('<error>Preset invalido.</error>');
            return Command::FAILURE;
        }
        if (!in_array($dbMode, ['docker', 'connection-string'], true)) {
            $output->writeln('<error>db-mode invalido. Usa docker o connection-string.</error>');
            return Command::FAILURE;
        }
        if (!in_array($dbType, ['mysql', 'sqlsrv'], true)) {
            $output->writeln('<error>Driver DB invalido.</error>');
            return Command::FAILURE;
        }

        $defaultPort = $dbType === 'mysql' ? '3306' : '1433';
        $defaultUser = $dbType === 'mysql' ? 'root' : 'sa';
        $defaultDsn = $dbType === 'sqlsrv'
            ? 'sqlsrv:Server=localhost,1433;Database=app_db;Encrypt=yes;TrustServerCertificate=no'
            : 'mysql:host=localhost;port=3306;dbname=app_db;charset=utf8mb4';

        $dbDsn = (string) ($input->getOption('db-dsn') ?: '');
        $dbHost = (string) ($input->getOption('db-host') ?: '');
        $dbPort = (string) ($input->getOption('db-port') ?: '');
        $dbName = (string) ($input->getOption('db-name') ?: '');
        $dbUser = (string) ($input->getOption('db-user') ?: '');
        $dbPass = (string) ($input->getOption('db-pass') ?: '');

        if ($input->isInteractive()) {
            if ($dbMode === 'docker') {
                if ($dbHost === '') {
                    $dbHost = (string) $helper->ask($input, $output, new Question('DB host [localhost]: ', 'localhost'));
                }
                if ($dbPort === '') {
                    $dbPort = (string) $helper->ask($input, $output, new Question("DB puerto [{$defaultPort}]: ", $defaultPort));
                }
                if ($dbName === '') {
                    $dbName = (string) $helper->ask($input, $output, new Question('DB nombre [app_db]: ', 'app_db'));
                }
                if ($dbUser === '') {
                    $dbUser = (string) $helper->ask($input, $output, new Question("DB usuario [{$defaultUser}]: ", $defaultUser));
                }
                if ($dbPass === '') {
                    $q = new Question('DB contrasena [vacio]: ', '');
                    $q->setHidden(true);
                    $q->setHiddenFallback(false);
                    $dbPass = (string) $helper->ask($input, $output, $q);
                }
            } else {
                if ($dbDsn === '') {
                    $dbDsn = (string) $helper->ask($input, $output, new Question("DB DSN [{$defaultDsn}]: ", $defaultDsn));
                }
                if ($dbUser === '') {
                    $dbUser = (string) $helper->ask($input, $output, new Question("DB usuario [{$defaultUser}]: ", $defaultUser));
                }
                if ($dbPass === '') {
                    $q = new Question('DB contrasena [vacio]: ', '');
                    $q->setHidden(true);
                    $q->setHiddenFallback(false);
                    $dbPass = (string) $helper->ask($input, $output, $q);
                }
            }
        }

        $dbHost = $dbHost !== '' ? $dbHost : 'localhost';
        $dbPort = $dbPort !== '' ? $dbPort : $defaultPort;
        $dbName = $dbName !== '' ? $dbName : 'app_db';
        $dbUser = $dbUser !== '' ? $dbUser : $defaultUser;

        if ($dbMode === 'connection-string') {
            if ($dbDsn === '') {
                $output->writeln('<error>Debes proporcionar --db-dsn cuando db-mode=connection-string.</error>');
                return Command::FAILURE;
            }
            if (!str_contains($dbDsn, ':')) {
                $output->writeln('<error>DB DSN invalido. Debe iniciar con el driver PDO (ej: sqlsrv:... o mysql:...).</error>');
                return Command::FAILURE;
            }
        }

        $appEnv = (string) $input->getOption('env');
        if (!in_array($appEnv, ['development', 'production'], true)) {
            $output->writeln('<error>Env invalido. Usa development o production.</error>');
            return Command::FAILURE;
        }

        $allowedOrigins = (string) ($input->getOption('allowed-origins') ?: '');
        $jwtAccessExp = (string) ($input->getOption('jwt-access-exp') ?: '');
        $jwtRefreshExp = (string) ($input->getOption('jwt-refresh-exp') ?: '');
        $generateTests = !(bool) $input->getOption('no-tests');
        $runComposer = (bool) $input->getOption('run-composer');
        $runMigrate = (bool) $input->getOption('run-migrate');
        $withDocker = (bool) $input->getOption('with-docker');

        if ($dbMode === 'docker') {
            $withDocker = true;
        } elseif (!$withDocker && $input->isInteractive()) {
            $withDocker = (bool) $helper->ask($input, $output, new ConfirmationQuestion('Generar Docker para PHP+Nginx (sin DB)? (y/N): ', false));
        }

        if ($input->isInteractive()) {
            $envQ = new ChoiceQuestion('Entorno inicial', ['development', 'production'], $appEnv);
            $appEnv = (string) $helper->ask($input, $output, $envQ);

            if ($allowedOrigins === '') {
                $corsDefault = $appEnv === 'development' ? '*' : '';
                $allowedOrigins = (string) $helper->ask($input, $output, new Question('ALLOWED_ORIGINS [* en dev / vacio en prod para definir luego]: ', $corsDefault));
            }

            if ($preset !== 'api-basic') {
                if ($jwtAccessExp === '') {
                    $jwtAccessExp = (string) $helper->ask($input, $output, new Question('JWT access expiration (seg) [900]: ', '900'));
                }
                if ($jwtRefreshExp === '') {
                    $jwtRefreshExp = (string) $helper->ask($input, $output, new Question('JWT refresh expiration (seg) [2592000]: ', '2592000'));
                }
            }

            $generateTests = (bool) $helper->ask($input, $output, new ConfirmationQuestion('Generar estructura de tests? (Y/n): ', true));
            $runComposer = (bool) $helper->ask($input, $output, new ConfirmationQuestion('Ejecutar composer install al finalizar? (y/N): ', false));
            $runMigrate = (bool) $helper->ask($input, $output, new ConfirmationQuestion('Ejecutar db:migrate al finalizar? (y/N): ', false));
        }

        if ($allowedOrigins === '') {
            $allowedOrigins = $appEnv === 'development' ? '*' : '';
        }
        if ($jwtAccessExp === '') {
            $jwtAccessExp = '900';
        }
        if ($jwtRefreshExp === '') {
            $jwtRefreshExp = '2592000';
        }

        $projectPath = getcwd() . DIRECTORY_SEPARATOR . $name;
        if (file_exists($projectPath)) {
            $output->writeln('<error>El directorio ya existe.</error>');
            return Command::FAILURE;
        }

        $config = [
            'projectName' => $name,
            'preset' => $preset,
            'dbMode' => $dbMode,
            'dbType' => $dbType,
            'dbDsn' => $dbDsn,
            'dbHost' => $dbHost,
            'dbPort' => $dbPort,
            'dbName' => $dbName,
            'dbUser' => $dbUser,
            'dbPass' => $dbPass,
            'appEnv' => $appEnv,
            'allowedOrigins' => $allowedOrigins,
            'jwtAccessExp' => (int) $jwtAccessExp,
            'jwtRefreshExp' => (int) $jwtRefreshExp,
            'generateTests' => $generateTests,
            'withDocker' => $withDocker,
        ];

        (new ProjectScaffolder())->generate($projectPath, $config);

        $output->writeln("<info>Proyecto creado: {$name}</info>");
        $output->writeln("<comment>Siguientes pasos:</comment>");
        $output->writeln("  cd {$name}");
        $output->writeln('  composer install');
        if ($withDocker) {
            $output->writeln('  docker compose up -d --build');
            $output->writeln('  curl.exe http://localhost:8080/health');
            if ($dbMode === 'connection-string') {
                $output->writeln('  # Nota: la base se conecta por DB_DSN; no se levanta servicio DB en docker-compose.');
            }
        } else {
            $output->writeln('  php -S localhost:8000 -t public');
            $output->writeln('  curl.exe http://127.0.0.1:8000/health');
        }

        if ($runComposer) {
            $output->writeln('<comment>Ejecutando composer install...</comment>');
            $this->runShellCommand('composer install', $projectPath, $output);
        }

        if ($runMigrate) {
            $output->writeln('<comment>Ejecutando db:migrate...</comment>');
            $cliBin = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php-init';
            $cmd = PHP_BINARY . ' ' . escapeshellarg($cliBin) . ' db:migrate';
            $this->runShellCommand($cmd, $projectPath, $output);
        }

        return Command::SUCCESS;
    }

    private function runShellCommand(string $command, string $cwd, OutputInterface $output): void
    {
        $descriptor = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptor, $pipes, $cwd);
        if (!is_resource($process)) {
            $output->writeln('<error>No se pudo iniciar comando: ' . $command . '</error>');
            return;
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            $output->writeln("<error>Comando fallo ({$exitCode}): {$command}</error>");
        }
    }
}

