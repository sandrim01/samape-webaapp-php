<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/init.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) . ' - ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/styles.css">
    
    <?php if (isset($use_charts) && $use_charts): ?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php endif; ?>
    
    <?php if (isset($extra_css)): ?>
    <?= $extra_css ?>
    <?php endif; ?>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/includes/sidebar.php'; ?>
        
        <!-- Page Content -->
        <div class="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
                <div class="container-fluid">
                    <button class="btn btn-dark" id="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <a class="navbar-brand d-none d-sm-block" href="<?= BASE_URL ?>/dashboard.php">
                        <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="SAMAPE Logo" style="height: 40px; width: auto;">
                    </a>
                    
                    <div class="ms-auto">
                        <ul class="navbar-nav">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <li><span class="dropdown-item-text text-muted"><?= htmlspecialchars($_SESSION['user_role']) ?></span></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/about.php">Sobre a SAMAPE</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Sair</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <div class="container-fluid my-4">
                <!-- Page Header -->
                <?php if (isset($page_title)): ?>
                <div class="page-header mb-4">
                    <h1><?= htmlspecialchars($page_title) ?></h1>
                    <?php if (isset($page_description)): ?>
                    <p class="text-muted"><?= htmlspecialchars($page_description) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Flash Messages -->
                <?= display_flash_messages() ?>
    <?php else: ?>
    <!-- Simplified header for login page -->
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-6 mx-auto text-center">
                <img src="<?= BASE_URL ?>/assets/img/logo.png" alt="SAMAPE Logo" class="img-fluid mb-3" style="max-height: 150px;">
                <p class="lead">Sistema de Gestão de Assistência Técnica</p>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?= display_flash_messages() ?>
    <?php endif; ?>
