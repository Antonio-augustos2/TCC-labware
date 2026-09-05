<?php
/**
 * Limpar Erros da API Gemini
 * Acesse: http://localhost/TCC-labware-main/clear_api_errors.php
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST.']);
    exit;
}

$errorsFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'api_errors.json';

$emptyData = [
    'total_errors' => 0,
    'last_update' => date('Y-m-d H:i:s'),
    'errors' => []
];

if (file_put_contents($errorsFile, json_encode($emptyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Erros limpados com sucesso']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível limpar os erros']);
}
?>
