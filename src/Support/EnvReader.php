<?php

declare(strict_types=1);

namespace AudFact\Cli\Support;

final class EnvReader
{
    /**
     * @return array<string,string>
     */
    public static function read(string $envFile): array
    {
        if (!file_exists($envFile)) {
            throw new \RuntimeException("No existe archivo .env en {$envFile}");
        }

        $vars = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"");
        }

        return $vars;
    }
}
