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
$accessCounts = [];
$feedbacksAdmin = [];
$editFeedback = null;
$selectedAccessJobId = filter_input(INPUT_GET, 'access_job_id', FILTER_VALIDATE_INT);
$activeTab = $_GET['tab'] ?? 'vagas';

/**
 * Divide a descrição estruturada em campos para edição.
 * Descrições antigas continuam sendo exibidas como "Descrição do trabalho".
 */
function getJobDescriptionSections($description) {
    $sections = [
        'description' => '',
        'responsibilities' => '',
        'requirements' => '',
        'benefits' => '',
        'additional' => ''
    ];

    $labels = [
        'description' => 'Descrição do trabalho',
        'responsibilities' => 'Responsabilidades',
        'requirements' => 'Requisitos Desejáveis',
        'benefits' => 'Remuneração e Benefícios',
        'additional' => 'Informações Adicionais'
    ];

    $labelPattern = implode('|', array_map(static fn($label) => preg_quote($label, '/'), $labels));
    $pattern = '/\*\*(' . $labelPattern . ')\*\*\s*(.*?)(?=\R\s*\*\*(?:' . $labelPattern . ')\*\*|$)/su';
    preg_match_all($pattern, (string) $description, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $key = array_search($match[1], $labels, true);
        if ($key !== false) {
            $sections[$key] = trim($match[2]);
        }
    }

    if (count(array_filter($sections)) === 0) {
        $sections['description'] = trim((string) $description);
    }

    return $sections;
}

function buildJobDescription($sections) {
    $labels = [
        'description' => 'Descrição do trabalho',
        'responsibilities' => 'Responsabilidades',
        'requirements' => 'Requisitos Desejáveis',
        'benefits' => 'Remuneração e Benefícios',
        'additional' => 'Informações Adicionais'
    ];
    $parts = [];

    foreach ($labels as $key => $label) {
        $content = trim((string) ($sections[$key] ?? ''));
        if ($content !== '') {
            $parts[] = '**' . $label . "**\n\n" . $content;
        }
    }

    return implode("\n\n", $parts);
}

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

    $stmt = $conn->prepare('SELECT id_rh, nome, senha FROM rh WHERE login_email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $adminUser = $result->fetch_assoc();

    if ($adminUser && $password === $adminUser['senha']) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = (int) $adminUser['id_rh'];
        $_SESSION['admin_nome'] = $adminUser['nome'];
        header('Location: admin.php');
        exit;
    }

    $loginError = 'E-mail ou senha incorretos.';
}

