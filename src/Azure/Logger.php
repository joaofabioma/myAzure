<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Log estruturado (JSON Lines) em logs/azure.log.
 * NUNCA registra tokens: qualquer valor que pareça um JWT ou header
 * Authorization é mascarado antes da escrita.
 */
final class Logger
{
    public function __construct(private readonly string $logFile)
    {
    }

    public static function default(): self
    {
        $dir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return new self($dir . '/azure.log');
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'ts'      => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.vP'),
            'level'   => $level,
            'message' => self::sanitize($message),
            'context' => array_map([self::class, 'sanitize'], $context),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents($this->logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /** Mascara tokens Bearer/JWT para nunca vazarem no log. */
    private static function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        if (!is_string($value)) {
            return $value;
        }
        // Header Authorization
        $value = preg_replace('/(Bearer|Basic)\s+[A-Za-z0-9\-\._~\+\/=]+/i', '$1 ***', $value);
        // JWT solto (três blocos base64url separados por ponto)
        return preg_replace('/eyJ[\w\-]+\.[\w\-]+\.[\w\-]+/', '***jwt***', $value);
    }
}
