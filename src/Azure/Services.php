<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Container mínimo de injeção de dependência (lazy singletons por requisição).
 *
 * Uso:
 *   Services::init($env);                      // no prepend.php
 *   $devops = Services::devOps();              // em qualquer lugar
 *   $token  = Services::auth()->getAccessToken();
 */
final class Services
{
    private static ?Config $config = null;
    private static ?Logger $logger = null;
    private static ?TokenStore $tokenStore = null;
    private static ?AuthService $auth = null;
    private static ?AzureDevOpsService $devOps = null;

    public static function init(array $env): void
    {
        self::$config = Config::fromEnv($env);
    }

    public static function config(): Config
    {
        if (self::$config === null) {
            throw new \LogicException('Services::init($env) deve ser chamado antes (inc/prepend.php).');
        }
        return self::$config;
    }

    public static function logger(): Logger
    {
        return self::$logger ??= Logger::default();
    }

    public static function tokenStore(): TokenStore
    {
        return self::$tokenStore ??= new TokenStore(self::config()->appKey, self::logger());
    }

    public static function auth(): AuthService
    {
        return self::$auth ??= new AuthService(self::config(), self::tokenStore(), self::logger());
    }

    public static function devOps(): AzureDevOpsService
    {
        return self::$devOps ??= new AzureDevOpsService(self::config(), self::auth(), self::logger());
    }
}
