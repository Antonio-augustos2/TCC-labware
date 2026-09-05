<?php
/**
 * API de Reprocessamento de Candidaturas - LabWare
 * Processa candidaturas em lote com a API Gemini
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'db_functions.php';
require_once 'gemini_service.php';

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

// Verificar se é uma requisição autenticada (admin)
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

// Obter dados da requisição
$data = json_decode(file_get_contents('php://input'), true);
$candidaturaIds = $data['candidatura_ids'] ?? [];

// Validar que temos IDs de candidaturas
if (empty($candidaturaIds) || !is_array($candidaturaIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Nenhuma candidatura foi selecionada']);
    exit;
}

// Sanitizar IDs
$candidaturaIds = array_map('intval', $candidaturaIds);

ensureCandidaturaArquivoColumn($conn);
ensureCandidatoStatusColumn($conn);

// Obter todas as candidaturas para reprocessamento
$todasCandidaturas = getCandidaturasParaReprocessamento($conn);

// Filtrar apenas as que foram solicitadas
$candidaturasParaProcessar = array_filter($todasCandidaturas, function($c) use ($candidaturaIds) {
    return in_array($c['id_candidatura'], $candidaturaIds);
});

if (empty($candidaturasParaProcessar)) {
    http_response_code(404);
    echo json_encode(['error' => 'Nenhuma candidatura válida para reprocessamento encontrada']);
    exit;
}

$resultados = [];
$erros = [];

// Usar .map() para processar cada candidatura
$processados = array_map(function($candidatura) use (&$resultados, &$erros, $conn) {
    $idCandidatura = (int) $candidatura['id_candidatura'];
    $arquivoPath = trim((string) ($candidatura['arquivo_path'] ?? ''));
    $vagaDescricao = $candidatura['vaga_descricao'];

    if ($arquivoPath !== '') {
        $arquivoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $arquivoPath);
        if (!preg_match('/^[A-Za-z]:\\\\|^' . preg_quote(DIRECTORY_SEPARATOR, '/') . '/', $arquivoPath)) {
            $arquivoPath = __DIR__ . DIRECTORY_SEPARATOR . ltrim($arquivoPath, DIRECTORY_SEPARATOR);
        }
    }

    // Verificar se o arquivo existe
    if ($arquivoPath === '' || !file_exists($arquivoPath)) {
        $erros[$idCandidatura] = 'Arquivo não encontrado: ' . ($candidatura['arquivo_path'] ?? '');
        updateCandidatoStatus($conn, (int) $candidatura['id_candidato'], 'Pendente');
        return ['id' => $idCandidatura, 'sucesso' => false];
    }

    // Determinar o tipo de arquivo
    $extensao = strtolower(pathinfo($arquivoPath, PATHINFO_EXTENSION));
    $fileInfo = ['extension' => $extensao];
    
    // Validar extensão
    if (!in_array($extensao, ['pdf', 'docx'])) {
        $erros[$idCandidatura] = 'Tipo de arquivo não suportado: ' . $extensao;
        updateCandidatoStatus($conn, (int) $candidatura['id_candidato'], 'Pendente');
        return ['id' => $idCandidatura, 'sucesso' => false];
    }
    
    // Preparar arquivo para análise
    $arquivo = ['tmp_name' => $arquivoPath];
    
    // Analisar com Gemini
    $analise = analyzeResumeWithGemini($arquivo, $fileInfo, $vagaDescricao);
    
    if (!$analise['success']) {
        $erros[$idCandidatura] = $analise['error'] ?? 'Erro desconhecido ao analisar currículo';
        updateCandidatoStatus($conn, (int) $candidatura['id_candidato'], 'Em análise');
        return ['id' => $idCandidatura, 'sucesso' => false];
    }

    // Atualizar candidatura com novos dados
    $atualizado = updateCandidaturaAnalise($conn, $idCandidatura, $analise['assertividade'], $analise['feedback']);
    
    if ($atualizado) {
        $resultados[$idCandidatura] = [
            'candidato' => $candidatura['candidato_nome'],
            'assertividade' => $analise['assertividade'],
            'feedback' => substr($analise['feedback'], 0, 100) . '...'
        ];
        return ['id' => $idCandidatura, 'sucesso' => true];
    } else {
        $erros[$idCandidatura] = 'Erro ao atualizar banco de dados';
        updateCandidatoStatus($conn, (int) $candidatura['id_candidato'], 'Pendente');
        return ['id' => $idCandidatura, 'sucesso' => false];
    }
}, $candidaturasParaProcessar);

// Responder com resultados
http_response_code(200);
echo json_encode([
    'sucesso' => count($resultados) > 0,
    'processados' => count($resultados),
    'erros' => count($erros),
    'resultados' => $resultados,
    'detalhes_erros' => $erros
]);
