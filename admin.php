<?php
session_start();

require_once 'config.php';
require_once 'db_functions.php';

$loginError = '';
$editJob = null;
$searchQuery = trim($_GET['search'] ?? '');
$loggedIn = false;
$filteredJobs = [];
$accessLog = [];

// Verificar login
if (!empty($_SESSION['admin_logged_in'])) {
    $loggedIn = true;
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email === 'admin@labware.com' && $password === 'senha123') {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginError = 'E-mail ou senha incorretos.';
}

if ($loggedIn) {
    // Obter todas as vagas
    $jobs = getAllVagas($conn);
    
    // Deletar vaga
    if (isset($_GET['delete'])) {
        $deleteId = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
        if ($deleteId !== false && $deleteId !== null) {
            deleteVaga($conn, $deleteId);
            header('Location: admin.php');
            exit;
        }
    }

    // Editar vaga
    if (isset($_GET['edit'])) {
        $editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        if ($editId !== false && $editId !== null) {
            $editJob = getVagaById($conn, $editId);
        }
    }

    // Salvar vaga (criar ou atualizar)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);

        if ($title !== '' && $description !== '') {
            if ($jobId) {
                // Atualizar vaga existente
                updateVaga($conn, $jobId, $title, $description, 'Aberta');
            } else {
                // Criar nova vaga
                createVaga($conn, $title, $description, 'Aberta');
            }
            header('Location: admin.php');
            exit;
        }
    }

    // Filtrar vagas por busca
    $filteredJobs = $jobs;
    if ($searchQuery !== '') {
        $filteredJobs = array_filter($jobs, function ($job) use ($searchQuery) {
            return stripos($job['title'], $searchQuery) !== false
                || stripos($job['description'], $searchQuery) !== false;
        });
    }

    // Obter histórico de acessos
    $accessLog = getAccessLog($conn, 20);
}

$pageTitle = 'Admin LabWare';
include 'header.php';
?>
<main class="container admin-page">
    <?php if (!$loggedIn): ?>
        <section class="admin-login">
            <h2>Login de Administrador</h2>
            <p>Entre para gerenciar vagas e visualizar o histórico de acessos.</p>
            <?php if ($loginError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="post" action="admin.php" class="admin-form">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required placeholder="admin@labware.com">
                </div>
                <div class="form-group">
                    <label for="password">Senha</label>
                    <input type="password" id="password" name="password" required placeholder="Senha">
                </div>
                <button type="submit" class="btn">Entrar</button>
            </form>
        </section>
    <?php else: ?>
        <section class="admin-dashboard">
            <div class="admin-header">
                <h2>Painel Administrativo</h2>
                <p>Gerencie vagas abertas e acompanhe o acesso dos candidatos.</p>
            </div>
            <div class="search-bar">
                <form method="get" action="admin.php" style="display:flex; width:100%; gap:0.75rem;">
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Buscar vagas por título, tipo ou localidade">
                    <button type="submit" class="btn">Buscar</button>
                </form>
                <?php if ($searchQuery !== ''): ?>
                    <a href="admin.php" class="btn btn-outline">Limpar busca</a>
                <?php endif; ?>
            </div>
            <div class="admin-grid">
                <div class="admin-card admin-form-card">
                    <h3><?= $editJob ? 'Editar vaga' : 'Criar nova vaga' ?></h3>
                    <form method="post" action="admin.php" class="admin-form">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="job_id" value="<?= $editJob['id'] ?? '' ?>">
                        <div class="form-group">
                            <label for="title">Título da vaga</label>
                            <input type="text" id="title" name="title" value="<?= htmlspecialchars($editJob['title'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="type">Nível e formato</label>
                            <input type="text" id="type" name="type" value="<?= htmlspecialchars($editJob['type'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Localidade</label>
                            <input type="text" id="location" name="location" value="<?= htmlspecialchars($editJob['location'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="description">Descrição</label>
                            <textarea id="description" name="description" rows="4" required><?= htmlspecialchars($editJob['description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn"><?= $editJob ? 'Salvar alterações' : 'Criar vaga' ?></button>
                        <?php if ($editJob): ?>
                            <a href="admin.php" class="btn btn-outline" style="margin-left: 1rem;">Cancelar</a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="admin-card admin-list-card">
                    <h3>Lista de vagas</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Local</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filteredJobs as $job): ?>
                                <tr>
                                    <td><?= $job['id'] ?></td>
                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                    <td><?= htmlspecialchars($job['type']) ?></td>
                                    <td><?= htmlspecialchars($job['location']) ?></td>
                                    <td class="actions-row">
                                        <a href="admin.php?edit=<?= $job['id'] ?>" class="btn btn-outline">Editar</a>
                                        <a href="admin.php?delete=<?= $job['id'] ?>" class="btn btn-danger" onclick="return confirm('Excluir esta vaga?');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($filteredJobs) === 0): ?>
                        <p style="margin-top: 1rem; color: #475569;">Nenhuma vaga encontrada para essa busca.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-card access-card">
                <h3>Histórico de acessos às vagas</h3>
                <?php if (count($accessLog) === 0): ?>
                    <p>Nenhum acesso registrado ainda.</p>
                <?php else: ?>
                    <ul class="access-list">
                        <?php foreach (array_slice($accessLog, 0, 20) as $entry): ?>
                            <li>
                                <strong>Vaga <?= $entry['job_id'] ?>:</strong> <?= htmlspecialchars($entry['job_title']) ?>
                                <span><?= date('d/m/Y H:i', strtotime($entry['timestamp'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php include 'footer.php'; ?>
