<?php

declare(strict_types=1);

namespace App\Azure;

/**
 * Armazenamento seguro do token.
 *
 * - O access token em claro só existe em MEMÓRIA (propriedade estática),
 *   nunca é gravado em disco em texto puro.
 * - Para sobreviver entre requisições (fluxo OAuth web) o conjunto
 *   access/refresh token é CRIPTOGRAFADO (libsodium XChaCha20-Poly1305,
 *   com fallback AES-256-GCM) antes de ir para a sessão PHP.
 * - No modo Azure CLI nada é persistido: o token vive apenas na requisição.
 */
final class TokenStore
{
    private const SESSION_KEY = 'ado_token_blob';
    private const FILE_NAME   = '.auth_blob';

    /** @var array{access_token:string,refresh_token:string,expires_at:int}|null */
    private static ?array $memory = null;

    public function __construct(
        private readonly string $appKey,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @return array{access_token:string,refresh_token:string,expires_at:int}|null
     */
    public function get(): ?array
    {
        if (self::$memory !== null) {
            return self::$memory;
        }

        $this->ensureSession();
        $blob = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($blob) || $blob === '') {
            $blob = $this->readFileBlob();
        }
        if (!is_string($blob) || $blob === '') {
            return null;
        }

        $json = $this->decrypt($blob);
        if ($json === null) {
            $this->logger->warning('TokenStore: blob de sessão inválido, descartando.');
            unset($_SESSION[self::SESSION_KEY]);
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['access_token'], $data['expires_at'])) {
            return null;
        }

        self::$memory = [
            'access_token'  => (string)$data['access_token'],
            'refresh_token' => (string)($data['refresh_token'] ?? ''),
            'expires_at'    => (int)$data['expires_at'],
        ];
        return self::$memory;
    }

    public function put(string $accessToken, string $refreshToken, int $expiresAt): void
    {
        self::$memory = [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => $expiresAt,
        ];

        $this->ensureSession();
        $encrypted = $this->encrypt(json_encode(self::$memory, JSON_THROW_ON_ERROR));
        $_SESSION[self::SESSION_KEY] = $encrypted;
        $this->writeFileBlob($encrypted);
    }

    /** Mantém o token apenas em memória (modo Azure CLI — nada vai para a sessão). */
    public function putMemoryOnly(string $accessToken, int $expiresAt): void
    {
        self::$memory = [
            'access_token'  => $accessToken,
            'refresh_token' => '',
            'expires_at'    => $expiresAt,
        ];
    }

    public function clear(): void
    {
        self::$memory = null;
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
        $this->deleteFileBlob();
    }

    private function ensureSession(): void
    {
        if (php_sapi_name() === 'cli') {
            $_SESSION ??= [];
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }
    }

    private function deriveKey(): string
    {
        if ($this->appKey === '') {
            throw new AuthException('APP_KEY não definida no .env — necessária para criptografar o token na sessão.');
        }
        return hash('sha256', $this->appKey, true); // 32 bytes
    }

    private function encrypt(string $plain): string
    {
        $key = $this->deriveKey();

        if (function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt')) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, '', $nonce, $key);
            return 's1:' . base64_encode($nonce . $cipher);
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'o1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $blob): ?string
    {
        $key = $this->deriveKey();
        [$ver, $b64] = array_pad(explode(':', $blob, 2), 2, '');
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return null;
        }

        if ($ver === 's1' && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if (strlen($raw) <= $nlen) {
                return null;
            }
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($raw, $nlen), '', substr($raw, 0, $nlen), $key
            );
            return $plain === false ? null : $plain;
        }

        if ($ver === 'o1' && strlen($raw) > 28) {
            $plain = openssl_decrypt(
                substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA,
                substr($raw, 0, 12), substr($raw, 12, 16)
            );
            return $plain === false ? null : $plain;
        }

        return null;
    }

    private function filePath(): string
    {
        return dirname(__DIR__, 2) . '/data/' . self::FILE_NAME;
    }

    private function readFileBlob(): ?string
    {
        $path = $this->filePath();
        if (!is_file($path)) {
            return null;
        }
        $blob = file_get_contents($path);
        return is_string($blob) && $blob !== '' ? $blob : null;
    }

    private function writeFileBlob(string $blob): void
    {
        $path = $this->filePath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $blob, LOCK_EX);
    }

    private function deleteFileBlob(): void
    {
        $path = $this->filePath();
        if (is_file($path)) {
            unlink($path);
        }
    }
}
