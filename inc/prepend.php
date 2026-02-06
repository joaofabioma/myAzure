<?php
// inc/prepend.php
if (basename($_SERVER["REQUEST_URI"]) == basename(__FILE__)) {
    header('Location: /', true, 301);
    exit();
}

$env = parse_ini_file(__DIR__ . '/../.env') ?: [];
$env['PROJECTS'] =  array_map('trim', explode(',', $env['PROJECTS'])) ?? [];

!defined('ORG') && define('ORG', $env['ORGANIZATION']);
!defined('PAT') && define('PAT', $env['PERSONAL_ACESS_TOKEN']);
!defined('UTASK') && define('UTASK', $env['URL_TASKS']);
!defined('PROJ') && define('PROJ', $env['PROJECTS']);
!defined('PROJETO') && define('PROJETO', $env['PROJECT_DEFAULT']);
!defined('EDEV') && define('EDEV', $env['EMAIL_DEV']);
!defined('TIMER') && define('TIMER', $env['TEMPO_RECARREGAR_PAGINA_MINUTOS']);
!defined('ONLINE') && define('ONLINE', TRUE);
!defined('DEBUG') && define('DEBUG', FALSE); // colocar no .env
