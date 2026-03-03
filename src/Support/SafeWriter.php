<?php

declare(strict_types=1);

namespace PhpInit\Cli\Support;

final class SafeWriter
{
    public static function write(string $basePath, string $relativePath, string $content): void
    {
        $base = rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
        $target = str_replace('\\', '/', $base . '/' . ltrim($relativePath, '/'));
        $dir = dirname($target);

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear directorio: {$dir}");
        }

        $realDir = str_replace('\\', '/', realpath($dir) ?: $dir);
        if (!str_starts_with($realDir, $base)) {
            throw new \RuntimeException("Ruta fuera del proyecto: {$relativePath}");
        }

        $tmp = $target . '.tmp-' . bin2hex(random_bytes(6));
        $normalized = rtrim(str_replace(["\r\n", "\r"], "\n", $content), "\n") . "\n";
        file_put_contents($tmp, $normalized, LOCK_EX);
        rename($tmp, $target);
    }

    public static function append(string $basePath, string $relativePath, string $content): void
    {
        $base = rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
        $target = str_replace('\\', '/', $base . '/' . ltrim($relativePath, '/'));
        $current = file_exists($target) ? (string) file_get_contents($target) : '';
        self::write($basePath, $relativePath, $current . "\n" . trim($content) . "\n");
    }
}

