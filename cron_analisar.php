<?php
/**
 * CRON - Processamento Assíncrono de Análise de Currículos
 * 
 * Este script deve ser executado periodicamente (a cada 5-15 minutos) via CRON.
 * Responsabilidades:
 * - Buscar candidatos com status 'Em analise'
 * - Processar análise de TODAS as candidaturas destes candidatos com Gemini
 * - Atualizar banco de dados com resultados
 * - Registrar logs detalhados
 * - Marcar candidatos como 'Pendente' após processamento completo
 * 
 * Configuração CRON sugerida:
 * *///seu-dominio.com/cron_analisar.php?token=k7j#$vP@2qL9m!zX5bN$wY3T&rQ8eF > /dev/null 2>&1
 
 // Nota: A linha acima é um exemplo de CRON, não código PHP
 //

// Apenas JSON será retornado
header('Content-Type: application/json; charset=utf-8');

// Token de segurança - altere isso em produção!
// Pode ser definido em config.php ou aqui
$CRON_TOKEN = defined('CRON_TOKEN') ? CRON_TOKEN : 'k7j#$vP@2qL9m!zX5bN$wY3T&rQ8eF';

// Validar token
$tokenRecebido = $_GET['token'] ?? '';
if (empty($CRON_TOKEN) || $tokenRecebido !== $CRON_TOKEN) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Token inválido ou não fornecido',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Log de erro customizado
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("[CRON] Erro PHP [$errno]: $errstr em $errfile:$errline");
});

try {
    require_once 'config.php';
    require_once 'db_functions.php';
    require_once 'gemini_service.php';

    // Garantir colunas necessárias
    ensureCandidaturaStatusColumn($conn);
    ensureCandidatoStatusColumn($conn);

    error_log("⏰ [CRON] ========================================");
    error_log("⏰ [CRON] Iniciando processamento de análises");
    error_log("⏰ [CRON] Timestamp: " . date('Y-m-d H:i:s'));
    error_log("⏰ [CRON] ========================================");

    // 1️⃣ BUSCAR CANDIDATOS COM STATUS 'Em analise'
    $candidatosEmAnalise = getCandidatosEmAnalise($conn, 20);
    
    if (empty($candidatosEmAnalise)) {
        error_log("ℹ️  [CRON] Nenhum candidato em análise no momento");
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Nenhum candidato em análise',
            'processadas' => 0,
            'sucesso' => 0,
            'erros' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    error_log("📋 [CRON] Encontrados " . count($candidatosEmAnalise) . " candidatura(s) de candidatos em análise");

    // 2️⃣ PROCESSAR CADA CANDIDATURA
    $resultados = [
        'total_processadas' => 0,
        'total_sucesso' => 0,
        'total_erros' => 0,
        'detalhes' => []
    ];

    foreach ($candidatosEmAnalise as $candidatura) {
        $idCandidatura = (int)$candidatura['id_candidatura'];
        $idCandidato = (int)$candidatura['id_candidato'];
        $caminhoArquivo = __DIR__ . DIRECTORY_SEPARATOR . $candidatura['arquivo_path'];
        $descricaoVaga = $candidatura['vaga_descricao'] ?? '';

        error_log("🔄 [CRON] Processando candidatura #$idCandidatura (Candidato: {$candidatura['candidato_nome']}, Vaga: {$candidatura['vaga_titulo']})");

        // Chamar função de análise assíncrona
        $resultadoAnalise = analizarComApi($conn, $caminhoArquivo, $descricaoVaga, $idCandidatura);

        $resultados['total_processadas']++;

        if ($resultadoAnalise['success']) {
            $resultados['total_sucesso']++;
            $resultados['detalhes'][] = [
                'id_candidatura' => $idCandidatura,
                'candidato' => $candidatura['candidato_nome'],
                'vaga' => $candidatura['vaga_titulo'],
                'status' => 'Sucesso',
                'assertividade' => $resultadoAnalise['assertividade'] ?? 0,
                'feedback_preview' => mb_substr($resultadoAnalise['feedback'] ?? '', 0, 100) . '...'
            ];
        } else {
            $resultados['total_erros']++;
            $resultados['detalhes'][] = [
                'id_candidatura' => $idCandidatura,
                'candidato' => $candidatura['candidato_nome'],
                'vaga' => $candidatura['vaga_titulo'],
                'status' => 'Erro',
                'erro' => $resultadoAnalise['erro'] ?? 'Erro desconhecido'
            ];
        }
    }

    error_log("⏰ [CRON] ========================================");
    error_log("✅ [CRON] Processamento concluído!");
    error_log("📊 [CRON] Total processadas: " . $resultados['total_processadas']);
    error_log("✅ [CRON] Sucesso: " . $resultados['total_sucesso']);
    error_log("❌ [CRON] Erros: " . $resultados['total_erros']);
    error_log("⏰ [CRON] ========================================");

    // 3️⃣ APÓS PROCESSAR O LOTE, VOLTAR O STATUS DOS CANDIDATOS PARA 'Pendente'
    // O objetivo é encerrar o ciclo de análise e liberar o candidato para o fluxo normal.
    $idsCandidatos = array_unique(array_map(static fn($item) => (int) $item['id_candidato'], $candidatosEmAnalise));
    if (!empty($idsCandidatos)) {
        $idsLista = implode(',', array_map('intval', $idsCandidatos));

        $conn->query("UPDATE candidato SET status = 'Pendente' WHERE id_candidato IN ($idsLista)");

        error_log("🔄 [CRON] Status de " . count($idsCandidatos) . " candidato(s) processado(s) foi resetado para 'Pendente'.");
    }

    // 4️⃣ RETORNAR RESULTADO
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Processamento de análises concluído',
        'processadas' => $resultados['total_processadas'],
        'sucesso' => $resultados['total_sucesso'],
        'erros' => $resultados['total_erros'],
        'detalhes' => $resultados['detalhes'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log('[CRON] Exceção: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao processar análises: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
