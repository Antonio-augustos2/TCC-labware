<?php
/**
 * Funções de Banco de Dados - LabWare
 * Gerenciar vagas e candidatos
 */

require_once 'config.php';

function ensureFeedbackTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS feedback (
        id_feedback INT AUTO_INCREMENT PRIMARY KEY,
        autor VARCHAR(150) NOT NULL,
        cargo VARCHAR(150) NOT NULL,
        mensagem TEXT NOT NULL,
        id_rh_autor INT NULL,
        destaque TINYINT(1) NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $created = $conn->query($sql);
    $conn->query('ALTER TABLE feedback ADD COLUMN IF NOT EXISTS id_rh_autor INT NULL');
    return $created;
}

function getFeedbacksPublicos($conn) {
    ensureFeedbackTable($conn);
    $result = $conn->query("SELECT id_feedback, autor, cargo, mensagem, destaque FROM feedback WHERE ativo = 1 ORDER BY criado_em DESC, id_feedback DESC");
    if (!$result) {
        return [];
    }

    $feedbacks = [];
    while ($row = $result->fetch_assoc()) {
        $feedbacks[] = [
            'id' => (int) $row['id_feedback'],
            'author' => $row['autor'],
            'role' => $row['cargo'],
            'message' => $row['mensagem'],
            'highlight' => (bool) $row['destaque']
        ];
    }

    return $feedbacks;
}

