<?php

declare(strict_types=1);

namespace AudFact\Cli;

use AudFact\Cli\Command\DbFreshCommand;
use AudFact\Cli\Command\DbMigrateCommand;
use AudFact\Cli\Command\InitDockerCommand;
use AudFact\Cli\Command\ListRoutesCommand;
use AudFact\Cli\Command\MakeControllerCommand;
use AudFact\Cli\Command\MakeCrudCommand;
use AudFact\Cli\Command\MakeMiddlewareCommand;
use AudFact\Cli\Command\MakeModelCommand;
use AudFact\Cli\Command\NewProjectCommand;
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
