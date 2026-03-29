<?php
// inc/prepend.php
require __DIR__ . '/../vendor/autoload.php';

\App\Class\Security::validateRequestByFile(__FILE__);

require __DIR__ . '/const.php';

$env = parse_ini_file(__DIR__ . '/../.env') ?: [];
$env['PROJECTS'] =  array_map('trim', explode(',', $env['PROJECTS'])) ?? [];

!defined('ORG') && define('ORG', $env['ORGANIZATION']);
!defined('PAT') && define('PAT', $env['PERSONAL_ACESS_TOKEN']);
!defined('UTASK') && define('UTASK', $env['URL_TASKS']);
!defined('PROJ') && define('PROJ', $env['PROJECTS']);
!defined('PROJETO') && define('PROJETO', $env['PROJECT_DEFAULT']);
!defined('EDEV') && define('EDEV', $env['EMAIL_DEV']);
!defined('TIMER') && define('TIMER', $env['TEMPO_RECARREGAR_PAGINA_MINUTOS']);
!defined('ONLINE') && define('ONLINE',(bool) $env['REQUEST_ONLINE']);
!defined('DEBUG') && define('DEBUG', (bool) $env['APP_DEBUG']);
!defined('UACESS') && define('UACESS', $env['URL_ACCESS']); 
!defined('LISTTASKS') && define('LISTTASKS', $env['URL_LIST_TASKS']); 