function getFeedbacksAdmin($conn) {
    ensureFeedbackTable($conn);
    $result = $conn->query("SELECT id_feedback, autor, cargo, mensagem, id_rh_autor, destaque, ativo, criado_em FROM feedback ORDER BY criado_em DESC, id_feedback DESC");
    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function getFeedbackById($conn, $id) {
    ensureFeedbackTable($conn);
    $id = (int) $id;
    $result = $conn->query("SELECT id_feedback, autor, cargo, mensagem, id_rh_autor, destaque FROM feedback WHERE id_feedback = $id LIMIT 1");
    return $result ? $result->fetch_assoc() : null;
}

function createFeedback($conn, $idRhAutor, $autor, $cargo, $mensagem, $destaque = false) {
    ensureFeedbackTable($conn);
    $stmt = $conn->prepare('INSERT INTO feedback (id_rh_autor, autor, cargo, mensagem, destaque) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }

    $destaque = $destaque ? 1 : 0;
    $idRhAutor = (int) $idRhAutor;
    $stmt->bind_param('isssi', $idRhAutor, $autor, $cargo, $mensagem, $destaque);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function updateFeedback($conn, $id, $idRhAutor, $autor, $cargo, $mensagem, $destaque = false) {
    ensureFeedbackTable($conn);
    $stmt = $conn->prepare('UPDATE feedback SET autor = ?, cargo = ?, mensagem = ?, destaque = ? WHERE id_feedback = ? AND id_rh_autor = ?');
    if (!$stmt) {
        return false;
    }

    $destaque = $destaque ? 1 : 0;
    $id = (int) $id;
    $idRhAutor = (int) $idRhAutor;
    $stmt->bind_param('sssiii', $autor, $cargo, $mensagem, $destaque, $id, $idRhAutor);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function deleteFeedback($conn, $id) {
    ensureFeedbackTable($conn);
    $id = (int) $id;
    return $conn->query("DELETE FROM feedback WHERE id_feedback = $id");
}

/**
 * Garantir colunas de tipo e localidade na tabela vaga.
 */
function ensureVagaMetadataColumns($conn) {
    $queries = [
        "ALTER TABLE vaga ADD COLUMN IF NOT EXISTS tipo VARCHAR(100) NOT NULL DEFAULT 'Desenvolvedor • Remoto'",
        "ALTER TABLE vaga ADD COLUMN IF NOT EXISTS localizacao VARCHAR(150) NOT NULL DEFAULT 'Remoto'"
    ];

    foreach ($queries as $sql) {
        $conn->query($sql);
    }
}

/**
 * Garantir coluna de armazenamento de arquivo na tabela candidatura.
 */
function ensureCandidaturaArquivoColumn($conn) {
    $sql = "ALTER TABLE candidatura ADD COLUMN IF NOT EXISTS arquivo_path VARCHAR(255) DEFAULT NULL";
    $conn->query($sql);
}

/**
 * Garantir coluna de currículo (BLOB) na tabela candidato.
 */
function ensureCandidatoCurriculoColumn($conn) {
    $sqlAdd = "ALTER TABLE candidato ADD COLUMN IF NOT EXISTS curriculo LONGBLOB DEFAULT NULL";
    $conn->query($sqlAdd);

    $sqlModify = "ALTER TABLE candidato MODIFY COLUMN curriculo LONGBLOB NULL";
    $conn->query($sqlModify);
}

/**
 * Garantir coluna de status na tabela candidato.
 */
function ensureCandidatoStatusColumn($conn) {
    $sqlAdd = "ALTER TABLE candidato ADD COLUMN IF NOT EXISTS status VARCHAR(50) NOT NULL DEFAULT 'Pendente'";
    $conn->query($sqlAdd);

    $sqlModify = "ALTER TABLE candidato MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'Pendente'";
    $conn->query($sqlModify);
}

/**
 * Obter todas as vagas abertas para a página pública.
 */
function getAllVagas($conn) {
    ensureVagaMetadataColumns($conn);

    $sql = "SELECT id_vaga, titulo, descricao, status, tipo, localizacao FROM vaga WHERE status = 'Aberta' ORDER BY id_vaga DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        return [];
    }
    
    $vagas = [];
    while ($row = $result->fetch_assoc()) {
        $vagas[] = [
            'id' => $row['id_vaga'],
            'title' => $row['titulo'],
            'type' => $row['tipo'] ?? 'Desenvolvedor • Remoto',
            'location' => $row['localizacao'] ?? 'Remoto',
            'description' => $row['descricao'],
            'status' => $row['status'] ?? 'Aberta'
        ];
    }
    
    return $vagas;
}

/**
 * Obter todas as vagas para o painel administrativo.
 */
function getAllVagasAdmin($conn) {
    ensureVagaMetadataColumns($conn);

    $sql = "SELECT id_vaga, titulo, descricao, status, tipo, localizacao FROM vaga ORDER BY id_vaga DESC";
    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    $vagas = [];
    while ($row = $result->fetch_assoc()) {
        $vagas[] = [
            'id' => $row['id_vaga'],
            'title' => $row['titulo'],
            'type' => $row['tipo'] ?? 'Desenvolvedor • Remoto',
            'location' => $row['localizacao'] ?? 'Remoto',
            'description' => $row['descricao'],
            'status' => $row['status'] ?? 'Aberta'
        ];
    }

    return $vagas;
}

/**
 * Obter vaga por ID
 */
function getVagaById($conn, $id) {
    ensureVagaMetadataColumns($conn);

    $id = (int)$id;
    $sql = "SELECT id_vaga, titulo, descricao, status, tipo, localizacao FROM vaga WHERE id_vaga = $id";
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        return null;
    }
    
    $row = $result->fetch_assoc();
    return [
        'id' => $row['id_vaga'],
        'title' => $row['titulo'],
        'type' => $row['tipo'] ?? 'Desenvolvedor • Remoto',
        'location' => $row['localizacao'] ?? 'Remoto',
        'description' => $row['descricao'],
        'status' => $row['status']
    ];
}

/**
 * Criar nova vaga
 */
function createVaga($conn, $titulo, $descricao, $status = 'Aberta', $tipo = 'Desenvolvedor • Remoto', $localizacao = 'Remoto', $empresa_id = 1, $rh_id = 1) {
    ensureVagaMetadataColumns($conn);

    $titulo = $conn->real_escape_string($titulo);
    $descricao = $conn->real_escape_string($descricao);
    $status = $conn->real_escape_string($status);
    $tipo = $conn->real_escape_string($tipo);
    $localizacao = $conn->real_escape_string($localizacao);
    
    $sql = "INSERT INTO vaga (titulo, descricao, status, tipo, localizacao, id_empresa, id_rh_responsavel) 
            VALUES ('$titulo', '$descricao', '$status', '$tipo', '$localizacao', $empresa_id, $rh_id)";
    
    if ($conn->query($sql)) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Alterar somente o status de uma vaga.
 */
function setVagaStatus($conn, $id, $status) {
    ensureVagaMetadataColumns($conn);

    $id = (int)$id;
    $status = in_array($status, ['Aberta', 'Encerrada'], true) ? $status : 'Aberta';
    $status = $conn->real_escape_string($status);

    $sql = "UPDATE vaga SET status = '$status' WHERE id_vaga = $id";
    return $conn->query($sql);
}

/**
 * Atualizar vaga
 */
function updateVaga($conn, $id, $titulo, $descricao, $status = 'Aberta', $tipo = 'Desenvolvedor • Remoto', $localizacao = 'Remoto') {
    ensureVagaMetadataColumns($conn);

    $id = (int)$id;
    $status = in_array($status, ['Aberta', 'Encerrada'], true) ? $status : 'Aberta';

    $updates = ["status = '" . $conn->real_escape_string($status) . "'"];

    if ($titulo !== null && $titulo !== '') {
        $updates[] = "titulo = '" . $conn->real_escape_string($titulo) . "'";
    }
    if ($descricao !== null && $descricao !== '') {
        $updates[] = "descricao = '" . $conn->real_escape_string($descricao) . "'";
    }
    if ($tipo !== null && $tipo !== '') {
        $updates[] = "tipo = '" . $conn->real_escape_string($tipo) . "'";
    }
    if ($localizacao !== null && $localizacao !== '') {
        $updates[] = "localizacao = '" . $conn->real_escape_string($localizacao) . "'";
    }

    $sql = "UPDATE vaga SET " . implode(', ', $updates) . " WHERE id_vaga = $id";
    return $conn->query($sql);
}

/**
 * Salvar o resultado da análise do currículo para uma candidatura.
 */
function updateCandidaturaAnalise($conn, $idCandidatura, $assertividade, $feedback, $arquivoPath = null) {
    $idCandidatura = (int) $idCandidatura;
    ensureCandidatoStatusColumn($conn);

    $stmt = $conn->prepare("SELECT id_candidato FROM candidatura WHERE id_candidatura = ? LIMIT 1");
    if (!$stmt) {
        error_log('❌ Erro ao preparar consulta de candidatura: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('i', $idCandidatura);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $candidatura = $resultado->fetch_assoc();
    $stmt->close();

    if (!$candidatura) {
        error_log('⚠ Candidatura não encontrada para atualizar análise: ' . $idCandidatura);
        return false;
    }

    $idCandidato = (int) $candidatura['id_candidato'];
    $assertividade = max(0, min(100, (float) $assertividade));
    $feedback = $conn->real_escape_string(trim((string) $feedback));
    $novoStatus = 'Pendente';

    $updateParts = ["assertividade = $assertividade", "feedback = '$feedback'"];
    if ($arquivoPath !== null) {
        $arquivoPath = $conn->real_escape_string($arquivoPath);
        $updateParts[] = "arquivo_path = '$arquivoPath'";
    }

    $sql = "UPDATE candidatura SET " . implode(', ', $updateParts) . " WHERE id_candidatura = $idCandidatura";
    $ok = $conn->query($sql);

    if ($ok) {
        updateCandidatoStatus($conn, $idCandidato, $novoStatus);
        return true;
    }

    updateCandidatoStatus($conn, $idCandidato, 'Em análise');
    return false;
}

/**
 * Deletar vaga
 */
function deleteVaga($conn, $id) {
    $id = (int)$id;
    
    // Primeiro, deletar candidaturas relacionadas
    $sql1 = "DELETE FROM candidatura WHERE id_vaga = $id";
    $conn->query($sql1);
    
    // Depois, deletar a vaga
    $sql2 = "DELETE FROM vaga WHERE id_vaga = $id";
    
    return $conn->query($sql2);
}

/**
 * Registrar acesso à vaga
 */
function registerAccess($conn, $job_id, $job_title) {
    $job_id = (int)$job_id;
    $job_title = $conn->real_escape_string($job_title);
    
    // Criar uma tabela de acessos se não existir
    $sql = "CREATE TABLE IF NOT EXISTS acesso (
        id_acesso INT AUTO_INCREMENT PRIMARY KEY,
        id_vaga INT NOT NULL,
        titulo_vaga VARCHAR(150),
        tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga',
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($sql);
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga'");
    
    // Registrar o acesso
    $sql = "INSERT INTO acesso (id_vaga, titulo_vaga, tipo_acesso) VALUES ($job_id, '$job_title', 'pagina_vaga')";
    return $conn->query($sql);
}

/**
 * Registrar exclusivamente um clique no botão "Saiba mais".
 */
function registerSaibaMaisClick($conn, $job_id, $job_title) {
    $job_id = (int) $job_id;
    $job_title = $conn->real_escape_string($job_title);
    $sql = "CREATE TABLE IF NOT EXISTS acesso (
        id_acesso INT AUTO_INCREMENT PRIMARY KEY,
        id_vaga INT NOT NULL,
        titulo_vaga VARCHAR(150),
        tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga',
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga'");
    ensureAccessVisitorColumn($conn);
    $visitorId = getAccessVisitorId($conn);

    return $conn->query("INSERT INTO acesso (id_vaga, titulo_vaga, tipo_acesso, visitor_id) VALUES ($job_id, '$job_title', 'saiba_mais', '$visitorId') ON DUPLICATE KEY UPDATE id_acesso = id_acesso");
}

function getAccessVisitorId($conn) {
    $visitorId = $_COOKIE['labware_visitor_id'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/', $visitorId)) {
        $visitorId = bin2hex(random_bytes(32));
        setcookie('labware_visitor_id', $visitorId, [
            'expires' => time() + (86400 * 365),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    return $conn->real_escape_string($visitorId);
}

function ensureAccessVisitorColumn($conn) {
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS visitor_id CHAR(64) NULL");
    $result = $conn->query("SHOW INDEX FROM acesso WHERE Key_name = 'uq_acesso_vaga_visitante'");
    if ($result && $result->num_rows === 0) {
        $conn->query('ALTER TABLE acesso ADD UNIQUE KEY uq_acesso_vaga_visitante (id_vaga, visitor_id)');
    }
}

/**
 * Registrar uma candidatura enviada para a vaga.
 */
function registerCandidaturaClick($conn, $job_id, $job_title) {
    $job_id = (int) $job_id;
    $job_title = $conn->real_escape_string($job_title);
    $sql = "CREATE TABLE IF NOT EXISTS acesso (
        id_acesso INT AUTO_INCREMENT PRIMARY KEY,
        id_vaga INT NOT NULL,
        titulo_vaga VARCHAR(150),
        tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga',
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga'");
    ensureAccessVisitorColumn($conn);
    $visitorId = getAccessVisitorId($conn);

    return $conn->query("INSERT INTO acesso (id_vaga, titulo_vaga, tipo_acesso, visitor_id) VALUES ($job_id, '$job_title', 'candidatura', '$visitorId') ON DUPLICATE KEY UPDATE id_acesso = id_acesso");
}

/**
 * Obter histórico de acessos
 */
function getAccessLog($conn, $limit = 20) {
    $limit = (int)$limit;
    
    // Criar tabela de acessos se não existir
    $sql = "CREATE TABLE IF NOT EXISTS acesso (
        id_acesso INT AUTO_INCREMENT PRIMARY KEY,
        id_vaga INT NOT NULL,
        titulo_vaga VARCHAR(150),
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($sql);
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga'");
    
    $sql = "SELECT id_vaga, titulo_vaga, data_acesso FROM acesso ORDER BY data_acesso DESC LIMIT $limit";
    $result = $conn->query($sql);
    
    if (!$result) {
        return [];
    }
    
    $accesses = [];
    while ($row = $result->fetch_assoc()) {
        $accesses[] = [
            'job_id' => $row['id_vaga'],
            'job_title' => $row['titulo_vaga'],
            'timestamp' => $row['data_acesso']
        ];
    }
    
    return $accesses;
}

/**
 * Obter quantidade de cliques em "Saiba mais" por vaga.
 */
function getAccessCounts($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS acesso (
        id_acesso INT AUTO_INCREMENT PRIMARY KEY,
        id_vaga INT NOT NULL,
        titulo_vaga VARCHAR(150),
        tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga',
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    $conn->query($sql);
    $conn->query("ALTER TABLE acesso ADD COLUMN IF NOT EXISTS tipo_acesso VARCHAR(30) NOT NULL DEFAULT 'pagina_vaga'");
    ensureAccessVisitorColumn($conn);

    $result = $conn->query("SELECT id_vaga, COUNT(*) AS total FROM acesso WHERE tipo_acesso IN ('saiba_mais', 'candidatura') GROUP BY id_vaga");
    if (!$result) {
        return [];
    }

    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[(int) $row['id_vaga']] = (int) $row['total'];
    }

    return $counts;
}

/**
 * Salvar currículo (BLOB) para um candidato
 */
function saveCurriculoToCandidato($conn, $idCandidato, $curriculoConteudo) {
    $idCandidato = (int)$idCandidato;

    $stmt = $conn->prepare("UPDATE candidato SET curriculo = ? WHERE id_candidato = ?");

    if (!$stmt) {
        error_log('❌ Erro ao preparar statement para salvar currículo: ' . $conn->error);
        return false;
    }

    $blobNulo = null;
    $stmt->bind_param("bi", $blobNulo, $idCandidato);
    $stmt->send_long_data(0, $curriculoConteudo);

    $resultado = $stmt->execute();

    if ($resultado) {
        $tamanho = strlen($curriculoConteudo);
        error_log("✅ BLOB atualizado para candidato $idCandidato - Tamanho: " . number_format($tamanho) . " bytes");
    } else {
        error_log('❌ Erro ao atualizar currículo BLOB: ' . $stmt->error);
    }

    $stmt->close();

    return $resultado;
}

/**
 * Registrar candidatura com currículo em BLOB
 */
function registerCandidatura($conn, $nome, $email, $id_vaga, $curriculoConteudo, $caminhoArquivo, $statusCandidato = 'Em analise') {
    $id_vaga = (int)$id_vaga;
    ensureCandidatoStatusColumn($conn);
    $statusCandidato = in_array($statusCandidato, ['Pendente', 'Em analise'], true) ? $statusCandidato : 'Em analise';

    // ⚠️ VALIDAÇÃO: Candidatura DEVE ter arquivo para ser processada pelo CRON
    if (empty($caminhoArquivo)) {
        error_log('❌ Candidatura rejeitada: arquivo_path vazio ou null. Email: ' . $email);
        return false;
    }

    // Verificar se o candidato já existe
    $stmt = $conn->prepare('SELECT id_candidato FROM candidato WHERE email = ? LIMIT 1');
    if (!$stmt) {
        error_log('❌ Erro ao preparar consulta de candidato: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    if ($result->num_rows > 0) {
        $candidato = $result->fetch_assoc();
        $id_candidato = $candidato['id_candidato'];
        error_log("ℹ Candidato existente encontrado - Email: $email, ID: $id_candidato");
        updateCandidatoStatus($conn, $id_candidato, $statusCandidato);

        if ($curriculoConteudo !== null) {
            error_log("🔄 Atualizando BLOB do candidato $id_candidato...");
            saveCurriculoToCandidato($conn, $id_candidato, $curriculoConteudo);
        }
    } else {
        if ($curriculoConteudo !== null) {
            $tamanho = strlen($curriculoConteudo);
            error_log("📝 Novo candidato - Inserindo BLOB - Email: $email, Tamanho: " . number_format($tamanho) . " bytes");

            $stmt = $conn->prepare("INSERT INTO candidato (nome, email, status, curriculo) VALUES (?, ?, ?, ?)");

            if (!$stmt) {
                error_log('❌ Erro ao preparar statement para inserir candidato: ' . $conn->error);
                return false;
            }

            $blobNulo = null;
            $stmt->bind_param("sssb", $nome, $email, $statusCandidato, $blobNulo);
            $stmt->send_long_data(3, $curriculoConteudo);

            if (!$stmt->execute()) {
                error_log('❌ Erro ao inserir candidato com currículo BLOB: ' . $stmt->error);
                $stmt->close();
                return false;
            }

            $id_candidato = $conn->insert_id;
            $stmt->close();
            error_log("✅ Candidato criado com BLOB - ID: $id_candidato, Email: $email, BLOB: " . number_format($tamanho) . " bytes");
        } else {
            error_log("📝 Novo candidato sem BLOB - Email: $email");
            $stmt = $conn->prepare('INSERT INTO candidato (nome, email, status) VALUES (?, ?, ?)');
            if (!$stmt) {
                error_log('❌ Erro ao preparar candidato sem BLOB: ' . $conn->error);
                return false;
            }

            $stmt->bind_param('sss', $nome, $email, $statusCandidato);
            if ($stmt->execute()) {
                $id_candidato = $conn->insert_id;
                error_log("✅ Candidato criado - ID: $id_candidato");
            } else {
                error_log('❌ Erro ao inserir candidato: ' . $stmt->error);
                $stmt->close();
                return false;
            }
            $stmt->close();
        }
    }

    if ($caminhoArquivo !== null) {
        $caminhoArquivo = $conn->real_escape_string($caminhoArquivo);
        $sql = "INSERT INTO candidatura (id_candidato, id_vaga, status, arquivo_path) VALUES ($id_candidato, $id_vaga, 'Pendente', '$caminhoArquivo')";
    } else {
        $sql = "INSERT INTO candidatura (id_candidato, id_vaga, status) VALUES ($id_candidato, $id_vaga, 'Pendente')";
    }

    if (!$conn->query($sql)) {
        error_log('❌ Erro ao registrar candidatura: ' . $conn->error);
        return false;
    }

    $idCandidatura = $conn->insert_id;
    error_log("✅ Candidatura registrada - ID: $idCandidatura, Candidato: $id_candidato, Vaga: $id_vaga");
    return $idCandidatura;
}

/**
 * Atualizar status do candidato.
 */
function updateCandidatoStatus($conn, $idCandidato, $status) {
    ensureCandidatoStatusColumn($conn);

    $idCandidato = (int) $idCandidato;
    $status = in_array($status, ['Pendente', 'Em analise'], true) ? $status : 'Pendente';
    $status = $conn->real_escape_string($status);

    $sql = "UPDATE candidato SET status = '$status' WHERE id_candidato = $idCandidato";
    return $conn->query($sql);
}

/**
 * Obter currículo (BLOB) de um candidato pelo ID
 */
function getCurriculoFromDatabase($conn, $idCandidato) {
    $idCandidato = (int)$idCandidato;
    
    $stmt = $conn->prepare("SELECT curriculo FROM candidato WHERE id_candidato = ?");
    
    if (!$stmt) {
        error_log('❌ Erro ao preparar statement: ' . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $idCandidato);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row && !empty($row['curriculo'])) {
        $tamanho = strlen($row['curriculo']);
        error_log("✅ Currículo BLOB recuperado - Candidato: $idCandidato, Tamanho: " . number_format($tamanho) . " bytes");
        return $row['curriculo'];
    }
    
    error_log("⚠ Nenhum currículo BLOB encontrado para candidato $idCandidato");
    return null;
}

/**
 * Verificar se um candidato tem currículo no BLOB
 */
function hasCurriculoInDatabase($conn, $idCandidato) {
    $idCandidato = (int)$idCandidato;
    
    $sql = "SELECT LENGTH(curriculo) as tamanho FROM candidato WHERE id_candidato = $idCandidato";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $tamanho = (int)$row['tamanho'];
        
        if ($tamanho > 0) {
            error_log("✅ Candidato $idCandidato tem BLOB salvo - Tamanho: " . number_format($tamanho) . " bytes");
            return true;
        }
    }
    
    error_log("⚠ Candidato $idCandidato não tem BLOB salvo");
    return false;
}

/**
 * Obter candidaturas com os dados do candidato e da vaga relacionada.
 */
function getCandidaturasComAnalise($conn) {
    ensureCandidatoStatusColumn($conn);

    $sql = "SELECT
                c.id_candidatura,
                c.id_candidato,
                c.status AS candidatura_status,
                c.assertividade,
                c.feedback,
                ca.nome AS candidato_nome,
                ca.email AS candidato_email,
                ca.status AS candidato_status,
                v.id_vaga,
                v.titulo AS vaga_titulo
            FROM candidatura c
            INNER JOIN candidato ca ON ca.id_candidato = c.id_candidato
            INNER JOIN vaga v ON v.id_vaga = c.id_vaga
            ORDER BY c.id_candidatura DESC";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $candidaturas = [];
    while ($row = $result->fetch_assoc()) {
        $candidaturas[] = $row;
    }

    return $candidaturas;
}

/**
 * Buscar o id do candidato a partir da candidatura.
 */
function getCandidatoIdByCandidatura($conn, $idCandidatura) {
    $idCandidatura = (int) $idCandidatura;

    $stmt = $conn->prepare("SELECT id_candidato FROM candidatura WHERE id_candidatura = ? LIMIT 1");
    if (!$stmt) {
        error_log('❌ Erro ao preparar consulta de candidatura: ' . $conn->error);
        return null;
    }

    $stmt->bind_param('i', $idCandidatura);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['id_candidato'] ?? null;
}

/**
 * Garantir coluna de status na tabela candidatura (para processamento assíncrono).
 */
function ensureCandidaturaStatusColumn($conn) {
    $sql = "ALTER TABLE candidatura ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'Pendente Análise'";
    $conn->query($sql);
}

/**
 * Buscar candidaturas pendentes de análise com Gemini.
 * @param $conn - Conexão com banco de dados
 * @param $limit - Limite de candidaturas a retornar (padrão 10)
 * @return array - Array de candidaturas pendentes
 */
function getCandidaturasPendentes($conn, $limit = 10) {
    ensureCandidaturaStatusColumn($conn);
    
    $limit = max(1, min((int)$limit, 100));
    
    $sql = "SELECT 
                c.id_candidatura,
                c.id_candidato,
                c.id_vaga,
                c.arquivo_path,
                c.status,
                ca.nome AS candidato_nome,
                ca.email AS candidato_email,
                v.titulo AS vaga_titulo,
                v.descricao AS vaga_descricao
            FROM candidatura c
            INNER JOIN candidato ca ON ca.id_candidato = c.id_candidato
            INNER JOIN vaga v ON v.id_vaga = c.id_vaga
            WHERE c.status = 'Pendente Análise'
            ORDER BY c.id_candidatura ASC
            LIMIT $limit";
    
    $result = $conn->query($sql);
    if (!$result) {
        error_log('❌ Erro ao buscar candidaturas pendentes: ' . $conn->error);
        return [];
    }
    
    $candidaturas = [];
    while ($row = $result->fetch_assoc()) {
        $candidaturas[] = $row;
    }
    
    return $candidaturas;
}

/**
 * Buscar candidatos em análise com suas candidaturas relacionadas.
 * Processa TODOS os candidatos que estão com status 'Em analise' e suas possíveis múltiplas candidaturas.
 * 
 * @param $conn - Conexão com banco de dados
 * @param $limit - Limite de candidatos a retornar (padrão 10)
 * @return array - Array de candidatos com status 'Em analise' e suas candidaturas
 */
function getCandidatosEmAnalise($conn, $limit = 10) {
    ensureCandidatoStatusColumn($conn);
    
    $limit = max(1, min((int)$limit, 100));
    
    $sql = "SELECT 
                ca.id_candidato,
                ca.nome AS candidato_nome,
                ca.email AS candidato_email,
                ca.status AS candidato_status,
                c.id_candidatura,
                c.id_vaga,
                c.arquivo_path,
                c.status AS candidatura_status,
                c.assertividade,
                c.feedback,
                v.titulo AS vaga_titulo,
                v.descricao AS vaga_descricao
            FROM candidato ca
            INNER JOIN candidatura c ON c.id_candidato = ca.id_candidato
            INNER JOIN vaga v ON v.id_vaga = c.id_vaga
            WHERE ca.status = 'Em analise' AND c.arquivo_path IS NOT NULL AND c.arquivo_path != ''
            ORDER BY ca.id_candidato ASC, c.id_candidatura ASC
            LIMIT $limit";
    
    $result = $conn->query($sql);
    if (!$result) {
        error_log('❌ Erro ao buscar candidatos em análise: ' . $conn->error);
        return [];
    }
    
    $candidatos = [];
    while ($row = $result->fetch_assoc()) {
        $candidatos[] = $row;
    }
    
    return $candidatos;
}

/**
 * Atualizar status de uma candidatura.
 * @param $conn - Conexão com banco de dados
 * @param $idCandidatura - ID da candidatura
 * @param $novoStatus - Novo status ('Pendente Análise', 'Análise Completa', 'Análise Erro')
 * @return bool - Retorna true se atualizado com sucesso
 */
function updateCandidaturaStatus($conn, $idCandidatura, $novoStatus) {
    ensureCandidaturaStatusColumn($conn);
    
    $idCandidatura = (int)$idCandidatura;
    $novoStatus = $conn->real_escape_string(trim((string)$novoStatus));
    
    // Validar status
    $statusValidos = ['Pendente Análise', 'Análise Completa', 'Análise Erro'];
    if (!in_array($novoStatus, $statusValidos, true)) {
        error_log("⚠ Status inválido para candidatura: $novoStatus");
        return false;
    }
    
    $sql = "UPDATE candidatura SET status = '$novoStatus' WHERE id_candidatura = $idCandidatura";
    
    if ($conn->query($sql)) {
        error_log("✅ Status da candidatura $idCandidatura atualizado para: $novoStatus");
        return true;
    }
    
    error_log('❌ Erro ao atualizar status da candidatura ' . $idCandidatura . ': ' . $conn->error);
    return false;
}

/**
 * Buscar detalhes completos de uma candidatura (para processamento via CRON).
 * @param $conn - Conexão com banco de dados
 * @param $idCandidatura - ID da candidatura
 * @return array|null - Dados da candidatura ou null se não encontrada
 */
function getCandidaturaComDetalhes($conn, $idCandidatura) {
    ensureCandidaturaStatusColumn($conn);
    
    $idCandidatura = (int)$idCandidatura;
    
    $stmt = $conn->prepare("SELECT 
                                c.id_candidatura,
                                c.id_candidato,
                                c.id_vaga,
                                c.arquivo_path,
                                c.status,
                                ca.nome AS candidato_nome,
                                ca.email AS candidato_email,
                                v.titulo AS vaga_titulo,
                                v.descricao AS vaga_descricao
                            FROM candidatura c
                            INNER JOIN candidato ca ON ca.id_candidato = c.id_candidato
                            INNER JOIN vaga v ON v.id_vaga = c.id_vaga
                            WHERE c.id_candidatura = ?
                            LIMIT 1");
    
    if (!$stmt) {
        error_log('❌ Erro ao preparar consulta de candidatura com detalhes: ' . $conn->error);
        return null;
    }
    
    $stmt->bind_param('i', $idCandidatura);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row ?? null;
}
