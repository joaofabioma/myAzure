<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Autenticação no Microsoft Entra ID para consumo do Azure DevOps.
 *
 * Dois modos, ambos SEM PAT e sem client secret:
 *
 *  - AUTH_MODE=cli   → token obtido via Azure CLI (`az account get-access-token`).
 *                      Ideal para uso local/dev. Token vive só em memória.
 *  - AUTH_MODE=oauth → OAuth 2.0 Authorization Code + PKCE (recomendação MSAL
 *                      para public clients). Access/refresh token criptografados
 *                      na sessão; renovação automática via refresh token.
 *  - AUTH_MODE=device → Device Code Flow no navegador (client ID público da
 *                      Microsoft). Ideal para Docker sem App Registration no Portal.
 */
final class AuthService
{
    private const SESSION_PKCE   = 'ado_pkce_verifier';
    private const SESSION_STATE  = 'ado_oauth_state';
    private const SESSION_DEVICE = 'ado_device_flow';

    /** Margem de segurança antes da expiração real do token (segundos). */
    private const EXPIRY_MARGIN = 120;

    public function __construct(
        private readonly Config $config,
        private readonly TokenStore $store,
        private readonly Logger $logger,
    ) {
    }

    // ------------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------------

    /**
     * Inicia o login.
     * - Modo oauth: redireciona o navegador para o Entra ID (authorize endpoint).
     * - Modo cli:   apenas valida que a Azure CLI consegue emitir token.
     */
    public function login(): void
    {
        if ($this->config->authMode === Config::AUTH_MODE_CLI) {
            $this->getAccessToken();
            return;
        }

        if ($this->config->authMode === Config::AUTH_MODE_DEVICE) {
            return;
        }

        $this->ensureTenantConfigured();

        $verifier  = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state     = bin2hex(random_bytes(16));

        $this->ensureSession();
        $_SESSION[self::SESSION_PKCE]  = $verifier;
        $_SESSION[self::SESSION_STATE] = $state;

        $url = $this->config->authorityUrl() . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id'             => $this->config->clientId,
            'response_type'         => 'code',
            'redirect_uri'          => $this->config->redirectUri,
            'response_mode'         => 'query',
            'scope'                 => $this->config->scope(),
            'state'                 => $state,
            'code_challenge'        => $challenge,
            'code_challenge_method' => 'S256',
        ]);

        $this->logger->info('AuthService: redirecionando para o Entra ID.', ['tenant' => $this->config->tenantId]);
        header('Location: ' . $url, true, 302);
        exit;
    }

    /**
     * Troca o authorization code recebido no redirect por access/refresh token.
     */
    public function handleCallback(string $code, string $state): void
    {
        $this->ensureTenantConfigured();
        $this->ensureSession();
        $expectedState = (string)($_SESSION[self::SESSION_STATE] ?? '');
        $verifier      = (string)($_SESSION[self::SESSION_PKCE] ?? '');
        unset($_SESSION[self::SESSION_STATE], $_SESSION[self::SESSION_PKCE]);

        if ($expectedState === '' || !hash_equals($expectedState, $state)) {
            throw new AuthException('State inválido no callback OAuth (possível CSRF).');
        }
        if ($verifier === '') {
            throw new AuthException('PKCE verifier ausente — reinicie o login.');
        }

        $this->requestToken([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->config->redirectUri,
            'code_verifier' => $verifier,
        ]);

        $this->logger->info('AuthService: login OAuth concluído com sucesso.');
    }

    /**
     * Retorna um access token válido, renovando automaticamente se necessário.
     */
    public function getAccessToken(): string
    {
        $token = $this->store->get();

        if ($token !== null && $token['expires_at'] > time() + self::EXPIRY_MARGIN) {
            return $token['access_token'];
        }

        if ($this->config->authMode === Config::AUTH_MODE_CLI) {
            return $this->acquireViaAzureCli();
        }

        if ($token !== null && $token['refresh_token'] !== '') {
            return $this->refreshToken();
        }

        throw new AuthException('Sem token válido — é necessário efetuar login (/login.php).');
    }

    /**
     * Inicia o Device Code Flow (modo device). Retorna código e URL para o usuário.
     *
     * @return array{user_code:string,verification_uri:string,expires_in:int}
     */
    public function startDeviceCodeLogin(): array
    {
        if ($this->config->authMode !== Config::AUTH_MODE_DEVICE) {
            throw new AuthException('Device Code Flow disponível apenas com AUTH_MODE=device.');
        }

        $this->ensureTenantConfigured();
        $this->ensureSession();

        $curl = curl_init($this->config->authorityUrl() . '/oauth2/v2.0/devicecode');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'client_id' => $this->config->effectiveClientId(),
                'scope'     => $this->config->scope(),
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => $this->config->httpTimeout,
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($curl);
        $curl = null;

        if ($body === false) {
            throw new AuthException('Falha de rede ao iniciar login: ' . $curlErr);
        }

        $data = json_decode((string)$body, true);
        if ($status !== 200 || !is_array($data) || empty($data['device_code'])) {
            $err = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'resposta inválida') : 'resposta inválida';
            throw new AuthException('Entra ID recusou o Device Code Flow: ' . $err);
        }

        $_SESSION[self::SESSION_DEVICE] = [
            'device_code' => (string)$data['device_code'],
            'interval'    => max(5, (int)($data['interval'] ?? 5)),
            'expires_at'  => time() + (int)($data['expires_in'] ?? 900),
        ];

        $this->logger->info('AuthService: Device Code Flow iniciado.');

        return [
            'user_code'         => (string)$data['user_code'],
            'verification_uri'  => (string)$data['verification_uri'],
            'expires_in'        => (int)$data['expires_in'],
        ];
    }

    /**
     * Tenta concluir o Device Code Flow. Retorna false enquanto o usuário não autenticar.
     */
    public function pollDeviceCodeLogin(): string|false
    {
        $this->ensureSession();
        $flow = $_SESSION[self::SESSION_DEVICE] ?? null;
        if (!is_array($flow) || ($flow['device_code'] ?? '') === '') {
            throw new AuthException('Fluxo de login não iniciado — recarregue a página.');
        }
        if ((int)($flow['expires_at'] ?? 0) < time()) {
            unset($_SESSION[self::SESSION_DEVICE]);
            throw new AuthException('Código expirado — recarregue a página para gerar outro.');
        }

        $result = $this->requestToken([
            'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
            'device_code' => (string)$flow['device_code'],
        ], pendingOk: true);

        if ($result === false) {
            return false;
        }

        unset($_SESSION[self::SESSION_DEVICE]);
        $this->logger->info('AuthService: login Device Code concluído com sucesso.');

        return $result;
    }

    /**
     * Renova o access token usando o refresh token (modo oauth).
     */
    public function refreshToken(): string
    {
        $token = $this->store->get();
        if ($token === null || $token['refresh_token'] === '') {
            throw new AuthException('Refresh token indisponível — efetue login novamente.');
        }

        $this->logger->info('AuthService: renovando access token via refresh token.');
        return $this->requestToken([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $token['refresh_token'],
        ]);
    }

    public function logout(): void
    {
        $this->store->clear();
        $this->logger->info('AuthService: sessão de token limpa (logout).');
    }

    public function isAuthenticated(): bool
    {
        try {
            $this->getAccessToken();
            return true;
        } catch (AuthException) {
            return false;
        }
    }

    // ------------------------------------------------------------------
    // Internos
    // ------------------------------------------------------------------

    /**
     * Obtém token pela Azure CLI: `az account get-access-token`.
     * A CLI cuida do login (az login), cache e renovação — nada é
     * persistido pela aplicação.
     */
    private function acquireViaAzureCli(): string
    {
        $prefix = '';
        $configDir = getenv('AZURE_CONFIG_DIR');
        if (is_string($configDir) && $configDir !== '') {
            $prefix = 'AZURE_CONFIG_DIR=' . escapeshellarg($configDir) . ' ';
        }

        $cmd = $prefix . 'az account get-access-token --resource ' . Config::AZURE_DEVOPS_RESOURCE . ' --output json 2>&1';
        $out = shell_exec($cmd);

        $data = is_string($out) ? json_decode(trim($out), true) : null;
        if (!is_array($data) || empty($data['accessToken'])) {
            $this->logger->error('AuthService: falha ao obter token via Azure CLI.', [
                'hint' => 'Execute "az login" no servidor/estação e verifique se a Azure CLI está no PATH do usuário do PHP.',
            ]);
            throw new AuthException(
                'Não foi possível obter token via Azure CLI. Execute "az login" e tente novamente.'
            );
        }

        $expiresAt = isset($data['expiresOn'])
            ? (strtotime((string)$data['expiresOn']) ?: time() + 3000)
            : time() + 3000;

        $this->store->putMemoryOnly((string)$data['accessToken'], $expiresAt);
        $this->logger->info('AuthService: token obtido via Azure CLI.', [
            'expires_at' => date('c', $expiresAt),
        ]);

        return (string)$data['accessToken'];
    }

    /**
     * Chama o token endpoint v2.0 do Entra ID e armazena o resultado.
     *
     * @return string|false Token de acesso, ou false se authorization_pending (Device Code).
     */
    private function requestToken(array $grantParams, bool $pendingOk = false): string|false
    {
        $this->ensureTenantConfigured();

        $params = $grantParams + [
            'client_id' => $this->config->effectiveClientId(),
            'scope'     => $this->config->scope(),
        ];

        $curl = curl_init($this->config->authorityUrl() . '/oauth2/v2.0/token');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => $this->config->httpTimeout,
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlErr = curl_error($curl);
        $curl = null;

        if ($body === false) {
            throw new AuthException('Falha de rede ao contactar o Entra ID: ' . $curlErr);
        }

        $data = json_decode((string)$body, true);
        if ($status !== 200 || !is_array($data) || empty($data['access_token'])) {
            $errCode = is_array($data) ? (string)($data['error'] ?? '') : '';
            if ($pendingOk && in_array($errCode, ['authorization_pending', 'slow_down'], true)) {
                return false;
            }
            $err = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? 'resposta inválida') : 'resposta inválida';
            $this->logger->error('AuthService: token endpoint retornou erro.', [
                'status' => $status,
                'grant'  => (string)($grantParams['grant_type'] ?? ''),
                'error'  => $err,
            ]);
            throw new AuthException('Entra ID recusou a emissão do token: ' . $err);
        }

        $expiresAt = time() + (int)($data['expires_in'] ?? 3600);
        $this->store->put(
            (string)$data['access_token'],
            (string)($data['refresh_token'] ?? ''),
            $expiresAt
        );

        $this->logger->info('AuthService: token emitido pelo Entra ID.', [
            'grant'      => (string)($grantParams['grant_type'] ?? ''),
            'expires_at' => date('c', $expiresAt),
        ]);

        return (string)$data['access_token'];
    }

    private function ensureSession(): void
    {
        if (php_sapi_name() !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }
    }

    private function ensureTenantConfigured(): void
    {
        if (!$this->config->isTenantIdConfigured()) {
            throw new AuthException($this->config->tenantIdConfigHint());
        }
    }
}
