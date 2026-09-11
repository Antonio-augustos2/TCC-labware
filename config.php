<?php
/**
 * Configuração de Banco de Dados - LabWare
 * Conexão com MySQL através do phpMyAdmin
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// A chave deve ficar no ambiente ou em um arquivo .env local (nunca no Git).
function loadLocalEnv($path) {
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . trim($value, "\"'"));
        }
    }
}

loadLocalEnv(__DIR__ . DIRECTORY_SEPARATOR . '.env');

$requiredEnv = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'CRON_TOKEN'];
foreach ($requiredEnv as $envName) {
    if (getenv($envName) === false) {
        error_log('Configuração obrigatória ausente: ' . $envName);
        http_response_code(500);
        die('Serviço temporariamente indisponível.');
    }
}

define('DB_HOST', getenv('DB_HOST'));
define('DB_USER', getenv('DB_USER'));
define('DB_PASS', getenv('DB_PASS'));
define('DB_NAME', getenv('DB_NAME'));
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Criar conexão sem expor warnings ou detalhes da conexão ao cliente.
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar conexão
if ($conn->connect_error) {
    error_log('Erro ao conectar ao banco de dados: ' . $conn->connect_error);
    http_response_code(500);
    die('Serviço temporariamente indisponível.');
}

// Definir charset para UTF-8 somente após uma conexão válida.
$conn->set_charset('utf8mb4');


// Use only URL-safe characters because this value is sent in ?token=...
define('CRON_TOKEN', getenv('CRON_TOKEN'));

function getCsrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}