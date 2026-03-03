<?php

declare(strict_types=1);

namespace PhpInit\Cli;

use PhpInit\Cli\Command\DbFreshCommand;
use PhpInit\Cli\Command\DbMigrateCommand;
use PhpInit\Cli\Command\InitDockerCommand;
use PhpInit\Cli\Command\ListRoutesCommand;
use PhpInit\Cli\Command\MakeControllerCommand;
use PhpInit\Cli\Command\MakeCrudCommand;
use PhpInit\Cli\Command\MakeMiddlewareCommand;
use PhpInit\Cli\Command\MakeModelCommand;
use PhpInit\Cli\Command\NewProjectCommand;
use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public function __construct()
    {
        parent::__construct('php-init', '2.0.0-alpha');

        $this->add(new NewProjectCommand());
        $this->add(new MakeControllerCommand());
        $this->add(new MakeModelCommand());
        $this->add(new MakeMiddlewareCommand());
        $this->add(new MakeCrudCommand());
        $this->add(new ListRoutesCommand());
        $this->add(new DbMigrateCommand());
        $this->add(new DbFreshCommand());
        $this->add(new InitDockerCommand());
    }
}

