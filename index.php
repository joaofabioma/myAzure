<?php
require __DIR__ . '/functions.php';

$arquivosMesAno = obterArquivosMesAno();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Horas por Dia - Azure DevOps</title>
    <link rel="stylesheet" href="assets/styles.css">
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
            <div class="cards-container">
                <?php foreach ($arquivosMesAno as $info): ?>
                    <a href="<?= htmlspecialchars($info['arquivo']) ?>" class="month-card">
                        <div class="month-card-icon">📊</div>
                        <div class="month-card-title"><?= htmlspecialchars($info['mesExtenso']) ?></div>
                        <div class="month-card-year"><?= htmlspecialchars($info['ano']) ?></div>
                    </a>
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