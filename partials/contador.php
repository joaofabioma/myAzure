<?php
// partials/contador.php
require __DIR__ . '/../vendor/autoload.php';
\App\Class\Security::validateRequestByFile(__FILE__);

// Não usar echo/return aqui, vamos incluir diretamente o HTML/JS
$ultimaAtualizacao = date('d/m/Y H:i:s');
?>

<div id="contador-relogio" style="
    position: fixed;
    top: 3px;
    right: 40px;
    background: linear-gradient(135deg, #667eead1 0%, #7395a5c2 100%);
    color: white;
    padding: 3px 30px;
    border-radius: 10px;
    font-family: 'Arial', sans-serif;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    min-width: 250px;
    text-align: center;
    border: 2px solid rgba(255, 255, 255, 0.1);
">
    <div style="font-size: 12px; opacity: 0.9; margin-bottom: 5px;">
        📊 RELATÓRIO ATUALIZADO
    </div>

    <div style="font-size: 20px; font-weight: bold; margin: 5px 0;" id="hora-atual">
        <?= date('H:i:s') ?>
    </div>

    <div style="font-size: 11px; opacity: 0.8; margin-top: 5px;">
        Última atualização: <span id="ultima-atualizacao"><?= $ultimaAtualizacao ?></span>
    </div>

    <div style="margin-top: 10px; font-size: 11px;">
        <div style="display: inline-block; background: rgba(255, 255, 255, 0.2); 
                    padding: 3px 8px; border-radius: 15px;">
            ⏳ Próxima atualização em: <span id="contador">30:00</span>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const horaAtualEl = document.getElementById('hora-atual');
        const ultimaAtualizacaoEl = document.getElementById('ultima-atualizacao');
        const contadorEl = document.getElementById('contador');

        // Configurações
        const intervaloAtualizacao = <?= TIMER ?> * 60 * 1000; // 30 minutos em milissegundos
        let tempoRestante = intervaloAtualizacao;
        let ultimaAtualizacaoTimestamp = Date.now();

        // Atualizar hora atual a cada segundo
        function atualizarHoraAtual() {
            const agora = new Date();
            horaAtualEl.textContent = agora.toLocaleTimeString('pt-BR');
        }

        // Atualizar contador regressivo
        function atualizarContador() {
            tempoRestante -= 1000;

            if (tempoRestante <= 0) {
                tempoRestante = intervaloAtualizacao;
                ultimaAtualizacaoTimestamp = Date.now();
                atualizarPagina();
            }

            const minutos = Math.floor(tempoRestante / 60000);
            const segundos = Math.floor((tempoRestante % 60000) / 1000);

            contadorEl.textContent = `${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;

            // Mudar cor quando faltar menos de 1 minuto
            if (tempoRestante < 60000) {
                contadorEl.style.color = '#ff6b6b';
                contadorEl.style.fontWeight = 'bold';
            } else if (tempoRestante < 120000) {
                contadorEl.style.color = '#ffd93d';
            } else {
                contadorEl.style.color = '';
            }
        }

        // Atualizar página
        function atualizarPagina() {
            // Atualizar o timestamp da última atualização
            const agora = new Date();
            ultimaAtualizacaoEl.textContent = agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR');

            // Adicionar animação
            contadorEl.parentElement.style.animation = 'pulse 0.5s';
            setTimeout(() => {
                contadorEl.parentElement.style.animation = '';
            }, 500);

            // Recarregar a página (ou fazer uma requisição AJAX)
            location.reload();
        }

        // Botão de atualização manual (opcional)
        function adicionarBotaoAtualizacao() {
            const botao = document.createElement('button');
            botao.innerHTML = '🔄 Atualizar Agora';
            botao.style.cssText = `
                background: rgba(255, 255, 255, 0.3);
                border: none;
                color: white;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 11px;
                margin-top: 8px;
                cursor: pointer;
                transition: background 0.3s;
            `;

            botao.onmouseover = () => botao.style.background = 'rgba(255, 255, 255, 0.5)';
            botao.onmouseout = () => botao.style.background = 'rgba(255, 255, 255, 0.3)';
            botao.onclick = atualizarPagina;

            contadorEl.parentElement.parentElement.appendChild(botao);
        }

        // Iniciar tudo
        atualizarHoraAtual();
        setInterval(atualizarHoraAtual, 1000);

        setInterval(atualizarContador, 1000);

        // Adicionar botão opcional
        adicionarBotaoAtualizacao();

        // Adicionar CSS para animação
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            #contador-relogio:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
                transition: all 0.3s ease;
            }
        `;
        document.head.appendChild(style);
    });
</script>