<?php
session_start();

require_once 'config.php';
require_once 'db_functions.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: admin.php');
    exit;
}

ensureCandidaturaArquivoColumn($conn);
ensureCandidatoStatusColumn($conn);
$candidaturas = getCandidaturasComAnalise($conn);

// Filtrar candidaturas com status != Pendente e com arquivo armazenado
$candidaturasParaReprocessamento = array_filter($candidaturas, function($c) {
    $status = $c['candidato_status'] ?? $c['candidatura_status'] ?? 'Pendente';
    return $status !== 'Pendente';
});

$totalCandidaturas = count($candidaturas);
$totalPendentes = count(array_filter($candidaturas, function($c) {
    $status = $c['candidato_status'] ?? $c['candidatura_status'] ?? 'Pendente';
    return $status === 'Pendente';
}));
$totalAnalisadas = $totalCandidaturas - $totalPendentes;

$pageTitle = 'Assertividade e Feedback - LabWare';
include 'header.php';
?>
<main class="container analysis-page">
    <section class="admin-header analysis-header">
        <span class="analysis-eyebrow"><i class="fas fa-chart-line"></i> Gestão de talentos</span>
        <h2>Assertividade e feedback das candidaturas</h2>
        <p>Consulte a relação entre cada currículo recebido, a vaga escolhida, a assertividade da análise e o feedback gerado.</p>
    </section>

    <section class="analysis-summary" aria-label="Resumo das candidaturas">
        <div class="summary-card summary-total">
            <span class="summary-icon"><i class="fas fa-users"></i></span>
            <div><strong><?= $totalCandidaturas ?></strong><span>Total de candidaturas</span></div>
        </div>
        <div class="summary-card summary-ready">
            <span class="summary-icon"><i class="fas fa-circle-check"></i></span>
            <div><strong><?= $totalAnalisadas ?></strong><span>Em análise</span></div>
        </div>
        <div class="summary-card summary-pending">
            <span class="summary-icon"><i class="fas fa-clock"></i></span>
            <div><strong><?= $totalPendentes ?></strong><span>Pendentes</span></div>
        </div>
    </section>

    <section class="admin-card analysis-card" aria-labelledby="analysis-title">
        <div class="analysis-header-toolbar">
            <div>
                <span class="card-eyebrow">Painel de análise</span>
                <h3 id="analysis-title">Currículos recebidos</h3>
            </div>
            <?php if (!empty($candidaturasParaReprocessamento)): ?>
                <div class="toolbar-actions">
                    <button id="btnReprocessarComGemini" class="btn btn-primary" onclick="reprocessarCandidaturas()">
                        🔄 Reprocessar com Gemini
                    </button>
                    <span id="contadorSelecionados" class="contador-selecionados"></span>
                </div>
            <?php endif; ?>
        </div>
        <?php if (count($candidaturas) === 0): ?>
            <p class="empty-state">Nenhuma candidatura encontrada.</p>
        <?php else: ?>
            <div class="analysis-table-wrapper">
            <table class="analysis-table">
                <thead>
                    <tr>
                        <?php if (!empty($candidaturasParaReprocessamento)): ?>
                            <th class="col-checkbox">
                                <input type="checkbox" id="selectAllCheckbox" onchange="selecionarTodos(this.checked)">
                            </th>
                        <?php endif; ?>
                        <th>Currículo / candidato</th>
                        <th>Vaga referente</th>
                        <th>Assertividade</th>
                        <th>Feedback</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidaturas as $candidatura): ?>
                        <?php $statusAtual = $candidatura['candidato_status'] ?? $candidatura['candidatura_status'] ?? 'Pendente'; ?>
                        <tr <?php if ($statusAtual !== 'Pendente'): ?>class="row-reprocessavel" data-id="<?= (int)$candidatura['id_candidatura'] ?>"<?php endif; ?>>
                            <?php if (!empty($candidaturasParaReprocessamento)): ?>
                                <td class="col-checkbox">
                                    <?php if ($statusAtual !== 'Pendente'): ?>
                                        <input type="checkbox" class="candidatura-checkbox" value="<?= (int)$candidatura['id_candidatura'] ?>" onchange="atualizarContador()">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="candidate-name"><?= htmlspecialchars($candidatura['candidato_nome']) ?></span>
                                <span class="candidate-email"><?= htmlspecialchars($candidatura['candidato_email']) ?></span>
                                <span class="application-id">Candidatura #<?= (int)$candidatura['id_candidatura'] ?></span>
                            </td>
                            <td>
                                <span class="job-name"><?= htmlspecialchars($candidatura['vaga_titulo']) ?></span>
                                <span class="application-id">Vaga #<?= (int)$candidatura['id_vaga'] ?></span>
                            </td>
                            <td>
                                <?php if ($candidatura['assertividade'] !== null): ?>
                                    <span class="score"><?= number_format((float)$candidatura['assertividade'], 2, ',', '.') ?>%</span>
                                <?php else: ?>
                                    <span class="application-id">Ainda não calculada</span>
                                <?php endif; ?>
                            </td>
                            <td class="feedback-text">
                                <?php if ($candidatura['feedback'] !== null && trim($candidatura['feedback']) !== ''): ?>
                                    <?= htmlspecialchars(trim(preg_replace('/\s+/u', ' ', (string) $candidatura['feedback'])), ENT_QUOTES, 'UTF-8') ?>
                                <?php else: ?>
                                    <span class="application-id">Ainda não disponível</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="candidate-status <?= $statusAtual === 'Pendente' ? 'status-pendente' : 'status-em-analise' ?>"><?= htmlspecialchars($statusAtual) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
