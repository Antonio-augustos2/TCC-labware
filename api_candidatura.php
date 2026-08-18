<?php
/**
 * API de Candidaturas - LabWare
 * Processa o formulário de candidatura
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once 'db_functions.php';

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
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

// Registrar candidatura
if (registerCandidatura($conn, $nome, $email, $job_id)) {
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Candidatura enviada com sucesso!'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Erro ao registrar candidatura'
    ]);
}
