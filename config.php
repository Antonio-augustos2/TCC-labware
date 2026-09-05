<?php
/**
 * Configuração de Banco de Dados - LabWare
 * Conexão com MySQL através do phpMyAdmin
 */

// Configurações de Conexão
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'recrutamento');

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
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');

// Criar conexão
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Definir charset para UTF-8
$conn->set_charset("utf8mb4");

// Verificar conexão
if ($conn->connect_error) {
    die("Erro ao conectar ao banco de dados: " . $conn->connect_error);
}


define('CRON_TOKEN', 'k7j#$vP@2qL9m!zX5bN$wY3T&rQ8eF');