if ($loggedIn) {
    // Obter todas as vagas para o painel administrativo
    $jobs = getAllVagasAdmin($conn);

    if (isset($_GET['delete_feedback'])) {
        $feedbackId = filter_input(INPUT_GET, 'delete_feedback', FILTER_VALIDATE_INT);
        if ($feedbackId !== false && $feedbackId !== null) {
            deleteFeedback($conn, $feedbackId);
            header('Location: admin.php?tab=feedbacks');
            exit;
        }
    }

    if (isset($_GET['edit_feedback'])) {
        $feedbackId = filter_input(INPUT_GET, 'edit_feedback', FILTER_VALIDATE_INT);
        if ($feedbackId !== false && $feedbackId !== null) {
            $editFeedback = getFeedbackById($conn, $feedbackId);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_feedback') {
        $feedbackAuthor = trim($_POST['feedback_author'] ?? '');
        $feedbackRole = trim($_POST['feedback_role'] ?? '');
        $feedbackMessage = trim($_POST['feedback_message'] ?? '');
        $feedbackHighlight = !empty($_POST['feedback_highlight']);
        $feedbackId = filter_input(INPUT_POST, 'feedback_id', FILTER_VALIDATE_INT);

        if ($feedbackAuthor !== '' && $feedbackRole !== '' && $feedbackMessage !== '') {
            if ($feedbackId) {
                updateFeedback($conn, $feedbackId, $feedbackAuthor, $feedbackRole, $feedbackMessage, $feedbackHighlight);
            } else {
                createFeedback($conn, $feedbackAuthor, $feedbackRole, $feedbackMessage, $feedbackHighlight);
            }
            header('Location: admin.php?tab=feedbacks');
            exit;
        }
    }

    // Alterar status da vaga via query string
    if (isset($_GET['toggle_status'])) {
        $statusId = filter_input(INPUT_GET, 'toggle_status', FILTER_VALIDATE_INT);
        $statusValue = trim($_GET['status_value'] ?? 'Aberta');
        $allowedStatus = ['Aberta', 'Encerrada'];

        if ($statusId !== false && $statusId !== null && in_array($statusValue, $allowedStatus, true)) {
            setVagaStatus($conn, $statusId, $statusValue);
            header('Location: admin.php?tab=status');
            exit;
        }
    }
    
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
        $descriptionSections = [
            'description' => trim($_POST['description'] ?? ''),
            'responsibilities' => trim($_POST['responsibilities'] ?? ''),
            'requirements' => trim($_POST['requirements'] ?? ''),
            'benefits' => trim($_POST['benefits'] ?? ''),
            'additional' => trim($_POST['additional'] ?? '')
        ];
        $description = buildJobDescription($descriptionSections);
        $status = trim($_POST['status'] ?? 'Aberta');
        $jobId = filter_input(INPUT_POST, 'job_id', FILTER_VALIDATE_INT);

        $allowedStatus = ['Aberta', 'Encerrada'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'Aberta';
        }

        if ($title !== '' && $type !== '' && $location !== '' && $descriptionSections['description'] !== '') {
            if ($jobId) {
                // Atualizar vaga existente
                updateVaga($conn, $jobId, $title, $description, $status, $type, $location);
            } else {
                // Criar nova vaga
                createVaga($conn, $title, $description, $status, $type, $location);
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
    $accessCounts = getAccessCounts($conn);
    $feedbacksAdmin = getFeedbacksAdmin($conn);

    // Ordenar vagas por número de acessos em ordem decrescente
    usort($jobs, function($a, $b) use ($accessCounts) {
        $aCount = (int) ($accessCounts[(int) $a['id']] ?? 0);
        $bCount = (int) ($accessCounts[(int) $b['id']] ?? 0);
        return $bCount - $aCount; // Decrescente: maior para menor
    });
}

$editJobSections = getJobDescriptionSections($editJob['description'] ?? '');

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
            <div class="admin-tabs">
                <a href="admin.php?tab=vagas" class="tab-button <?= $activeTab === 'vagas' ? 'active' : '' ?>">Vagas</a>
                <a href="admin.php?tab=status" class="tab-button <?= $activeTab === 'status' ? 'active' : '' ?>">Status das vagas</a>
                <a href="admin.php?tab=feedbacks" class="tab-button <?= $activeTab === 'feedbacks' ? 'active' : '' ?>">Feedbacks</a>
            </div>
            <?php if ($activeTab !== 'feedbacks'): ?>
            <section class="access-summary" aria-labelledby="access-summary-title">
                <div class="access-summary-header">
                    <div>
                        <span class="card-eyebrow">Interesse nas vagas</span>
                        <h3 id="access-summary-title">Cliques em “Saiba mais”</h3>
                    </div>
                    <form method="get" action="admin.php" class="access-filter-form">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                        <label for="access_job_id">Filtrar vaga</label>
                        <select id="access_job_id" name="access_job_id" onchange="this.form.submit()">
                            <option value="">Todas as vagas</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?= (int) $job['id'] ?>" <?= ((int) $selectedAccessJobId === (int) $job['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($job['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="access-summary-grid">
                    <div class="access-counter access-counter-total">
                        <span class="access-counter-icon"><i class="fas fa-chart-column"></i></span>
                        <div><strong><?= array_sum($accessCounts) ?></strong><span>Total coletivo</span></div>
                    </div>
                    <?php foreach ($jobs as $job): ?>
                        <?php if ($selectedAccessJobId === false || $selectedAccessJobId === null || (int) $selectedAccessJobId === (int) $job['id']): ?>
                            <div class="access-counter">
                                <span class="access-counter-icon"><i class="fas fa-eye"></i></span>
                                <div><strong><?= (int) ($accessCounts[(int) $job['id']] ?? 0) ?></strong><span><?= htmlspecialchars($job['title']) ?></span></div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($activeTab === 'feedbacks'): ?>
                <div class="admin-grid feedback-admin-grid">
                    <div class="admin-card admin-form-card">
                        <span class="card-eyebrow">Conteúdo do site</span>
                        <h3><?= $editFeedback ? 'Alterar feedback' : 'Publicar feedback' ?></h3>
                        <form method="post" action="admin.php?tab=feedbacks" class="admin-form feedback-admin-form">
                            <input type="hidden" name="action" value="save_feedback">
                            <input type="hidden" name="feedback_id" value="<?= (int) ($editFeedback['id_feedback'] ?? 0) ?>">
                            <div class="form-group">
                                <label for="feedback_author">Nome da pessoa ou equipe</label>
                                <input type="text" id="feedback_author" name="feedback_author" value="<?= htmlspecialchars($editFeedback['autor'] ?? '') ?>" required placeholder="Ex.: Ana Silva">
                            </div>
                            <div class="form-group">
                                <label for="feedback_role">Cargo ou identificação</label>
                                <input type="text" id="feedback_role" name="feedback_role" value="<?= htmlspecialchars($editFeedback['cargo'] ?? '') ?>" required placeholder="Ex.: Desenvolvedora Full Stack">
                            </div>
                            <div class="form-group">
                                <label for="feedback_message">Feedback</label>
                                <textarea id="feedback_message" name="feedback_message" rows="6" required placeholder="Escreva o depoimento que aparecerá no site."><?= htmlspecialchars($editFeedback['mensagem'] ?? '') ?></textarea>
                            </div>
                            <label class="checkbox-label"><input type="checkbox" name="feedback_highlight" value="1" <?= !empty($editFeedback['destaque']) ? 'checked' : '' ?>> Destacar este feedback em azul</label>
                            <div class="feedback-form-actions">
                                <button type="submit" class="btn feedback-submit-button"><i class="fas <?= $editFeedback ? 'fa-save' : 'fa-paper-plane' ?>"></i> <?= $editFeedback ? 'Salvar alterações' : 'Publicar feedback' ?></button>
                                <?php if ($editFeedback): ?>
                                    <a href="admin.php?tab=feedbacks" class="btn btn-outline feedback-cancel-button">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="admin-card admin-list-card">
                        <span class="card-eyebrow">Feedbacks cadastrados</span>
                        <h3>Gerenciar publicações</h3>
                        <?php if (count($feedbacksAdmin) === 0): ?>
                            <p class="empty-state">Nenhum feedback novo cadastrado.</p>
                        <?php else: ?>
                            <div class="feedback-admin-list">
                                <?php foreach ($feedbacksAdmin as $feedback): ?>
                                    <article class="feedback-admin-item">
                                        <div>
                                            <strong><?= htmlspecialchars($feedback['autor']) ?></strong>
                                            <span><?= htmlspecialchars($feedback['cargo']) ?></span>
                                            <p><?= nl2br(htmlspecialchars($feedback['mensagem'])) ?></p>
                                        </div>
                                        <div class="feedback-item-actions">
                                            <a class="btn btn-outline feedback-edit-button" href="admin.php?tab=feedbacks&edit_feedback=<?= (int) $feedback['id_feedback'] ?>" title="Alterar feedback" aria-label="Alterar feedback"><i class="fas fa-pen"></i></a>
                                            <a class="btn btn-danger feedback-delete-button" href="admin.php?tab=feedbacks&delete_feedback=<?= (int) $feedback['id_feedback'] ?>" onclick="return confirm('Excluir este feedback?');" title="Excluir feedback" aria-label="Excluir feedback"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($activeTab === 'status'): ?>
                <div class="admin-card admin-list-card" style="margin-top: 1.5rem;">
                    <h3>Gerenciar status das vagas</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vaga</th>
                                <th>Status</th>
                                <th>Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?= $job['id'] ?></td>
                                    <td><?= htmlspecialchars($job['title']) ?></td>
                                    <td>
                                        <span class="status-badge <?= ($job['status'] ?? 'Aberta') === 'Encerrada' ? 'status-closed' : 'status-open' ?>">
                                            <?= htmlspecialchars($job['status'] ?? 'Aberta') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin.php?toggle_status=<?= $job['id'] ?>&status_value=<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrada' : 'Aberta' ?>&tab=status" class="btn btn-outline">
                                            <?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar' : 'Reabrir' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
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
                            <label for="status">Status da vaga</label>
                            <select id="status" name="status">
                                <option value="Aberta" <?= (($editJob['status'] ?? 'Aberta') === 'Aberta') ? 'selected' : '' ?>>Aberta</option>
                                <option value="Encerrada" <?= (($editJob['status'] ?? 'Aberta') === 'Encerrada') ? 'selected' : '' ?>>Encerrada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="description">Descrição do trabalho</label>
                            <textarea id="description" name="description" rows="5" required placeholder="Apresente a empresa, a posição e o contexto da oportunidade."><?= htmlspecialchars($editJobSections['description']) ?></textarea>
                            <small class="form-help">Explique o propósito da vaga e o contexto da empresa.</small>
                        </div>
                        <div class="form-group">
                            <label for="responsibilities">Responsabilidades</label>
                            <textarea id="responsibilities" name="responsibilities" rows="5" placeholder="Descreva as principais atividades e entregas da posição."><?= htmlspecialchars($editJobSections['responsibilities']) ?></textarea>
                            <small class="form-help">Use parágrafos ou tópicos iniciados por •.</small>
                        </div>
                        <div class="form-group">
                            <label for="requirements">Requisitos desejáveis</label>
                            <textarea id="requirements" name="requirements" rows="4" placeholder="Ex.: Escolaridade, experiência, conhecimentos técnicos e comportamentais."><?= htmlspecialchars($editJobSections['requirements']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="benefits">Remuneração e benefícios</label>
                            <textarea id="benefits" name="benefits" rows="4" placeholder="Ex.: Salário, contratação, benefícios e condições. "><?= htmlspecialchars($editJobSections['benefits']) ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="additional">Informações adicionais</label>
                            <textarea id="additional" name="additional" rows="4" placeholder="Ex.: Modalidade, jornada, período e observações para candidatos."><?= htmlspecialchars($editJobSections['additional']) ?></textarea>
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
                                <th>Status</th>
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
                                    <td>
                                        <span class="status-badge <?= ($job['status'] ?? 'Aberta') === 'Encerrada' ? 'status-closed' : 'status-open' ?>">
                                            <?= htmlspecialchars($job['status'] ?? 'Aberta') ?>
                                        </span>
                                    </td>
                                    <td class="actions-row">
                                        <a href="admin.php?edit=<?= $job['id'] ?>" class="btn btn-outline action-button" title="Editar vaga" aria-label="Editar vaga">✏️</a>
                                        <a href="admin.php?delete=<?= $job['id'] ?>" class="btn btn-danger action-button" title="Excluir vaga" aria-label="Excluir vaga" onclick="return confirm('Excluir esta vaga?');">🗑️</a>
                                        <a href="admin.php?toggle_status=<?= $job['id'] ?>&status_value=<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrada' : 'Aberta' ?>" class="btn btn-outline action-button" title="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar vaga' : 'Reabrir vaga' ?>" aria-label="<?= (($job['status'] ?? 'Aberta') === 'Aberta') ? 'Encerrar vaga' : 'Reabrir vaga' ?>">
                                            <?= (($job['status'] ?? 'Aberta') === 'Aberta') ? '🔒' : '🔓' ?>
                                        </a>
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
                                <strong><?= htmlspecialchars($entry['job_title']) ?></strong>
                                <span><?= date('d/m/Y H:i', strtotime($entry['timestamp'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>
<script>
// Mantém a posição da lista ao abrir uma edição no painel administrativo.
(function () {
    const scrollStorageKey = 'adminScrollPosition';

    document.querySelectorAll('a[href*="edit="]:not([href*="edit_feedback="]), a[href*="edit_feedback="]').forEach(link => {
        link.addEventListener('click', function () {
            sessionStorage.setItem(scrollStorageKey, String(window.scrollY));
        });
    });

    const savedPosition = sessionStorage.getItem(scrollStorageKey);
    if (savedPosition !== null) {
        sessionStorage.removeItem(scrollStorageKey);
        const restoreScroll = () => window.scrollTo(0, Number(savedPosition) || 0);
        requestAnimationFrame(() => {
            restoreScroll();
            requestAnimationFrame(restoreScroll);
        });
    }
})();
</script>
<?php include 'footer.php'; ?>
