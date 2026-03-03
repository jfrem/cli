<?php

declare(strict_types=1);

namespace AudFact\Cli\Command;

use AudFact\Cli\Support\NameSanitizer;
use AudFact\Cli\Support\ProjectContext;
use AudFact\Cli\Support\SafeWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModelCommand extends Command
{
    protected static $defaultName = 'make:model';

    protected function configure(): void
    {
        $this->setDescription('Crea un modelo')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del modelo')
            ->addArgument('table', InputArgument::OPTIONAL, 'Nombre de tabla');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $name = NameSanitizer::className((string) $input->getArgument('name'));
        $table = (string) ($input->getArgument('table') ?: strtolower($name) . 's');
        $table = NameSanitizer::tableName($table);
        $class = $name . 'Model';

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nclass {$class} extends Model\n{\n    protected string \$table = '{$table}';\n    protected array \$fillable = [];\n}\n";

        SafeWriter::write(getcwd(), "app/Models/{$class}.php", $content);
        $output->writeln("<info>Creado app/Models/{$class}.php</info>");
        return Command::SUCCESS;
    }
}
