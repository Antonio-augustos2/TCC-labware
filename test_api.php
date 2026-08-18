<?php
/**
 * Teste da API de Vagas
 * Acesse: http://localhost/tcc/test_api.php
 */

require_once 'config.php';
require_once 'db_functions.php';

echo "<h1>Teste da API de Vagas</h1>";

// Testar conexão
echo "<h2>1. Conexão com Banco</h2>";
if ($conn) {
    echo "<p style='color: green;'><strong>✓ Conexão estabelecida</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Erro na conexão</strong></p>";
    exit;
}

// Testar tabela de vagas
echo "<h2>2. Verificar Tabela 'vaga'</h2>";
$result = $conn->query("SHOW TABLES LIKE 'vaga'");
if ($result->num_rows > 0) {
    echo "<p style='color: green;'><strong>✓ Tabela 'vaga' existe</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Tabela 'vaga' não encontrada</strong></p>";
}

// Contar vagas
echo "<h2>3. Contar Vagas</h2>";
$result = $conn->query("SELECT COUNT(*) as total FROM vaga WHERE status = 'Aberta'");
$row = $result->fetch_assoc();
$total = $row['total'];
echo "<p><strong>Total de vagas abertas:</strong> $total</p>";

if ($total === 0) {
    echo "<p style='color: orange;'><strong>⚠ Nenhuma vaga aberta no banco de dados</strong></p>";
    echo "<p>Verifique se as vagas foram importadas do arquivo recrutamento (1).sql</p>";
}

// Listar vagas
echo "<h2>4. Listar Vagas</h2>";
$result = $conn->query("SELECT id_vaga, titulo, status FROM vaga LIMIT 10");
if ($result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Título</th><th>Status</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id_vaga'] . "</td>";
        echo "<td>" . $row['titulo'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>Nenhuma vaga encontrada</strong></p>";
}

// Testar função getAllVagas()
echo "<h2>5. Testar Função getAllVagas()</h2>";
$vagas = getAllVagas($conn);
echo "<pre>";
echo "Resultado: " . json_encode($vagas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";

// Testar API JSON diretamente
echo "<h2>6. Teste da API</h2>";
echo "<p><a href='api_vagas.php' target='_blank'>Abrir api_vagas.php em nova aba</a></p>";

$conn->close();
?>
