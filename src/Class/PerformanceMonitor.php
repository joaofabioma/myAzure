<?php

namespace App\Class;


class PerformanceMonitor extends Classes
{
    private static bool $enabled = true;
    private static array $timers = [];

    public static function start(
        string $action,
        ?string $file = null,
        ?int $line = null
    ): string {
        if (!self::$enabled) {
            return '';
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $file = $file ?? $trace['file'] ?? '';
        $line = $line ?? $trace['line'] ?? 0;

        $id = uniqid('perf_', true);
        self::$timers[$id] = [
            'start' => microtime(true),
            'action' => $action,
            'file' => $file,
            'line' => $line
        ];

        perf_log($action, $file, $line);
        return $id;
    }

    public static function stop(string $id): float
    {
        if (!isset(self::$timers[$id])) {
            return 0.0;
        }

        $duration = microtime(true) - self::$timers[$id]['start'];
        unset(self::$timers[$id]);

        return $duration;
    }

    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
}
