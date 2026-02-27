<?php

namespace App\Class;


class Security extends Classes
{
    public static function validateRequestByFile(string $file): void
    {
        $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?? '';

        if (basename($uri) === basename($file)) {
            header('Location: /', true, 301);
            exit();
        }
    }
}
