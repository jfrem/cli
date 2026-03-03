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

final class MakeCrudCommand extends Command
{
    protected static $defaultName = 'make:crud';

    protected function configure(): void
    {
        $this->setDescription('Genera controller + model + rutas CRUD')
            ->addArgument('name', InputArgument::REQUIRED, 'Nombre del recurso');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        ProjectContext::assertInProject(getcwd());
        $name = NameSanitizer::className((string) $input->getArgument('name'));
        $lower = strtolower($name);

        $modelClass = $name . 'Model';
        $model = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nclass {$modelClass} extends Model\n{\n    protected string \$table = '{$lower}s';\n    protected array \$fillable = [];\n}\n";
        SafeWriter::write(getcwd(), "app/Models/{$modelClass}.php", $model);

        $controllerClass = $name . 'Controller';
        $controller = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Controllers;\n\nuse Core\\Response;\n\nclass {$controllerClass} extends Controller\n{\n    public function index(): void { Response::success([], 'Listado de {$name}'); }\n    public function show(string \$id): void { Response::success(['id' => \$id], 'Detalle de {$name}'); }\n    public function store(): void { \$data = \$this->getBody(); Response::success(\$data, '{$name} creado', 201); }\n    public function update(string \$id): void { \$data = \$this->getBody(); Response::success(['id' => \$id, 'payload' => \$data], '{$name} actualizado'); }\n    public function destroy(string \$id): void { Response::success(['id' => \$id], '{$name} eliminado'); }\n}\n";
        SafeWriter::write(getcwd(), "app/Controllers/{$controllerClass}.php", $controller);

        $routes = "\n\$router->get('/{$lower}s', '{$name}Controller', 'index');\n\$router->get('/{$lower}s/{id}', '{$name}Controller', 'show');\n\$router->post('/{$lower}s', '{$name}Controller', 'store');\n\$router->put('/{$lower}s/{id}', '{$name}Controller', 'update');\n\$router->delete('/{$lower}s/{id}', '{$name}Controller', 'destroy');\n";

        SafeWriter::append(getcwd(), 'app/Routes/web.php', $routes);
        $output->writeln('<info>CRUD generado: modelo, controlador y rutas.</info>');

        return Command::SUCCESS;
    }
}
