<?php

/**
 * Redirect URI do fluxo OAuth 2.0 Authorization Code + PKCE.
 * Deve estar registrada no App Registration do Entra ID
 * (ex.: http://localhost:8000/callback.php).
 */

require __DIR__ . '/inc/prepend.php';

use App\Azure\AuthException;
use App\Azure\Services;

header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['error'])) {
    http_response_code(401);
    $desc = (string)($_GET['error_description'] ?? $_GET['error']);
    echo '<h1>Login recusado pelo Entra ID</h1><p>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login.php">Tentar novamente</a></p>';
    exit;
}

$code  = (string)($_GET['code'] ?? '');
$state = (string)($_GET['state'] ?? '');

if ($code === '' || $state === '') {
    http_response_code(400);
    echo '<h1>Callback inválido</h1><p>Parâmetros <code>code</code>/<code>state</code> ausentes.</p>';
    exit;
}

try {
    Services::auth()->handleCallback($code, $state);
    header('Location: /', true, 302);
    exit;
} catch (AuthException $e) {
    http_response_code(401);
    echo '<h1>Falha ao concluir o login</h1><p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<p><a href="/login.php">Tentar novamente</a></p>';
}
