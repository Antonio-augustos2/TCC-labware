<?php
/**
 * API de Candidaturas - LabWare
 * Processa o formulário de candidatura
 */
header('Content-Type: application/json; charset=utf-8');

// Garantir que erros sejam retornados como JSON
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("Erro PHP [$errno]: $errstr em $errfile:$errline");
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $errstr]);
    exit;
});

try {
    require_once 'config.php';
    require_once 'db_functions.php';
    require_once 'gemini_service.php';

    // Garantir que as colunas necessárias existem no banco de dados
    ensureCandidaturaArquivoColumn($conn);
    ensureCandidatoStatusColumn($conn);
    ensureCandidatoCurriculoColumn($conn);

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        exit;
    }

    // Obter dados do formulário
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $job_id = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);

    // Validar dados
    if (empty($nome) || empty($email) || !$job_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Dados inválidos: nome, email e vaga são obrigatórios']);
        exit;
    }

    // Validar email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Email inválido']);
        exit;
    }

    $vaga = getVagaById($conn, $job_id);
    if (!$vaga) {
        http_response_code(400);
        echo json_encode(['error' => 'A vaga selecionada não foi encontrada.']);
        exit;
    }

    // Validar o arquivo no servidor antes de qualquer gravação no banco.
    $arquivo = $_FILES['formulario'] ?? null;
    if (!$arquivo) {
        http_response_code(400);
        echo json_encode(['error' => 'Arquivo não foi enviado']);
        exit;
    }

    $arquivoInfo = validateResumeFile($arquivo);
    if (!$arquivoInfo['valid']) {
        http_response_code(400);
        echo json_encode(['error' => $arquivoInfo['error']]);
        exit;
    }

    // Ler o conteúdo do currículo para armazenar no banco de dados (BLOB)
    $curriculoConteudo = file_get_contents($arquivo['tmp_name']);
    if ($curriculoConteudo === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao ler o arquivo de currículo']);
        exit;
    }

    // Preparar dados para salvar no sistema de arquivos
    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'candidatos';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log('Erro ao criar diretório de upload: ' . $uploadDir);
        }
    }

    $nomeArquivoSalvo = 'candidatura_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $arquivoInfo['extension'];
    $caminhoArquivo = $uploadDir . DIRECTORY_SEPARATOR . $nomeArquivoSalvo;
    $caminhoRelativo = 'uploads/candidatos/' . $nomeArquivoSalvo;

    // Registrar candidatura e deixar o candidato em estado de análise até o Gemini confirmar.
    $idCandidatura = registerCandidatura($conn, $nome, $email, $job_id, $curriculoConteudo, $caminhoRelativo, 'Em análise');
    if (!$idCandidatura) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao registrar candidatura no banco de dados']);
        exit;
    }

    // Tentar salvar o arquivo também no sistema de arquivos (backup)
    if (copy($arquivo['tmp_name'], $caminhoArquivo)) {
        error_log('✓ Currículo ID ' . $idCandidatura . ' salvo em: ' . $caminhoArquivo);
    } else {
        error_log('⚠ Arquivo não salvo no sistema para ID ' . $idCandidatura . ', mas currículo está no banco de dados');
    }

    set_time_limit(120);
    $analise = analyzeResumeWithGemini($arquivo, $arquivoInfo, $vaga['description']);
    if (!$analise || !$analise['success']) {
        $idCandidato = getCandidatoIdByCandidatura($conn, $idCandidatura);
        if ($idCandidato !== null) {
            updateCandidatoStatus($conn, $idCandidato, 'Em análise');
        }
        error_log('Erro na análise do currículo: ' . ($analise['error'] ?? 'Erro desconhecido'));
        http_response_code(503);
        echo json_encode(['error' => $analise['error'] ?? 'Erro ao analisar o currículo com Gemini. Tente novamente.']);
        exit;
    }

    if (!$analise['is_curriculum']) {
        $idCandidato = getCandidatoIdByCandidatura($conn, $idCandidatura);
        if ($idCandidato !== null) {
            updateCandidatoStatus($conn, $idCandidato, 'Em análise');
        }
        http_response_code(422);
        echo json_encode(['error' => 'O arquivo enviado não parece ser um currículo. Envie um currículo em PDF ou DOCX.']);
        exit;
    }

    $tamanhoArquivo = strlen($curriculoConteudo);
    error_log("📄 Arquivo lido: " . $arquivo['name'] . " - Tamanho: " . number_format($tamanhoArquivo) . " bytes para salvar em BLOB");

    $updateResult = updateCandidaturaAnalise($conn, $idCandidatura, $analise['assertividade'], $analise['feedback'], $caminhoRelativo);

    if ($updateResult) {
        $idCandidato = getCandidatoIdByCandidatura($conn, $idCandidatura);
        if ($idCandidato !== null) {
            updateCandidatoStatus($conn, $idCandidato, 'Pendente');
        }

        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Candidatura enviada com sucesso!',
            'candidatura_id' => $idCandidatura
        ]);
    } else {
        $idCandidato = getCandidatoIdByCandidatura($conn, $idCandidatura);
        if ($idCandidato !== null) {
            updateCandidatoStatus($conn, $idCandidato, 'Em análise');
        }
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao registrar análise da candidatura']);
    }

} catch (Exception $e) {
    error_log('Exceção em api_candidatura.php: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
