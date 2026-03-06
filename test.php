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
echo str_repeat("<br>", 7);
echo '';

$hora = "7.34"; // hora convertida

//mostrar a duração no formato HH:MM
$horaDecimal = (float) str_replace(',', '.', $hora);
$horas = (int) floor($hora);
$minutos = (int) round(($horaDecimal - $horas) * 60);

if ($minutos === 60) {
    $horas++;
    $minutos = 0;
}

echo sprintf('%02d:%02d', $horas, $minutos);




