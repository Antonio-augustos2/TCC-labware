<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'LabWare - Transforme a Gestão Laboratorial'; ?></title>
  
  <!-- Fontes e ícones -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <!-- HEADER -->
  <header>
    <div class="container header-content">
      <div class="logo">Lab<span>Ware</span></div>
      <nav>
        <?php 
          // Determina as links de navegação baseado no arquivo atual
          $currentPage = basename($_SERVER['PHP_SELF']);
          
          if ($currentPage === 'index.php' || $currentPage === 'index.html'): 
        ?>
          <a href="#sobre">Sobre</a>
          <a href="#valores">Valores</a>
          <a href="#carreira">Carreira</a>
          <a href="#vagas">Vagas</a>
          <a href="admin.php" class="btn btn-outline">Login admin</a>
          <a href="#formulario" class="btn">Candidatar-se</a>
        <?php 
          elseif ($currentPage === 'job.php'): 
        ?>
          <a href="index.php">Voltar</a>
          <a href="admin.php" class="btn btn-outline">Login admin</a>
        <?php 
          elseif ($currentPage === 'admin.php'): 
        ?>
          <a href="index.php">Voltar ao site</a>
          <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']): ?>
            <a href="admin.php?logout=1" class="btn btn-outline">Sair</a>
          <?php endif; ?>
        <?php 
          endif; 
        ?>
      </nav>
    </div>
  </header>
