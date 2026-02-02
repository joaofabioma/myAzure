<?php
require __DIR__ . '/functions.php';
// Função para identificar arquivos com padrão mmmyyyy.php
function obterArquivosMesAno() {
    $arquivos = [];
    $diretorio = __DIR__;
    $meses = meses();
    
    // Escaneia o diretório atual
    $items = scandir($diretorio);
    
    foreach ($items as $item) {
        // Verifica se o arquivo tem extensão .php
        if (substr($item, -4) === '.php') {
            // Remove a extensão .php para análise
            $nome = substr($item, 0, -4);
            
            // Verifica se tem o padrão: 3 letras (mês) + 4 dígitos (ano)
            if (strlen($nome) === 7) {
                $mes = strtolower(substr($nome, 0, 3));
                $ano = substr($nome, 3, 4);
                
                // Verifica se o mês existe e o ano é numérico
                if (isset($meses[$mes]) && is_numeric($ano) && strlen($ano) === 4) {
                    $arquivos[] = [
                        'arquivo' => $item,
                        'mes' => $mes,
                        'ano' => $ano,
                        'mesNum' => $meses[$mes],
                        'mesExtenso' => ucfirst($mes),
                        'timestamp' => strtotime("$ano-{$meses[$mes]}-01")
                    ];
                }
            }
        }
    }
    
    // Ordena por timestamp (mais recente primeiro)
    usort($arquivos, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    return $arquivos;
}

$arquivosMesAno = obterArquivosMesAno();
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Horas por Dia - Azure DevOps</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/Microsoft_Azure.svg" type="image/svg+xml">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Estilos para os cards de meses */
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .month-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .month-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, #3498db, #2980b9);
        }
        
        .month-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(52, 152, 219, 0.2);
            border-color: #3498db;
        }
        
        .month-card-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #3498db;
        }
        
        .month-card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .month-card-year {
            font-size: 1.2rem;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        .section-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin: 40px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid #3498db;
            font-weight: 700;
        }

        .section-action {
            display: inline-block;
            margin-right: 12px;
            padding: 8px 12px;
            background: #3498db40;
            color: #fff;
            border-radius: 8px;
            font-size: 0.95rem;
            text-decoration: none;
            font-weight: 600;
            vertical-align: middle;
            /* box-shadow: 0 4px 0 #2980b9, 0 8px 16px rgba(52, 152, 219, 0.35); */
            transform: translateY(0);
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .section-action:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: 0 5px 0 #1f6fa5, 0 10px 18px rgba(52, 152, 219, 0.4);
        }

        .section-action:active {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #1f6fa5, 0 4px 10px rgba(52, 152, 219, 0.35);
        }
        
        .no-files-message {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(52, 152, 219, 0.1);
            color: #7f8c8d;
            font-size: 1.1rem;
        }
    </style>
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