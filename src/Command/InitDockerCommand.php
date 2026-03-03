<?php

declare(strict_types=1);

namespace AudFact\Cli\Command;

use AudFact\Cli\Support\EnvReader;
use AudFact\Cli\Support\ProjectContext;
use AudFact\Cli\Support\SafeWriter;
use AudFact\Cli\Support\ScaffoldTemplates;
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
        $dbType = $env['DB_TYPE'] ?? 'mysql';

        SafeWriter::write(getcwd(), 'docker/Dockerfile', ScaffoldTemplates::dockerfile());
        SafeWriter::write(getcwd(), 'docker/nginx.conf', ScaffoldTemplates::nginxConf());
        SafeWriter::write(getcwd(), 'docker/healthcheck.php', ScaffoldTemplates::dockerHealthcheck());
        SafeWriter::write(getcwd(), 'docker-compose.yml', ScaffoldTemplates::dockerCompose($dbType));

        $output->writeln('<info>Docker inicializado.</info>');
        return Command::SUCCESS;
    }
}
