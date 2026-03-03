<?php

declare(strict_types=1);

namespace PhpInit\Cli\Command;

use PhpInit\Cli\Support\NameSanitizer;
use PhpInit\Cli\Support\ProjectContext;
use PhpInit\Cli\Support\SafeWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeMiddlewareCommand extends Command
{
    protected static $defaultName = 'make:middleware';

    protected function configure(): void
    {
        $this->setDescription('Crea un middleware personalizado')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del middleware');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $name = NameSanitizer::className((string) $input->getArgument('name'));

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Middleware;\n\nclass {$name}Middleware\n{\n    public static function handle(): void\n    {\n        // Implementar validacion de middleware\n    }\n}\n";

        SafeWriter::write(getcwd(), "app/Middleware/{$name}Middleware.php", $content);
        $output->writeln("<info>Creado app/Middleware/{$name}Middleware.php</info>");
        return Command::SUCCESS;
    }
}

