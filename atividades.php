<?php

/**
 * Rotina principal: consulta atividades lançadas no Azure DevOps SEM PAT.
 *
 * Fluxo: Entra ID → Access Token → WIQL → Work Items → JSON.
 *
 * Parâmetros:
 *   ?mes=jul2026          período (padrão: mês atual)
 *   ?projeto=NOME         projeto (padrão: PROJECT_DEFAULT do .env)
 *   ?formato=html         tabela HTML em vez de JSON
 */

require __DIR__ . '/functions.php';

use App\Azure\ApiException;
use App\Azure\AuthException;
use App\Azure\Services;

$mesAno  = (string)($_GET['mes'] ?? strtolower(array_search(date('m'), meses(), true) . date('Y')));
$projeto = isset($_GET['projeto']) ? (string)$_GET['projeto'] : null;
$formato = (string)($_GET['formato'] ?? 'json');

$periodo = intervalo($mesAno);

try {
    $atividades = Services::devOps()->getActivities($periodo['qini'], $periodo['qfim'], $projeto);
} catch (AuthException $e) {
    _exigir_login($e->getMessage());
} catch (ApiException $e) {
    if ($e->isUnauthorized()) {
        _exigir_login($e->getMessage());
    }
    http_response_code($e->statusCode >= 400 ? $e->statusCode : 502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['erro' => $e->getMessage(), 'status' => $e->statusCode], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($formato === 'html') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Atividades ' . htmlspecialchars($mesAno) . ' (' . count($atividades) . ')</h1>';
    echo '<table border="1" cellpadding="6" cellspacing="0">';
    echo '<tr><th>ID</th><th>Título</th><th>Responsável</th><th>Estado</th><th>Criado</th><th>Alterado</th><th>Iteração</th><th>Área</th><th>Horas</th></tr>';
    foreach ($atividades as $a) {
        printf(
            '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%.2f</td></tr>',
            $a['id'],
            htmlspecialchars($a['title']),
            htmlspecialchars($a['assignedTo']),
            htmlspecialchars($a['state']),
            $a['createdDate'] !== '' ? date('d/m/Y H:i', strtotime($a['createdDate'])) : '-',
            $a['changedDate'] !== '' ? date('d/m/Y H:i', strtotime($a['changedDate'])) : '-',
            htmlspecialchars($a['iterationPath']),
            htmlspecialchars($a['areaPath']),
            $a['completedWork']
        );
    }
    echo '</table>';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'periodo'    => ['inicio' => $periodo['qini'], 'fim' => $periodo['qfim']],
    'projeto'    => $projeto ?? Services::config()->project,
    'total'      => count($atividades),
    'atividades' => $atividades,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
