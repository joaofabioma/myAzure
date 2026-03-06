<?php

namespace App\Class;

/**
 * Classe utilitária para Utilidades
 * @package App\Class
 * @author Joao Fabio
 * @since 2026-03-06
 */
class Util extends Classes
{

    /**
     * Formata hora decimal para formato HH:MM
     * @param string $pHora - Hora decimal a ser formatada
     * @return string - Hora formatada em HH:MM
     * @author Joao Fabio
     * @since 2026-03-06
     * @example Util::formatHora() // Retorna '00:00'
     * @example Util::formatHora('7.32') // Retorna '07:32'
     */
    public static function formatHora(string $pHora = '00:00'): string
    {
        if (empty($pHora)) {
            return '00:00';
        }
        if (strpos($pHora, ',') !== false) {
            $vHora = (float) str_replace(',', '.', $pHora);
        }

        $horas = (int) floor($vHora);
        $minutos = (int) round(($vHora - $horas) * 60);
        if ($minutos === 60) {
            $horas++;
            $minutos = 0;
        }
        return sprintf('%02d:%02d', $horas, $minutos);
    }
}
