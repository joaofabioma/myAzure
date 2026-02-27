<?php
// partials/scripts.php
require __DIR__ . '/../vendor/autoload.php';
\App\Class\Security::validateRequestByFile(__FILE__);

$data = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
return <<<HTML
<script>
        const dados = {$data};

        new Chart(document.getElementById('grafico'), {
            type: 'bar',
            data: {
                // usar strings formatadas para evitar problemas de fuso-horário
                labels: dados.map(d => d.date ? d.date.split('-').reverse().join('/') : ''),
                datasets: [{
                    label: 'Horas por dia',
                    data: dados.map(d => Number(d.hours) || 0)
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

</script>

 <script>
        // Adicionar interatividade à tabela
        document.addEventListener('DOMContentLoaded', function() {
            // Efeito de hover nas linhas da tabela
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    this.classList.toggle('selected');
                });
            });
            
            // Tooltip para tarefas longas
            const taskCells = document.querySelectorAll('td:nth-child(4)');
            taskCells.forEach(cell => {
                if (cell.textContent.length > 50) {
                    cell.setAttribute('data-tooltip', cell.textContent);
                    cell.textContent = cell.textContent.substring(0, 50) + '...';
                }
            });
        });
    </script>
HTML;
