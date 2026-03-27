<?php
require __DIR__ . '/functions.php';

// arquivos disponíveis
function obterArquivosMesAno()
{
    $arquivos = [];
    $diretorio = __DIR__ . '/data/';
    $meses = meses();

    if (!is_dir($diretorio)) return [];
    $items = scandir($diretorio);

    foreach ($items as $item) {
        $tipo = '.json';
        $tipolen = strlen($tipo);

        if (substr($item, -$tipolen) === $tipo) {
            $nome = substr($item, 0, -$tipolen);
            if (strlen($nome) === 7) {
                $mes = strtolower(substr($nome, 0, 3));
                $ano = substr($nome, 3, 4);
                if (isset($meses[$mes]) && is_numeric($ano)) {
                    $arquivos[] = [
                        'arquivo' => $item,
                        'id' => $nome,
                        'mesExtenso' => ucfirst($mes) . " " . $ano,
                        'timestamp' => strtotime("$ano-{$meses[$mes]}-01")
                    ];
                }
            }
        }
    }

    usort($arquivos, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
    return $arquivos;
}

/** ID do arquivo do mês civil anterior (ex.: mar2025 → fev2025). */
function idMesAnterior(?string $qual): ?string
{
    if ($qual === null || strlen($qual) !== 7) {
        return null;
    }
    $mesStr = strtolower(substr($qual, 0, 3));
    $ano = substr($qual, 3, 4);
    $meses = meses();
    if (!isset($meses[$mesStr]) || !is_numeric($ano)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $ano . '-' . $meses[$mesStr] . '-01');
    if (!$dt) {
        return null;
    }
    $prev = $dt->modify('-1 month');
    $flip = array_flip($meses);
    $keyMes = $flip[$prev->format('m')] ?? null;
    return $keyMes !== null ? $keyMes . $prev->format('Y') : null;
}

/** Soma CompletedWork no intervalo do mês, com o mesmo filtro do dashboard (e-mail e prazo). */
function totalHorasMesFiltrado(string $idMes, $umail): ?float
{
    $path = __DIR__ . "/data/{$idMes}.json";
    if (!is_file($path)) {
        return null;
    }
    $allDataTasks = json_decode(file_get_contents($path), true) ?: [];
    $intervalo = intervalo($idMes);
    $dataIni = strtotime($intervalo['ini']);
    $dataFim = strtotime($intervalo['fim']);

    $filtered = array_filter($allDataTasks, function ($task) use ($dataIni, $dataFim, $umail) {
        if ($umail && isset($task['AssignedTo']['UserEmail']) && $task['AssignedTo']['UserEmail'] !== $umail) {
            return false;
        }
        $cpDate = $task['Custom_Prazoss'] ?? null;
        if (empty($cpDate)) {
            return false;
        }
        $cpDt = strtotime($cpDate);
        return $cpDt !== false && $cpDt >= $dataIni && $cpDt <= $dataFim;
    });

    $total = 0.0;
    foreach ($filtered as $task) {
        $total += floatval($task['CompletedWork'] ?? 0);
    }
    return $total;
}

$arquivosDisponiveis = obterArquivosMesAno();
$qual = $_GET['mes'] ?? ($arquivosDisponiveis[0]['id'] ?? null);
$umail = me('UserEmail');

$dados = [];
$audit = [
    'blindLogs' => [], // Completed == Original
    'zeroHours' => [],
    'noDeadline' => [],
    'open' => [],
];


if ($qual && file_exists(__DIR__ . "/data/$qual.json")) {
    $json = file_get_contents(__DIR__ . "/data/$qual.json");
    $allDataTasks = json_decode($json, true) ?: [];
    $json = null;  //=>nil  =)
    $intervalo = intervalo($qual);
    $dataIni = strtotime($intervalo['ini']);
    $dataFim   = strtotime($intervalo['fim']);

    foreach ($allDataTasks as $task) {
        if (empty($task['Custom_Prazoss'] ?? null)) {
            $audit['noDeadline'][] = $task;
        }
    }

    // reorganiza dados filtrados
    $allDataTasks = array_values(array_filter($allDataTasks, function ($task) use ($dataIni, $dataFim, $umail) {
        if ($umail && isset($task['AssignedTo']['UserEmail']) && $task['AssignedTo']['UserEmail'] !== $umail) {
            return false;
        }
        $cpDate = $task['Custom_Prazoss'] ?? null;
        if (empty($cpDate)) {
            return false;
        }
        $cpDt = strtotime($cpDate);
        return $cpDt !== false && $cpDt >= $dataIni && $cpDt <= $dataFim;
    }));

    // filtradas
    foreach ($allDataTasks as $task) {
        $completed = floatval($task['CompletedWork'] ?? 0);
        $estimado  = floatval($task['OriginalEstimate'] ?? 0);
        $state     = $task['State'] ?? '';

        if ($completed == $estimado && $completed > 0) {
            $audit['blindLogs'][] = $task;
        }

        if ($state === 'Fechado') {
            if ($completed == 0) {
                $audit['zeroHours'][] = $task;
            }
        } else {
            $audit['open'][] = $task;
        }

        // var_dump($audit);die;
        $dados[] = $task;
    }
}

// Agrupamento para gráficos
$produtividadeDiaria = [];
$atividadesCount = [];
$projetosHours = [];
$projetosEstimativa = [];

foreach ($dados as $task) {
    // Produtividade
    $data = !empty($task['Custom_Prazoss']) ? date('Y-m-d', strtotime($task['Custom_Prazoss'])) : null;
    if ($data) {
        $produtividadeDiaria[$data] = ($produtividadeDiaria[$data] ?? 0) + floatval($task['CompletedWork'] ?? 0);
    }

    // Atividades
    $ativ = $task['Activity'] ?? 'Outros';
    $atividadesCount[$ativ] = ($atividadesCount[$ativ] ?? 0) + 1;

    // Projetos — horas realizadas e estimadas
    $proj = $task['Project']['ProjectName'] ?? 'Desconhecido';
    $projetosHours[$proj]      = ($projetosHours[$proj] ?? 0)      + floatval($task['CompletedWork'] ?? 0);
    $projetosEstimativa[$proj] = ($projetosEstimativa[$proj] ?? 0) + floatval($task['OriginalEstimate'] ?? 0);
}

ksort($produtividadeDiaria);

$totalHorasAtual = array_sum($produtividadeDiaria);
$comparacaoHorasMesAnterior = ['html' => '', 'color' => '#7f8c8d'];
if ($qual) {
    $idAnt = idMesAnterior($qual);
    $horasAnt = ($idAnt !== null) ? totalHorasMesFiltrado($idAnt, $umail) : null;
    if ($horasAnt === null) {
        $comparacaoHorasMesAnterior['html'] = 'Sem dados do mês anterior';
    } elseif ($horasAnt <= 0 && $totalHorasAtual <= 0) {
        $comparacaoHorasMesAnterior['html'] = 'Igual ao mês anterior (0h)';
    } elseif ($horasAnt <= 0 && $totalHorasAtual > 0) {
        $comparacaoHorasMesAnterior['html'] = 'Mês anterior sem horas (não dá para calcular %)';
    } else {
        $pct = (($totalHorasAtual - $horasAnt) / $horasAnt) * 100;
        if (abs($pct) < 0.05) {
            $comparacaoHorasMesAnterior['html'] = '≈ igual ao mês anterior';
        } elseif ($pct > 0) {
            $comparacaoHorasMesAnterior['html'] = '↑ ' . number_format($pct, 1, ',', '') . '% vs mês anterior';
            $comparacaoHorasMesAnterior['color'] = 'var(--accent)';
        } else {
            $comparacaoHorasMesAnterior['html'] = '↓ ' . number_format(abs($pct), 1, ',', '') . '% vs mês anterior';
            $comparacaoHorasMesAnterior['color'] = 'var(--danger)';
        }
    }
} else {
    $comparacaoHorasMesAnterior['html'] = '—';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Azure DevOps</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="icon" href="assets/Microsoft_Azure.svg" type="image/svg+xml">
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <script src="assets/js/chart.js"></script>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --accent: #2ecc71;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --glass: rgba(255, 255, 255, 0.85);
        }

        body {
            background: #f0f2f5;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            width: 100%;
            box-sizing: border-box;
            background: var(--secondary);
            color: white;
            padding: 0 1.2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .topbar-nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }

        .topbar-status {
            font-size: 0.8rem;
            color: #7f8c8d;
        }

        .topbar-status .online {
            color: var(--accent);
        }

        .logo {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-link {
            padding: 0.4rem 0.7rem;
            margin: 0.2rem 0.5rem;
            border-radius: 8px;
            color: #bdc3c7;
            text-decoration: none;
            transition: 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .main-content {
            flex: 1;
            width: 100%;
            box-sizing: border-box;
            padding: 2rem;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.2rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--secondary);
            margin: 0.5rem 0;
        }

        .charts-grid {
            display: grid;
            /* grid-template-columns: 1fr 1fr; */
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .charts-grid-parts {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .chart-wrapper {
            position: relative;
            height: 260px;
        }

        .audit-section {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .audit-tab {
            display: flex;
            gap: 10px;
            margin-bottom: 1rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
            font-weight: 600;
            color: #7f8c8d;
            border-radius: 6px;
        }

        .tab-btn.active {
            background: var(--primary);
            color: white;
        }

        .audit-section table {
            margin: 0.5rem 0 0;
            box-shadow: none;
            border: 1px solid #eee;
        }

        .audit-section table:hover {
            transform: none;
            box-shadow: none;
        }

        .audit-section tr:hover td {
            transform: none;
        }

        .badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: bold;
        }

        .badge-danger {
            background: #fadbd8;
            color: #e74c3c;
        }

        .badge-warning {
            background: #fef9e7;
            color: #f1c40f;
        }

        .form-select {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: white;
        }

        #auditBody tr td a {
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="logo">
            <img src="assets/Microsoft_Azure.svg" width="32" alt="Azure" style="height: 1em;">
            <span> Dashboard</span>
        </div>
        <nav class="topbar-nav" aria-label="Navegação principal">
            <a href="index.php" class="nav-link">Dashboard Geral</a>
            <a href="dashboard.php" class="nav-link active">Análise Mensal</a>
        </nav>
        <p class="topbar-status">Status: <span class="online">Online</span></p>
    </header>

    <div class="main-content">
        <div class="header">
            <div>
                <h1 style="margin: 0; font-size: 1.5rem;">Performance</h1>
                <p style="margin: 0; color: #7f8c8d;">Detalhes das tasks, horas, projetos e atividades</p>
            </div>
            <form method="GET" id="monthForm">
                <select name="mes" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($arquivosDisponiveis as $arq): ?>
                        <option value="<?= $arq['id'] ?>" <?= $qual == $arq['id'] ? 'selected' : '' ?>>
                            <?= $arq['mesExtenso'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-left: 0.21rem solid #2fdf55cf;">
                <span class="stat-label">Total de Horas</span>
                <span class="stat-value" data-count-to="<?= htmlspecialchars((string)array_sum($produtividadeDiaria), ENT_QUOTES, 'UTF-8') ?>" data-decimals="2" data-suffix="h"><?= number_format(0, 2) ?>h</span>
                <span style="font-size: 0.8rem; color: <?= htmlspecialchars($comparacaoHorasMesAnterior['color'], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars($comparacaoHorasMesAnterior['html'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="stat-card" style="border-left: 0.21rem solid #306ae6cf;">
                <span class="stat-label">Tasks Concluídas</span>
                <span class="stat-value" data-count-to="<?= count($dados) ?>" data-decimals="0">0</span>
                <span style="font-size: 0.8rem; color: #7f8c8d;">Média de <?= number_format(count($dados) / (count($produtividadeDiaria) ?: 1), 1) ?> por dia</span>
            </div>
            <div class="stat-card" style="border-left: 0.21rem solid #e63030cf;">
                <span class="stat-label">Inconsistências</span>
                <span class="stat-value" style="color: var(--danger);" data-count-to="<?= count($audit['zeroHours']) ?>" data-decimals="0">0</span>
                <span style="font-size: 0.8rem; color: var(--danger);">
                    <?php if (count($audit['zeroHours']) > 0): ?>Sem horas: <?= count($audit['zeroHours']) ?><?php endif; ?>
                    <?php if (count($audit['zeroHours']) === 0): ?>Nenhuma inconsistência encontrada<?php endif; ?>
                </span>
            </div>
            <div class="stat-card" style="border-left: 0.21rem solid #e630cecf;">
                <span class="stat-label">Diversidade de Projetos</span>
                <span class="stat-value" data-count-to="<?= count($projetosHours) ?>" data-decimals="0">0</span>
                <span style="font-size: 0.8rem; color: #7f8c8d;">Foco principal: <?= !empty($projetosHours) ? (string)array_search(max($projetosHours), $projetosHours) : '-' ?></span>
            </div>
            <div class="stat-card" style="border-left: 0.21rem solid #e9d700cf;">
                <span class="stat-label">Não fechadas</span>
                <span class="stat-value" data-count-to="<?= count($audit['open']) ?>" data-decimals="0">0</span>
                <span style="font-size: 0.8rem; color: #7f8c8d;">Tarefas a fechar</span>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-container">
                <h3 style="margin-top: 0;">Produtividade Diária (Horas)</h3>
                <div class="chart-wrapper">
                    <canvas id="productivityChart"></canvas>
                </div>
            </div>
        </div>

        <div class="charts-grid-parts">
            <div class="chart-container">
                <h3 style="margin-top: 0;">Estimativa vs. Realizado por Projeto</h3>
                <div class="chart-wrapper">
                    <canvas id="estimativaChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3 style="margin-top: 0;">Distribuição por Projeto</h3>
                <div class="chart-wrapper">
                    <canvas id="projectChart"></canvas>
                </div>
            </div>

            <div class="chart-container">
                <h3 style="margin-top: 0;">Radar de Atividades</h3>
                <div class="chart-wrapper">
                    <canvas id="atividadesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="audit-section">
            <h3 style="margin-top: 0;">Auditoria Tasks</h3>
            <div class="audit-tab">
                <button class="tab-btn" onclick="showAudit('zeroHours', this)">
                    ⚠ Sem Horas (<?= count($audit['zeroHours']) ?>)
                </button>
                <button class="tab-btn" onclick="showAudit('blindLogs', this)">
                    ℹ Horas = Estimativa (<?= count($audit['blindLogs']) ?>)
                </button>
                <button class="tab-btn" onclick="showAudit('noDeadline', this)">
                    ℹ Sem Prazo (<?= count($audit['noDeadline']) ?>)
                </button>
                <button class="tab-btn active" onclick="showAudit('open', this)">
                    ⚠ Tarefas abertas (<?= count($audit['open']) ?>)
                </button>
            </div>

            <div id="auditContent">
                <table id="auditTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Atividade</th>
                            <th>Projeto</th>
                            <th>Horas</th>
                            <th>Est.</th>
                        </tr>
                    </thead>
                    <tbody id="auditBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const auditData = <?= json_encode($audit) ?>;
        const uacesso = <?= json_encode(UACESS, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

        (function animateStatValues() {
            var durationMs = 2500;
            function formatLikePhpNumberFormat(num, decimals) {
                var fixed = Number(num).toFixed(decimals);
                var parts = fixed.split('.');
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                return parts.length > 1 ? parts[0] + '.' + parts[1] : parts[0];
            }
            var nodes = document.querySelectorAll('.stats-grid .stat-value[data-count-to]');
            if (!nodes.length) return;
            var startTs = null;
            function frame(ts) {
                if (startTs === null) startTs = ts;
                var t = Math.min(1, (ts - startTs) / durationMs);
                for (var i = 0; i < nodes.length; i++) {
                    var el = nodes[i];
                    var end = parseFloat(el.getAttribute('data-count-to'), 10);
                    if (isNaN(end)) end = 0;
                    var decimals = parseInt(el.getAttribute('data-decimals') || '0', 10) || 0;
                    var suffix = el.getAttribute('data-suffix') || '';
                    var current = end * t;
                    if (decimals === 0) current = Math.round(current);
                    el.textContent = formatLikePhpNumberFormat(current, decimals) + suffix;
                }
                if (t < 1) requestAnimationFrame(frame);
            }
            requestAnimationFrame(frame);
        })();

        function showAudit(type, btn) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const body = document.getElementById('auditBody');
            body.innerHTML = '';

            auditData[type].forEach(task => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><a href="${uacesso.replace('%s', 'Loglab').replace('%s', task.Project?.ProjectName).replace('%s', task.WorkItemId)}">${task.WorkItemId}</a></td>
                    <td style="max-width: 400px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${task.Title}</td>
                    <td>${task?.Activity || '-'}</td>
                    <td>${task.Project?.ProjectName || '-'}</td>
                    <td><span class="badge ${task.CompletedWork > task.OriginalEstimate ? 'badge-danger' : 'badge-warning'}">${task.CompletedWork || 0}h</span></td>
                    <td>${task.OriginalEstimate || 0}h</td>
                `;
                body.appendChild(tr);
            });
        }

        // onde iniciar
        showAudit('blindLogs', document.querySelector('.tab-btn'));

        // Gráfico de Produtividade Diária (Progressive Line — chartjs.org/docs/latest/samples/animations/progressive-line.html)
        const productivityLabels = <?= json_encode(array_keys($produtividadeDiaria)) ?>;
        const productivityValues = <?= json_encode(array_values($produtividadeDiaria)) ?>;
        const productivityTotalDuration = 2500;
        const productivityDelayBetweenPoints = productivityTotalDuration / Math.max(1, productivityValues.length);
        const productivitySegmentDuration = productivityDelayBetweenPoints * 1.1;
        const productivityPreviousY = (ctx) => ctx.index === 0
            ? ctx.chart.scales.y.getPixelForValue(0)
            : ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.index - 1].getProps(['y'], true).y;
        const productivityLineAnimation = {
            x: {
                type: 'number',
                easing: 'easeInOutSine',
                duration: productivitySegmentDuration,
                from: NaN,
                delay(ctx) {
                    if (ctx.type !== 'data' || ctx.xStarted) {
                        return 0;
                    }
                    ctx.xStarted = true;
                    return ctx.index * productivityDelayBetweenPoints;
                }
            },
            y: {
                type: 'number',
                easing: 'easeInOutSine',
                duration: productivitySegmentDuration,
                from: productivityPreviousY,
                delay(ctx) {
                    if (ctx.type !== 'data' || ctx.yStarted) {
                        return 0;
                    }
                    ctx.yStarted = true;
                    return ctx.index * productivityDelayBetweenPoints;
                }
            }
        };

        new Chart(document.getElementById('productivityChart'), {
            type: 'line',
            data: {
                labels: productivityLabels,
                datasets: [{
                    label: 'Horas Lan\u00e7adas',
                    data: productivityValues,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                animation: productivityLineAnimation,
                interaction: {
                    intersect: false
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        }
                    },
                    x: {
                        grid: {
                            display: true
                        }
                    }
                }
            }
        });

        // Distribuição por Projeto
        new Chart(document.getElementById('projectChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($projetosHours)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($projetosHours)) ?>,
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#f1c40f', '#e74c3c', '#9b59b6', '#1abc9c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });

        // Estimativa vs Realizado por Projeto
        new Chart(document.getElementById('estimativaChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($projetosHours)) ?>,
                datasets: [{
                        label: 'Estimado (h)',
                        data: <?= json_encode(array_values(array_map(
                                    fn($proj) => round($projetosEstimativa[$proj] ?? 0, 2),
                                    array_keys($projetosHours)
                                ))) ?>,
                        backgroundColor: 'rgba(52, 152, 219, 0.6)',
                        borderColor: '#3498db',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Realizado (h)',
                        data: <?= json_encode(array_values(array_map('round', $projetosHours, array_fill(0, count($projetosHours), 2)))) ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.6)',
                        borderColor: '#2ecc71',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f0f0f0'
                        },
                        ticks: {
                            callback: v => v + 'h'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        //queria radar, ficou feio
        new Chart(document.getElementById('atividadesChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($atividadesCount)); ?>,
                datasets: [{
                    label: 'Horas por Atividade',
                    data: <?= json_encode(array_values($atividadesCount)) ?>,
                    // fill: true,
                    backgroundColor: ['#3498db', '#2ecc71', '#f1c40f', '#e74c3c', '#9b59b6', '#1abc9c']
                    // borderColor: 'rgba(54, 162, 235, 1)',
                    // pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    // pointBorderColor: '#fff',
                    // pointHoverBackgroundColor: '#fff',
                    // pointHoverBorderColor: 'rgba(54, 162, 235, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 15
                        }
                    }
                }
            }
        });
    </script>
    <?php
    // var_dump($atividadesCount); 
    ?>
</body>

</html>