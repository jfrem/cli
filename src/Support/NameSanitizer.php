<?php

declare(strict_types=1);

namespace PhpInit\Cli\Support;

final class NameSanitizer
{
    public static function projectName(string $value): string
    {
        $trimmed = trim($value);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $trimmed)) {
            throw new \InvalidArgumentException("Nombre invalido para proyecto: {$value}");
        }

        if (strlen($trimmed) > 80) {
            throw new \InvalidArgumentException("Nombre demasiado largo para proyecto: {$value}");
        }

        return $trimmed;
    }

    public static function className(string $value, string $label = 'clase'): string
    {
        $trimmed = trim($value);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $trimmed)) {
            throw new \InvalidArgumentException("Nombre invalido para {$label}: {$value}");
        }

        if (strlen($trimmed) > 80) {
            throw new \InvalidArgumentException("Nombre demasiado largo para {$label}: {$value}");
        }

        return $trimmed;
    }

    public static function tableName(string $value): string
    {
        $trimmed = trim($value);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed)) {
            throw new \InvalidArgumentException("Nombre de tabla invalido: {$value}");
        }

        return $trimmed;
    }
}

