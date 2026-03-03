<?php

declare(strict_types=1);

namespace AudFact\Cli\Command;

use AudFact\Cli\Support\ProjectContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ListRoutesCommand extends Command
{
    protected static $defaultName = 'list:routes';

    protected function configure(): void
    {
        $this->setDescription('Lista rutas definidas en app/Routes/web.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $file = getcwd() . '/app/Routes/web.php';
        if (!is_file($file)) {
            $output->writeln('<error>No se encontro app/Routes/web.php</error>');
            return Command::FAILURE;
        }

        $content = (string) file_get_contents($file);
        $regex = '/\$router->(get|post|put|delete)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/i';
        preg_match_all($regex, $content, $matches, PREG_SET_ORDER);

        $output->writeln('METHOD  PATH                     CONTROLLER             ACTION');
        $output->writeln(str_repeat('-', 72));
        foreach ($matches as $m) {
            $output->writeln(sprintf('%-7s %-24s %-22s %s', strtoupper($m[1]), $m[2], $m[3], $m[4]));
        }

        return Command::SUCCESS;
    }
}
