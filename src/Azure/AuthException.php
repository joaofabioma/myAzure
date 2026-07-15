<?php

declare(strict_types=1);

namespace App\Azure;

/** Falha de autenticação/obtenção de token no Entra ID. */
class AuthException extends \RuntimeException
{
}
