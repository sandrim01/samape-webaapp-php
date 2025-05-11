<nav id="sidebar" class="active">
    <div class="sidebar-header">
        <h3>SAMAPE</h3>
        <p>Assistência Técnica</p>
    </div>

    <ul class="list-unstyled components">
        <li <?= strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'class="active"' : '' ?>>
            <a href="<?= BASE_URL ?>/dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        
        <li <?= strpos($_SERVER['PHP_SELF'], 'service_orders.php') !== false ? 'class="active"' : '' ?>>
            <a href="<?= BASE_URL ?>/service_orders.php">
                <i class="fas fa-clipboard-list"></i> Ordens de Serviço
            </a>
        </li>
        
        <li <?= strpos($_SERVER['PHP_SELF'], 'clients.php') !== false || strpos($_SERVER['PHP_SELF'], 'machinery.php') !== false ? 'class="active"' : '' ?>>
            <a href="#clientsSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-users"></i> Clientes
            </a>
            <ul class="collapse list-unstyled <?= strpos($_SERVER['PHP_SELF'], 'clients.php') !== false || strpos($_SERVER['PHP_SELF'], 'machinery.php') !== false ? 'show' : '' ?>" id="clientsSubmenu">
                <li>
                    <a href="<?= BASE_URL ?>/clients.php">
                        <i class="fas fa-user-tie"></i> Gerenciar Clientes
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/machinery.php">
                        <i class="fas fa-cogs"></i> Gerenciar Maquinário
                    </a>
                </li>
            </ul>
        </li>
        
        <?php if (has_permission([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <li <?= strpos($_SERVER['PHP_SELF'], 'employees.php') !== false ? 'class="active"' : '' ?>>
            <a href="<?= BASE_URL ?>/employees.php">
                <i class="fas fa-user-hard-hat"></i> Funcionários
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission([ROLE_ADMIN, ROLE_MANAGER])): ?>
        <li <?= strpos($_SERVER['PHP_SELF'], 'financial.php') !== false ? 'class="active"' : '' ?>>
            <a href="<?= BASE_URL ?>/financial.php">
                <i class="fas fa-chart-line"></i> Financeiro
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (has_permission([ROLE_ADMIN])): ?>
        <li>
            <a href="#userSubmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle">
                <i class="fas fa-user-shield"></i> Administração
            </a>
            <ul class="collapse list-unstyled" id="userSubmenu">
                <li>
                    <a href="<?= BASE_URL ?>/users.php">
                        <i class="fas fa-users-cog"></i> Usuários
                    </a>
                </li>
                <li>
                    <a href="<?= BASE_URL ?>/logs.php">
                        <i class="fas fa-history"></i> Logs do Sistema
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="<?= BASE_URL ?>/about.php">
                <i class="fas fa-info-circle"></i> Sobre
            </a>
        </li>
        
        <li>
            <a href="<?= BASE_URL ?>/logout.php">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </li>
    </ul>
</nav>
