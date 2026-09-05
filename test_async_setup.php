<?php
/**
 * Script de Teste - Validar Instalação e Configuração
 * 
 * Use este script para validar se a refatoração foi implementada corretamente.
 * Acesse: http://seu-dominio.com/test_async_setup.php
 */

// Configurar output
header('Content-Type: text/html; charset=utf-8');
ob_start();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste - Configuração Assíncrona</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #2196F3;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .test-item {
            border-left: 4px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .test-item.pass {
            background-color: #e8f5e9;
            border-left-color: #4CAF50;
        }
        .test-item.fail {
            background-color: #ffebee;
            border-left-color: #f44336;
        }
        .test-item.warn {
            background-color: #fff3e0;
            border-left-color: #ff9800;
        }
        .test-title {
            font-weight: bold;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .icon {
            font-size: 20px;
        }
        .test-desc {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .code-block {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-ok {
            background: #4CAF50;
            color: white;
        }
        .status-error {
            background: #f44336;
            color: white;
        }
        .status-warn {
            background: #ff9800;
            color: white;
        }
        .summary {
            background: #e3f2fd;
            border: 1px solid #2196F3;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .summary h3 {
            margin-top: 0;
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>✅ Teste de Configuração - Processamento Assíncrono</h1>

<?php

// Configuração
$testes_realizados = 0;
$testes_ok = 0;
$testes_erro = 0;

function teste($titulo, $condicao, $mensagem = '', $tipo = 'pass') {
    global $testes_realizados, $testes_ok, $testes_erro;
    $testes_realizados++;
    
    if ($condicao) {
        $testes_ok++;
        $classe = 'pass';
        $icone = '✅';
    } else {
        $testes_erro++;
        $classe = 'fail';
        $icone = '❌';
    }
    
    if ($tipo === 'warn') {
        $classe = 'warn';
        $icone = '⚠️';
    }
    
    echo "<div class='test-item $classe'>";
    echo "<div class='test-title'><span class='icon'>$icone</span>$titulo</div>";
    if ($mensagem) {
        echo "<div class='test-desc'>$mensagem</div>";
    }
    echo "</div>";
}

// ============================================
// 1. TESTES DE ARQUIVO
// ============================================
echo "<h2>1. Arquivos Necessários</h2>";

$arquivos_necessarios = [
    'db_functions.php' => 'Funções de banco de dados',
    'gemini_service.php' => 'Serviço Gemini',
    'api_candidatura.php' => 'API de candidaturas (refatorada)',
    'cron_analisar.php' => 'Script CRON (novo)',
];

foreach ($arquivos_necessarios as $arquivo => $descricao) {
    $existe = file_exists(__DIR__ . DIRECTORY_SEPARATOR . $arquivo);
    teste($arquivo, $existe, $descricao);
}

// ============================================
// 2. TESTES DE BANCO DE DADOS
// ============================================
echo "<h2>2. Banco de Dados</h2>";

include_once 'config.php';
include_once 'db_functions.php';

$tem_conexao = isset($conn) && $conn;
teste("Conexão com banco de dados", $tem_conexao, "Verificando mysqli connection");

if ($tem_conexao) {
    // Verificar tabelas
    $tabelas = ['candidato', 'candidatura', 'vaga'];
    foreach ($tabelas as $tabela) {
        $result = $conn->query("SHOW TABLES LIKE '$tabela'");
        teste("Tabela: $tabela", $result && $result->num_rows > 0, "");
    }
    
    // Verificar colunas cruciais
    echo "<h3>Colunas Candidatura</h3>";
    
    // Chamar função para garantir coluna
    ensureCandidaturaStatusColumn($conn);
    
    $result = $conn->query("DESCRIBE candidatura");
    $colunas = [];
    while ($row = $result->fetch_assoc()) {
        $colunas[] = $row['Field'];
    }
    
    $colunas_necessarias = ['id_candidatura', 'id_candidato', 'id_vaga', 'arquivo_path', 'status', 'assertividade', 'feedback'];
    foreach ($colunas_necessarias as $col) {
        $tem = in_array($col, $colunas);
        teste("Coluna: $col", $tem, $tem ? "✓" : "✗");
    }
}

// ============================================
// 3. TESTES DE FUNÇÕES
// ============================================
echo "<h2>3. Novas Funções Implementadas</h2>";

$funcoes_necessarias = [
    'getCandidaturasPendentes',
    'updateCandidaturaStatus',
    'getCandidaturaComDetalhes',
    'ensureCandidaturaStatusColumn',
    'analizarComApi'
];

foreach ($funcoes_necessarias as $funcao) {
    teste("Função: $funcao", function_exists($funcao), "");
}

// ============================================
// 4. TESTES DE CONFIGURAÇÃO
// ============================================
echo "<h2>4. Configuração e Ambiente</h2>";

teste("config.php Carregado", defined('DB_HOST'), "Constantes de banco de dados definidas");
teste("GEMINI_API_KEY Configurada", defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY), 
    "API Key do Gemini deve estar configurada em config.php");

if (defined('CRON_TOKEN')) {
    teste("CRON_TOKEN Definido", true, "Token de segurança para CRON: " . 
        (strlen(CRON_TOKEN) > 10 ? "String suficientemente longa ✓" : "⚠️ Muito curto (min 10 chars)"), 
        strlen(CRON_TOKEN) > 10 ? 'pass' : 'warn');
} else {
    teste("CRON_TOKEN Definido", false, 
        "⚠️ Defina CRON_TOKEN em config.php: define('CRON_TOKEN', 'seu_token_secreto');", 'warn');
}

// ============================================
// 5. TESTES DE ARQUIVO UPLOAD
// ============================================
echo "<h2>5. Diretório de Upload</h2>";

$upload_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'candidatos';
teste("Diretório de upload existe", is_dir($upload_dir), $upload_dir);
teste("Permissões de escrita", is_writable($upload_dir), "Diretório deve ser gravável");

// ============================================
// 6. TESTES DE API
// ============================================
echo "<h2>6. API Endpoints</h2>";

teste("api_candidatura.php Exists", file_exists(__DIR__ . '/api_candidatura.php'), "Refatorada para fluxo assíncrono");

// Verificar se cron_analisar.php está acessível
if (file_exists(__DIR__ . '/cron_analisar.php')) {
    teste("cron_analisar.php Exists", true, "Script pronto para estar no CRON");
    
    // Verificar se tem validação de token
    $conteudo = file_get_contents(__DIR__ . '/cron_analisar.php');
    $tem_validacao_token = strpos($conteudo, 'CRON_TOKEN') !== false;
    teste("Token de Segurança (CRON)", $tem_validacao_token, 
        "Script valida token para evitar execução não autorizada");
}

// ============================================
// 7. RESUMO E RECOMENDAÇÕES
// ============================================
echo "</div>"; // fecha container anterior

$porcentagem = $testes_realizados > 0 ? round(($testes_ok / $testes_realizados) * 100) : 0;
$status_geral = $testes_erro === 0 ? 'OK' : 'COM ERROS';

?>

<div class="container">
    <div class="summary">
        <h3>📊 Resumo dos Testes</h3>
        <table>
            <tr>
                <td>Total de Testes</td>
                <td><span class="status-badge status-ok"><?php echo $testes_realizados; ?></span></td>
            </tr>
            <tr>
                <td>✅ Testes OK</td>
                <td><span class="status-badge status-ok"><?php echo $testes_ok; ?></span></td>
            </tr>
            <tr>
                <td>❌ Testes com Falha</td>
                <td><span class="status-badge <?php echo $testes_erro > 0 ? 'status-error' : 'status-ok'; ?>"><?php echo $testes_erro; ?></span></td>
            </tr>
            <tr>
                <td>Taxa de Sucesso</td>
                <td><span class="status-badge status-ok"><?php echo $porcentagem; ?>%</span></td>
            </tr>
            <tr>
                <td><strong>Status Geral</strong></td>
                <td><span class="status-badge <?php echo $testes_erro === 0 ? 'status-ok' : 'status-error'; ?>"><?php echo $status_geral; ?></span></td>
            </tr>
        </table>
    </div>

    <h2>🚀 Próximos Passos</h2>
    
    <div class="test-item pass">
        <div class="test-title">✅ 1. Validar token de segurança</div>
        <div class="test-desc">
            No arquivo <strong>config.php</strong>, defina um token seguro:
        </div>
        <div class="code-block">define('CRON_TOKEN', 'seu_token_muito_secreto_aqui_com_muitos_caracteres');</div>
    </div>

    <div class="test-item pass">
        <div class="test-title">✅ 2. Configurar CRON Job</div>
        <div class="test-desc">
            No seu cPanel ou via SSH, configure o agendador para executar a cada 5 minutos:
        </div>
        <div class="code-block">*/5 * * * * curl -s http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_aqui_com_muitos_caracteres > /dev/null 2>&1</div>
        <div class="test-desc">
            <strong>Ou via wget:</strong>
        </div>
        <div class="code-block">*/5 * * * * wget -O - http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_aqui_com_muitos_caracteres > /dev/null 2>&1</div>
    </div>

    <div class="test-item pass">
        <div class="test-title">✅ 3. Testar Manualmente</div>
        <div class="test-desc">
            Acesse a URL do CRON manualmente para verificar:
        </div>
        <div class="code-block">http://seu-dominio.com/cron_analisar.php?token=seu_token_muito_secreto_aqui_com_muitos_caracteres</div>
    </div>

    <div class="test-item pass">
        <div class="test-title">✅ 4. Consultar Documentação</div>
        <div class="test-desc">
            Leia o arquivo <strong>CRON_SETUP.md</strong> para informações detalhadas sobre:
        </div>
        <ul>
            <li>Configuração em diferentes hosts</li>
            <li>Troubleshooting comum</li>
            <li>Monitoramento de candidaturas</li>
            <li>Otimizações futuras</li>
        </ul>
    </div>

    <h2>🧪 Teste da Candidatura</h2>
    
    <div class="test-item">
        <div class="test-title">📝 Fluxo de Teste</div>
        <div class="test-desc">
            <ol>
                <li>Submeta uma candidatura no formulário (qualquer currículo PDF/DOCX)</li>
                <li>Verifique se recebe confirmação imediatamente</li>
                <li>Verifique no banco: <code>SELECT * FROM candidatura WHERE status = 'Pendente Análise';</code></li>
                <li>Aguarde 5 minutos ou execute o CRON manualmente</li>
                <li>Verifique se o status mudou para 'Análise Completa' com assertividade e feedback</li>
            </ol>
        </div>
    </div>

    <h2>📚 Arquivos Criados/Modificados</h2>
    
    <table>
        <tr>
            <th>Arquivo</th>
            <th>Ação</th>
            <th>Descrição</th>
        </tr>
        <tr>
            <td><strong>cron_analisar.php</strong></td>
            <td><span class="status-badge status-ok">NOVO</span></td>
            <td>Script executado pelo CRON a cada 5-15 minutos</td>
        </tr>
        <tr>
            <td><strong>api_candidatura.php</strong></td>
            <td><span class="status-badge status-ok">MODIFICADO</span></td>
            <td>Refatorado para não chamar Gemini (apenas registra candidatura)</td>
        </tr>
        <tr>
            <td><strong>gemini_service.php</strong></td>
            <td><span class="status-badge status-ok">MODIFICADO</span></td>
            <td>Adicionada função analizarComApi() para processamento assíncrono</td>
        </tr>
        <tr>
            <td><strong>db_functions.php</strong></td>
            <td><span class="status-badge status-ok">MODIFICADO</span></td>
            <td>Adicionadas 4 novas funções para gerenciar status de candidaturas</td>
        </tr>
        <tr>
            <td><strong>CRON_SETUP.md</strong></td>
            <td><span class="status-badge status-ok">NOVO</span></td>
            <td>Documentação completa de configuração</td>
        </tr>
    </table>

    <h2>💡 Perguntas Frequentes</h2>
    
    <div class="test-item">
        <div class="test-title">P: Quanto tempo leva para a candidatura ser analisada?</div>
        <div class="test-desc">
            R: Depende da frequência do CRON. Se configurado para executar a cada 5 minutos, 
            a análise começará em no máximo 5 minutos, levando alguns segundos com Gemini.
        </div>
    </div>

    <div class="test-item">
        <div class="test-title">P: É seguro expor o CRON_TOKEN na URL?</div>
        <div class="test-desc">
            R: Recomenda-se usar HTTPS. Se usar HTTP, o token pode ser interceptado. 
            Alternativamente, configure o CRON apenas em localhost (127.0.0.1).
        </div>
    </div>

    <div class="test-item">
        <div class="test-title">P: Posso processar mais de 10 candidaturas por vez?</div>
        <div class="test-desc">
            R: Sim! Modifique a chamada de getCandidaturasPendentes() em cron_analisar.php 
            aumentando o parâmetro $limit (máximo 100 por defesa).
        </div>
    </div>

    <h2>🎯 Benefícios da Nova Arquitetura</h2>
    
    <ul>
        <li>✅ Usuário recebe resposta imediatamente</li>
        <li>✅ Servidor não fica bloqueado esperando Gemini</li>
        <li>✅ Falhas da IA não afetam experiência do usuário</li>
        <li>✅ Fácil de escalar e adicionar retries automáticos</li>
        <li>✅ Logs centralizados e monitoráveis</li>
        <li>✅ Separação clara de responsabilidades</li>
    </ul>

</div>

</body>
</html>

<?php
ob_end_flush();
?>
