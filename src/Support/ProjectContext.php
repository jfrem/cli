<?php

declare(strict_types=1);

namespace AudFact\Cli\Support;

final class ProjectContext
{
    public static function assertInProject(string $cwd): void
    {
        $markers = ['composer.json', 'app', 'core', 'public'];
        foreach ($markers as $marker) {
            if (file_exists($cwd . DIRECTORY_SEPARATOR . $marker)) {
                return;
            }
        }

        throw new \RuntimeException('No estas en un proyecto generado por php-init.');
    }
}
