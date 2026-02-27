<?php
header('Content-Type: text/html; charset=utf-8');

require __DIR__ . '/functions.php';

function test(): ?string
{
    if (ONLINE === FALSE) {
        return null;
        // return 'teste offline';
    }
    return 'testado online' . PHP_EOL;
}

echo test();
echo 'fim';
