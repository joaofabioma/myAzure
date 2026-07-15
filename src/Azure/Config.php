<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Configuração centralizada da integração Azure DevOps + Microsoft Entra ID.
 *
 * Nenhum segredo é mantido aqui além do que já está no .env
 * (TenantId e ClientId NÃO são segredos — o fluxo usa PKCE, sem client secret).
 */
final class Config
{
    /** Application ID (resource) fixo do Azure DevOps no Entra ID. */
    public const AZURE_DEVOPS_RESOURCE = '499b84ac-1321-427f-aa17-267ca6975798';

    public const AUTH_MODE_CLI    = 'cli';
    public const AUTH_MODE_OAUTH  = 'oauth';
    /** Device Code Flow — login Microsoft no navegador, sem App Registration no Portal. */
    public const AUTH_MODE_DEVICE = 'device';

    /** Client ID público da Microsoft Azure CLI (não exige App Registration próprio). */
    public const DEVICE_CLIENT_ID = '04b07795-8ddb-461a-bbee-02f9e1bf7b46';

    public function __construct(
        public readonly string $organization,
        public readonly string $project,
        public readonly string $tenantId,
        public readonly string $clientId,
        public readonly string $authMode = self::AUTH_MODE_CLI,
        public readonly string $redirectUri = '',
        public readonly string $appKey = '',
        public readonly int $httpTimeout = 30,
        public readonly int $httpRetries = 3,
    ) {}

    /**
     * Cria a configuração a partir do array retornado por App\Class\Env::load('.env').
     */
    public static function fromEnv(array $env): self
    {
        $mode = strtolower(trim((string)($env['AUTH_MODE'] ?? self::AUTH_MODE_CLI)));
        if (!in_array($mode, [self::AUTH_MODE_CLI, self::AUTH_MODE_OAUTH, self::AUTH_MODE_DEVICE], true)) {
            $mode = self::AUTH_MODE_CLI;
        }

        return new self(
            organization: (string)($env['ORGANIZATION'] ?? ''),
            project: (string)($env['PROJECT_DEFAULT'] ?? ''),
            tenantId: (string)($env['TENANT_ID'] ?? 'organizations'),
            clientId: (string)($env['CLIENT_ID'] ?? ''),
            authMode: $mode,
            redirectUri: (string)($env['REDIRECT_URI'] ?? ''),
            appKey: (string)($env['APP_KEY'] ?? ''),
            httpTimeout: max(1, (int)($env['HTTP_TIMEOUT'] ?? 30)),
            httpRetries: max(0, (int)($env['HTTP_RETRIES'] ?? 3)),
        );
    }

    public function baseUrl(): string
    {
        return 'https://dev.azure.com/' . rawurlencode($this->organization);
    }

    public function authorityUrl(): string
    {
        return 'https://login.microsoftonline.com/' . rawurlencode($this->tenantId);
    }

    /** Escopo delegado do Azure DevOps (v2.0 endpoint). */
    public function scope(): string
    {
        return self::AZURE_DEVOPS_RESOURCE . '/.default openid profile offline_access';
    }

    public function effectiveClientId(): string
    {
        if ($this->authMode === self::AUTH_MODE_DEVICE) {
            return self::DEVICE_CLIENT_ID;
        }

        return $this->clientId;
    }

    /** Modos em que o login é feito pelo navegador (sem terminal), senao tem que instalar o az cli. */
    public function isBrowserAuth(): bool
    {
        return in_array($this->authMode, [self::AUTH_MODE_OAUTH, self::AUTH_MODE_DEVICE], true);
    }
}
