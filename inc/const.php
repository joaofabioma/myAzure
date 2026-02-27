<?php
// inc/const.php
const VERSION = '1.0.3'; // 2026-02-27 - toda alteracao, incrementar 0.0.1
const DATE = '2026-02-27'; // 2026-02-27 - toda alteracao, alterar data

if (php_sapi_name() !== 'cli') {
    $versionFile = __DIR__ . '/../VERSION';
    $fileVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0.0.0';

    if (VERSION !== $fileVersion) {
        http_response_code(503);
        header('Content-Type: text/plain');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        exit("Versão incompatível: Versão do sistema (v" . VERSION . "), Versão detectada (v" . $fileVersion . "). Aplicação encerrada.");
    }

    unset($versionFile, $fileVersion);
}