</main>

<script>
/**
 * Funcionalidade de Reprocessamento com Gemini
 * Usa .map() para processar múltiplos currículos
 */

function selecionarTodos(selecionado) {
    document.querySelectorAll('.candidatura-checkbox').forEach(checkbox => {
        checkbox.checked = selecionado;
    });
    atualizarContador();
}

function atualizarContador() {
    const selecionados = document.querySelectorAll('.candidatura-checkbox:checked');
    const contador = document.getElementById('contadorSelecionados');
    const botao = document.getElementById('btnReprocessarComGemini');
    
    if (selecionados.length > 0) {
        contador.textContent = `${selecionados.length} selecionado(s)`;
        contador.style.display = 'inline-block';
        botao.disabled = false;
    } else {
        contador.style.display = 'none';
        botao.disabled = true;
    }
}

function reprocessarCandidaturas() {
    const selecionados = document.querySelectorAll('.candidatura-checkbox:checked');
    
    if (selecionados.length === 0) {
        alert('Selecione pelo menos uma candidatura para reprocessar');
        return;
    }
    
    const candidaturaIds = Array.from(selecionados).map(checkbox => parseInt(checkbox.value));
    
    if (!confirm(`Tem certeza que deseja reprocessar ${candidaturaIds.length} candidatura(s) com a API Gemini? Esta ação pode levar alguns minutos.`)) {
        return;
    }
    
    const botao = document.getElementById('btnReprocessarComGemini');
    const textoBotaoOriginal = botao.textContent;
    botao.disabled = true;
    botao.textContent = '⏳ Processando...';
    
    fetch('api_reprocessar_candidaturas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            candidatura_ids: candidaturaIds
        })
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.error || 'Erro na requisição');
            });
        }
        return response.json();
    })
    .then(data => {
        const resultadosFormatos = Object.entries(data.resultados).map(([id, resultado]) => {
            return `✓ ${resultado.candidato}: ${resultado.assertividade}%`;
        });
        
        let mensagem = `Processamento concluído!\n\nCandidaturas reprocessadas: ${data.processados}`;
        
        if (resultadosFormatos.length > 0) {
            mensagem += `\n\nResultados:\n${resultadosFormatos.join('\n')}`;
        }
        
        if (data.erros > 0) {
            mensagem += `\n\nErros encontrados: ${data.erros}`;
            console.error('Detalhes dos erros:', data.detalhes_erros);
        }
        
        alert(mensagem);
        setTimeout(() => {
            location.reload();
        }, 1000);
    })
    .catch(error => {
        console.error('Erro:', error);
        alert(`Erro ao reprocessar candidaturas: ${error.message}`);
        botao.disabled = false;
        botao.textContent = textoBotaoOriginal;
    });
}

document.addEventListener('DOMContentLoaded', function() {
    atualizarContador();
});
</script>

<?php include 'footer.php'; ?>
