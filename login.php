<?php

/**
 * Inicia o login no Microsoft Entra ID.
 * - AUTH_MODE=device → Device Code Flow (navegador, sem App Registration)
 * - AUTH_MODE=oauth  → redireciona ao authorize endpoint
 * - AUTH_MODE=cli    → valida token via Azure CLI
 */

require __DIR__ . '/inc/prepend.php';

use App\Azure\AuthException;
use App\Azure\Config;
use App\Azure\Services;

$config = Services::config();
$auth   = Services::auth();

if ($config->authMode === Config::AUTH_MODE_DEVICE && isset($_GET['poll'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $token = $auth->pollDeviceCodeLogin();
        echo json_encode(['status' => $token !== false ? 'ok' : 'pending'], JSON_THROW_ON_ERROR);
    } catch (AuthException $e) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_THROW_ON_ERROR);
    }
    exit;
}

try {
    if ($auth->isAuthenticated()) {
        header('Location: /', true, 302);
        exit;
    }

    if ($config->authMode === Config::AUTH_MODE_OAUTH) {
        $auth->login();
    }

    if ($config->authMode === Config::AUTH_MODE_CLI) {
        $auth->login();
        header('Location: /', true, 302);
        exit;
    }

    if ($config->authMode === Config::AUTH_MODE_DEVICE) {
        $flow = $auth->startDeviceCodeLogin();
        renderDeviceLoginPage($flow);
        exit;
    }

    throw new AuthException('Modo de autenticação não suportado: ' . $config->authMode);
} catch (AuthException $e) {
    http_response_code(401);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Falha na autenticação</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    if ($config->authMode === Config::AUTH_MODE_CLI) {
        echo '<p>Execute <code>az login</code> no terminal e recarregue esta página.</p>';
    }
}

/** @param array{user_code:string,verification_uri:string,expires_in:int} $flow */
function renderDeviceLoginPage(array $flow): void
{
    $code = htmlspecialchars($flow['user_code'], ENT_QUOTES, 'UTF-8');
    $uri  = htmlspecialchars($flow['verification_uri'], ENT_QUOTES, 'UTF-8');
    $mins = max(1, (int)ceil($flow['expires_in'] / 60));
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Azure DevOps Monitor</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 520px; margin: 3rem auto; padding: 0 1rem; color: #1a1a1a; }
        h1 { font-size: 1.35rem; }
        .code { font-size: 2rem; letter-spacing: .25em; font-weight: 700; background: #f0f4ff; padding: 1rem; text-align: center; border-radius: 8px; margin: 1.5rem 0; }
        .btn { display: inline-block; background: #0078d4; color: #fff; text-decoration: none; padding: .75rem 1.25rem; border-radius: 6px; font-weight: 600; }
        .btn:hover { background: #106ebe; }
        .status { margin-top: 1.5rem; color: #555; }
        .error { color: #b00020; }
        ol { line-height: 1.7; }
    </style>
</head>
<body>
    <h1>Entrar com conta Microsoft</h1>
    <p>Use sua conta corporativa (mesma do Azure DevOps). Não é necessário App Registration no Portal.</p>
    <ol>
        <li>Abra <a href="<?= $uri ?>" target="_blank" rel="noopener">microsoft.com/devicelogin</a></li>
        <li>Digite o código abaixo quando solicitado:</li>
    </ol>
    <div class="code" id="user-code"><?= $code ?></div>
    <p><a class="btn" href="<?= $uri ?>" target="_blank" rel="noopener">Abrir página de login</a></p>
    <p class="status" id="status">Aguardando confirmação no navegador… (expira em ~<?= $mins ?> min)</p>
    <script>
        (function poll() {
            fetch('/login.php?poll=1', { credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'ok') {
                        document.getElementById('status').textContent = 'Login concluído! Redirecionando…';
                        window.location.href = '/';
                        return;
                    }
                    if (data.status === 'error') {
                        document.getElementById('status').innerHTML = '<span class="error">' + data.message + '</span>';
                        return;
                    }
                    setTimeout(poll, 5000);
                })
                .catch(() => setTimeout(poll, 8000));
        })();
    </script>
</body>
</html>
    <?php
}
