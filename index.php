<?php
require __DIR__ . '/functions.php';

$arquivosMesAno = obterArquivosMesAno();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horas por Dia - Azure DevOps</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="assets/css/animador_cards.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/Microsoft_Azure.svg" type="image/svg+xml">
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <script src="assets/js/chart.js"></script>
</head>

<body>
    <div class="container">
        <h2>Relatórios de Horas - Azure DevOps</h2>
        <?php if (count($arquivosMesAno) > 0): ?>
            <div class="section-title">
                📅 Selecione um Período
                <a href="gerarmesatual.php" class="section-action">🆕 Gerar mês atual</a>
            </div>
            <div class="box-container box-container--grid">
                <?php foreach ($arquivosMesAno as $info):
                    $href = htmlspecialchars($info['arquivo'], ENT_QUOTES, 'UTF-8');
                    $resumo = resumoEstatisticasMesRelatorio($info['arquivo']);
                    $horasFmt = number_format($resumo['horas'], 1, ',', ' ');
                ?>
                    <div class="box-item">
                        <div class="flip-box">
                            <div class="flip-box-front text-center">
                                <div class="inner">
                                    <div class="month-card-icon" aria-hidden="true">📊</div>
                                    <div class="month-card-title"><?= htmlspecialchars($info['mesExtenso']) ?></div>
                                    <div class="month-card-year"><?= htmlspecialchars($info['ano']) ?></div>
                                    <!-- <p class="month-flip-hint">Passe o mouse para ver o resumo</p> -->
                                    <a href="<?= $href ?>" class="month-flip-front-direct">Abrir direto</a>
                                </div>
                            </div>
                            <div class="flip-box-back text-center" aria-label="Resumo do período">
                                <div class="inner">
                                    <?php if ($resumo['temArquivo']): ?>
                                        <dl class="month-flip-stats">

                                            <div class="month-flip-stat">
                                                <dt>Tarefas</dt>
                                                <dd><?= (int) $resumo['tarefas'] ?></dd>
                                            </div>
                                            <div class="month-flip-stat">
                                                <dt>Horas registradas</dt>
                                                <dd><?= htmlspecialchars($horasFmt) ?></dd>
                                            </div>
                                            <div class="month-flip-stat">
                                                <dt>Em aberto</dt>
                                                <dd><?= (int) $resumo['abertas'] ?></dd>
                                            </div>
                                            <div class="month-flip-stat">
                                                <dt>Fechadas</dt>
                                                <dd><?= (int) $resumo['fechadas'] ?></dd>
                                            </div>
                                        </dl>
                                    <?php else: ?>
                                        <p class="month-flip-no-data">Sem arquivos de dados para este mês. Abra o relatório para gerar ou sincronizar.</p>
                                    <?php endif; ?>
                                    <a href="<?= $href ?>" class="flip-box-button month-flip-cta">Abrir relatório</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-files-message">
                ⚠️ Nenhum arquivo de período encontrado.<br>
                <small>Os arquivos devem seguir o padrão: mmmyyyy.php (ex: jan2026.php, dez2025.php)</small>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>