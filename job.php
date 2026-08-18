<?php
require_once 'config.php';
require_once 'db_functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$job = null;

if ($id !== false && $id !== null) {
    $job = getVagaById($conn, $id);
    
    if ($job) {
        // Registrar acesso à vaga
        registerAccess($conn, $job['id'], $job['title']);
    }
}

$pageTitle = 'Vaga LabWare';
include 'header.php';
?>
<main class="container job-page">
    <?php if (!$job): ?>
        <section class="job-details-card">
            <h2>Vaga não encontrada</h2>
            <p>O ID informado não corresponde a nenhuma vaga ativa.</p>
            <a href="index.html" class="btn">Voltar ao site</a>
        </section>
    <?php else: ?>
        <section class="job-details-card">
            <h2><?= htmlspecialchars($job['title']) ?></h2>
            <span class="job-badge"><?= htmlspecialchars($job['type']) ?></span>
            <p class="job-location">Local: <?= htmlspecialchars($job['location']) ?></p>
            <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
            <div class="job-actions">
                <a href="index.html#formulario" class="btn">Candidatar-se</a>
                <a href="admin.php" class="btn btn-outline">Painel admin</a>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php include 'footer.php'; ?>
