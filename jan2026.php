<?php
$qual = basename(__FILE__, '.php');

require __DIR__ . '/functions.php';

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <?= require __DIR__ . '/partials/head.php' ?>
</head>

<body>
    <div class="container fade-in">
        <?php require __DIR__ . '/partials/contador.php'; ?>

        <h2 style="margin-top: 50px;">📈 Visualização Gráfica</h2>
        <canvas id="grafico" height="100"></canvas>

        <h2>📊 Horas Trabalhadas por Dia</h2>

        <div class="info-card">
            <div class="status-update">
                Sistema atualizado em tempo real | Auto-refresh a cada 30 minutos
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>📅 Data</th>
                    <th>⏱️ Horas</th>
                    <th>📊 Qtd</th>
                    <th>📝 Tarefas</th>
                </tr>

            </thead>
            <tbody>
                <?php foreach ($dados as $row): ?>
                    <tr>
                        <td><?= !empty($row['date']) ? date('d/m/Y', strtotime($row['date'])) : '-' ?></td>
                        <td><?= number_format(floatval($row['hours'] ?? 0), 2, ',', '.') ?></td>
                        <td><?= intval($row['total'] ?? 0) ?></td>
                        <td><?= htmlspecialchars(implode(' | ', array_map('strval', $row['tarefas'] ?? [])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <?= require __DIR__ . '/partials/scripts.php'; ?>
</body>

</html>