<?php

declare(strict_types=1);

namespace App\Core;

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $value = match (strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                'empty', '(empty)' => '',
                default => $value,
            };

            if (getenv($key) === false && !isset($_SERVER[$key])) {
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            } else {
                $_ENV[$key] = $_SERVER[$key] ?? getenv($key);
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $val = getenv($key);
        if ($val !== false) {
            return $val;
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        return $default;
    }
}
