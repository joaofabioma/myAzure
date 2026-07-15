<?php

namespace App\Class;

/**
 * Carrega variáveis de um arquivo .env (formato KEY=VALUE).
 * Substitui parse_ini_file(), que no PHP 8.5 falha em comentários # com parênteses.
 */
class Env
{
    public static function load(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($key === '') {
                continue;
            }

            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (str_ends_with($value, $quote) && strlen($value) > 1) {
                    $value = substr($value, 1, -1);
                }
            }

            $env[$key] = $value;
        }

        return $env;
    }
}
