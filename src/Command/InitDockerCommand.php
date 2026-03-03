<?php

declare(strict_types=1);

namespace PhpInit\Cli\Command;

use PhpInit\Cli\Support\EnvReader;
use PhpInit\Cli\Support\ProjectContext;
use PhpInit\Cli\Support\SafeWriter;
use PhpInit\Cli\Support\ScaffoldTemplates;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class InitDockerCommand extends Command
{
    protected static $defaultName = 'init:docker';

    protected function configure(): void
    {
        $this->setDescription('Genera archivos Docker en proyecto existente');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $env = EnvReader::read(getcwd() . '/.env');
        $dbType = strtolower(trim((string) ($env['DB_TYPE'] ?? 'mysql')));
        $dbMode = strtolower(trim((string) ($env['DB_MODE'] ?? 'docker')));
        if (!in_array($dbType, ['mysql', 'sqlsrv'], true)) {
            $dbType = 'mysql';
            $output->writeln('<comment>DB_TYPE invalido en .env; se usa mysql por defecto.</comment>');
        }
        if (!in_array($dbMode, ['docker', 'connection-string'], true)) {
            $dbMode = 'docker';
        }

        SafeWriter::write(getcwd(), 'docker/Dockerfile', ScaffoldTemplates::dockerfile($dbType));
        SafeWriter::write(getcwd(), 'docker/nginx.conf', ScaffoldTemplates::nginxConf());
        SafeWriter::write(getcwd(), 'docker/healthcheck.php', ScaffoldTemplates::dockerHealthcheck());
        SafeWriter::write(getcwd(), 'docker-compose.yml', ScaffoldTemplates::dockerCompose($dbType, $dbMode));

        $output->writeln('<info>Docker inicializado.</info>');
        return Command::SUCCESS;
    }
}

