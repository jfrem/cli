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

final class MakeControllerCommand extends Command
{
    protected static $defaultName = 'make:controller';

    protected function configure(): void
    {
        $this->setDescription('Crea un controlador CRUD basico')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del controlador (sin sufijo Controller)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $name = NameSanitizer::className((string) $input->getArgument('name'));
        $class = $name . 'Controller';

        $content = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Controllers;\n\nuse Core\\Response;\n\nclass {$class} extends Controller\n{\n    public function index(): void\n    {\n        Response::success([], 'Listado de {$name}');\n    }\n\n    public function show(string \$id): void\n    {\n        Response::success(['id' => \$id], 'Detalle de {$name}');\n    }\n\n    public function store(): void\n    {\n        \$data = \$this->getBody();\n        Response::success(\$data, '{$name} creado', 201);\n    }\n\n    public function update(string \$id): void\n    {\n        \$data = \$this->getBody();\n        Response::success(['id' => \$id, 'payload' => \$data], '{$name} actualizado');\n    }\n\n    public function destroy(string \$id): void\n    {\n        Response::success(['id' => \$id], '{$name} eliminado');\n    }\n}\n";

        SafeWriter::write(getcwd(), "app/Controllers/{$class}.php", $content);
        $output->writeln("<info>Creado app/Controllers/{$class}.php</info>");
        return Command::SUCCESS;
    }
}

