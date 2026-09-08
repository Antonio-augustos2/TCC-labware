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
 * curl -s "https://seu-dominio.com/cron_analisar.php?token=SEU_TOKEN_URL_SAFE" > /dev/null 2>&1
 */

$cronLogFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cron_analisar.log';

function cronLog($message, $level = 'INFO') {
    global $cronLogFile;

    $line = sprintf(
        "[%s] [%s] [PID %s] %s%s",
        date('Y-m-d H:i:s'),
        $level,
        getmypid(),
        $message,
        PHP_EOL
    );

    @file_put_contents($cronLogFile, $line, FILE_APPEND | LOCK_EX);
}

// Direciona também os erros nativos do PHP para o log dedicado.
@ini_set('log_errors', '1');
@ini_set('error_log', $cronLogFile);
$requestUri = $_SERVER['REQUEST_URI'] ?? 'CLI';
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: 'CLI';
cronLog('Execução iniciada. Método: ' . ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . '; Caminho: ' . $requestPath);

// Registra erros fatais que não passam pelo try/catch.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        cronLog(sprintf('Erro fatal [%d]: %s em %s:%d', $error['type'], $error['message'], $error['file'], $error['line']), 'FATAL');
    }
});

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

// Apenas JSON será retornado
header('Content-Type: application/json; charset=utf-8');

// O token deve ser URL-safe (sem #, &, ?, espaços ou acentos).
$CRON_TOKEN = defined('CRON_TOKEN') ? (string) CRON_TOKEN : '';

// Validar token
$tokenRecebido = $_GET['token'] ?? '';
if ($CRON_TOKEN === '' || !is_string($tokenRecebido) || !hash_equals($CRON_TOKEN, $tokenRecebido)) {
    cronLog('Autenticação recusada: token ausente ou inválido.', 'ERROR');
    http_response_code(401);
    echo json_encode([
        'error' => 'Token inválido ou não fornecido',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

cronLog('Autenticação validada. Iniciando processamento.');

// Log de erro customizado
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    cronLog("Erro PHP [$errno]: $errstr em $errfile:$errline", 'ERROR');
});

try {
    require_once 'db_functions.php';
    require_once 'gemini_service.php';

    // Garantir colunas necessárias
    ensureCandidaturaStatusColumn($conn);
    ensureCandidatoStatusColumn($conn);

    cronLog('========================================');
    cronLog('Iniciando processamento de análises');

    // 1️⃣ BUSCAR CANDIDATOS COM STATUS 'Em analise'
    $candidatosEmAnalise = getCandidatosEmAnalise($conn, 20);
    
    if (empty($candidatosEmAnalise)) {
        cronLog('Nenhum candidato em análise no momento');
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

    cronLog('Encontrados ' . count($candidatosEmAnalise) . ' candidatura(s) de candidatos em análise');

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

        cronLog("Processando candidatura #$idCandidatura (Candidato: {$candidatura['candidato_nome']}, Vaga: {$candidatura['vaga_titulo']})");

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

    cronLog('========================================');
    cronLog('Processamento concluído!');
    cronLog('Total processadas: ' . $resultados['total_processadas']);
    cronLog('Sucesso: ' . $resultados['total_sucesso']);
    cronLog('Erros: ' . $resultados['total_erros']);
    cronLog('========================================');

    // 3️⃣ APÓS PROCESSAR O LOTE, VOLTAR O STATUS DOS CANDIDATOS PARA 'Pendente'
    // O objetivo é encerrar o ciclo de análise e liberar o candidato para o fluxo normal.
    $idsCandidatos = array_unique(array_map(static fn($item) => (int) $item['id_candidato'], $candidatosEmAnalise));
    if (!empty($idsCandidatos)) {
        $idsLista = implode(',', array_map('intval', $idsCandidatos));

        $conn->query("UPDATE candidato SET status = 'Pendente' WHERE id_candidato IN ($idsLista)");

        cronLog('Status de ' . count($idsCandidatos) . " candidato(s) processado(s) foi resetado para 'Pendente'.");
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

} catch (Throwable $e) {
    cronLog('Exceção: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine(), 'FATAL');
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao processar análises: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
