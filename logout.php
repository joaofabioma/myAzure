<?php

/**
 * Limpa o token da sessão/memória. Não desloga do Entra ID no navegador
 * (para isso, acesse https://login.microsoftonline.com/common/oauth2/v2.0/logout).
 */

require __DIR__ . '/inc/prepend.php';

App\Azure\Services::auth()->logout();
header('Location: /', true, 302);
