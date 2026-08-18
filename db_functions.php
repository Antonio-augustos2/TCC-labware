<?php
/**
 * Funções de Banco de Dados - LabWare
 * Gerenciar vagas e candidatos
 */

require_once 'config.php';

/**
 * Obter todas as vagas
 */
function getAllVagas($conn) {
    $sql = "SELECT id_vaga, titulo, descricao, status FROM vaga WHERE status = 'Aberta' ORDER BY id_vaga DESC";
    $result = $conn->query($sql);
    
    if (!$result) {
        return [];
    }
    
    $vagas = [];
    while ($row = $result->fetch_assoc()) {
        $vagas[] = [
            'id' => $row['id_vaga'],
            'title' => $row['titulo'],
            'type' => 'Desenvolvedor • Remoto', // Pode ser expandido na tabela vaga
            'location' => 'Remoto',
            'description' => $row['descricao']
        ];
    }
    
    return $vagas;
}

/**
 * Obter vaga por ID
 */
function getVagaById($conn, $id) {
    $id = (int)$id;
    $sql = "SELECT id_vaga, titulo, descricao, status FROM vaga WHERE id_vaga = $id";
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows === 0) {
        return null;
    }
    
    $row = $result->fetch_assoc();
    return [
        'id' => $row['id_vaga'],
        'title' => $row['titulo'],
        'type' => 'Desenvolvedor • Remoto',
        'location' => 'Remoto',
        'description' => $row['descricao'],
        'status' => $row['status']
    ];
}

/**
 * Criar nova vaga
 */
function createVaga($conn, $titulo, $descricao, $status = 'Aberta', $empresa_id = 1, $rh_id = 1) {
    $titulo = $conn->real_escape_string($titulo);
    $descricao = $conn->real_escape_string($descricao);
    $status = $conn->real_escape_string($status);
    
    $sql = "INSERT INTO vaga (titulo, descricao, status, id_empresa, id_rh_responsavel) 
            VALUES ('$titulo', '$descricao', '$status', $empresa_id, $rh_id)";
    
    if ($conn->query($sql)) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Atualizar vaga
 */
function updateVaga($conn, $id, $titulo, $descricao, $status = 'Aberta') {
    $id = (int)$id;
    $titulo = $conn->real_escape_string($titulo);
    $descricao = $conn->real_escape_string($descricao);
    $status = $conn->real_escape_string($status);
    
    $sql = "UPDATE vaga SET titulo = '$titulo', descricao = '$descricao', status = '$status' WHERE id_vaga = $id";
    
    return $conn->query($sql);
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
        data_acesso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_vaga) REFERENCES vaga(id_vaga)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $conn->query($sql);
    
    // Registrar o acesso
    $sql = "INSERT INTO acesso (id_vaga, titulo_vaga) VALUES ($job_id, '$job_title')";
    return $conn->query($sql);
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
 * Registrar candidatura
 */
function registerCandidatura($conn, $nome, $email, $id_vaga) {
    $nome = $conn->real_escape_string($nome);
    $email = $conn->real_escape_string($email);
    $id_vaga = (int)$id_vaga;
    
    // Verificar se o candidato já existe
    $sql = "SELECT id_candidato FROM candidato WHERE email = '$email'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $candidato = $result->fetch_assoc();
        $id_candidato = $candidato['id_candidato'];
    } else {
        // Criar novo candidato
        $sql = "INSERT INTO candidato (nome, email) VALUES ('$nome', '$email')";
        if ($conn->query($sql)) {
            $id_candidato = $conn->insert_id;
        } else {
            return false;
        }
    }
    
    // Registrar candidatura
    $sql = "INSERT INTO candidatura (id_candidato, id_vaga, status) 
            VALUES ($id_candidato, $id_vaga, 'Pendente')";
    
    return $conn->query($sql);
}
