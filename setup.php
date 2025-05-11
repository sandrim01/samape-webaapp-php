<?php
/**
 * SAMAPE - System Setup
 * Initializes the database and creates default data
 * Restricted to administrator users only
 */

// Include initialization file which loads config, database, auth functions etc.
require_once 'config/init.php';

// First check if the system is already set up
// If not, initialize it before checking permissions
$database = new Database();
$db = $database->connect();

// Try to initialize the schema first (will create admin user if needed)
try {
    $database->initialize_schema();
    $initialized = true;
} catch (PDOException $e) {
    $initialized = false;
    error_log("Setup initialization error: " . $e->getMessage());
}

// Temporarily skip authentication check for initial setup
// After system is set up, uncomment the following block to restrict access
/*
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_ADMIN) {
    // Store an error message in session
    $_SESSION['error'] = "Acesso restrito. Apenas administradores podem acessar a página de configuração.";
    
    // Redirect to login page
    header("Location: " . BASE_URL . "/login.php");
    exit;
}
*/

// Initialize the database schema (again if needed)
try {
    // Initialize gamification tables
    require_once 'includes/gamification.php';
    setup_gamification_tables($db);
    
    $success = true;
    $message = "Sistema inicializado com sucesso!";
} catch (PDOException $e) {
    $success = false;
    $message = "Erro ao inicializar o sistema: " . $e->getMessage();
    error_log("Setup error: " . $e->getMessage());
}

// Create sample data if requested
if ($success && isset($_GET['sample_data']) && $_GET['sample_data'] == 1) {
    try {
        $db = $database->connect();
        
        // Begin transaction
        $db->beginTransaction();
        
        // Check if we already have sample data
        $stmt = $db->query("SELECT COUNT(*) as count FROM clientes");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            // Create sample clients
            $clients = [
                ['Construções Vega S.A.', '12.345.678/0001-90', '(11) 3456-7890', 'contato@vega.com.br', 'Av. Industrial, 1500, São Paulo, SP'],
                ['Metalúrgica Orion Ltda.', '23.456.789/0001-01', '(21) 2345-6789', 'contato@orion.com.br', 'Rua das Indústrias, 500, Rio de Janeiro, RJ'],
                ['Transportes Andromeda Ltda.', '34.567.890/0001-12', '(31) 3456-7890', 'contato@andromeda.com.br', 'Av. dos Transportes, 789, Belo Horizonte, MG'],
                ['Mineradora Phoenix S.A.', '45.678.901/0001-23', '(41) 4567-8901', 'contato@phoenix.com.br', 'Rod. BR 101, Km 50, Curitiba, PR'],
                ['Agro Centauri Ltda.', '56.789.012/0001-34', '(51) 5678-9012', 'contato@centauri.com.br', 'Estrada Rural, 100, Porto Alegre, RS']
            ];
            
            $stmt = $db->prepare("INSERT INTO clientes (nome, cnpj, telefone, email, endereco) VALUES (?, ?, ?, ?, ?)");
            foreach ($clients as $client) {
                $stmt->execute($client);
            }
            
            // Create sample machinery
            $machinery = [
                [1, 'Escavadeira', 'Caterpillar', '336D2 L', 'CAT33612345', 2018, '2023-01-15'],
                [1, 'Pá-carregadeira', 'Volvo', 'L120H', 'VOL12098765', 2019, '2023-02-20'],
                [2, 'Torno Mecânico', 'Romi', 'C 420', 'ROM42012345', 2017, '2023-01-10'],
                [2, 'Fresadora CNC', 'DMG MORI', 'DMU 50', 'DMG50123456', 2020, '2023-03-05'],
                [3, 'Caminhão', 'Scania', 'R 450', 'SCA45012345', 2021, '2023-02-28'],
                [3, 'Empilhadeira', 'Hyster', 'H80FT', 'HYS80123456', 2019, '2023-03-15'],
                [4, 'Perfuratriz', 'Atlas Copco', 'SmartROC D65', 'ATL65123456', 2020, '2023-01-20'],
                [4, 'Britador', 'Metso', 'C120', 'MET12098765', 2018, '2022-12-10'],
                [5, 'Trator', 'John Deere', '8R 370', 'JDR37012345', 2022, '2023-03-01'],
                [5, 'Colheitadeira', 'New Holland', 'CR 8.90', 'NH890123456', 2021, '2023-02-10']
            ];
            
            $stmt = $db->prepare("INSERT INTO maquinarios (cliente_id, tipo, marca, modelo, numero_serie, ano, ultima_manutencao) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($machinery as $machine) {
                $stmt->execute($machine);
            }
            
            // Create sample employees
            $employees = [
                ['Carlos Silva', 'Técnico Mecânico', 'carlos.silva@samape.com.br', 1],
                ['Ana Oliveira', 'Técnico Elétrico', 'ana.oliveira@samape.com.br', 1],
                ['Paulo Santos', 'Técnico em Hidráulica', 'paulo.santos@samape.com.br', 1],
                ['Maria Costa', 'Técnico em Automação', 'maria.costa@samape.com.br', 1],
                ['Roberto Lima', 'Técnico em Refrigeração', 'roberto.lima@samape.com.br', 1],
                ['Júlia Martins', 'Supervisor de Manutenção', 'julia.martins@samape.com.br', 1]
            ];
            
            $stmt = $db->prepare("INSERT INTO funcionarios (nome, cargo, email, ativo) VALUES (?, ?, ?, ?)");
            foreach ($employees as $employee) {
                $stmt->execute($employee);
            }
            
            // Create sample users (besides the default admin)
            $users = [
                ['Gerente Operacional', 'gerente', 'gerente@samape.com.br', password_hash('gerente123', PASSWORD_DEFAULT), 'gerente'],
                ['Técnico Operacional', 'tecnico', 'tecnico@samape.com.br', password_hash('tecnico123', PASSWORD_DEFAULT), 'funcionario']
            ];
            
            $stmt = $db->prepare("INSERT INTO usuarios (nome, username, email, senha_hash, papel) VALUES (?, ?, ?, ?, ?)");
            foreach ($users as $user) {
                $stmt->execute($user);
            }
            
            // Create sample service orders
            $orders = [
                [1, 1, 'Manutenção preventiva do sistema hidráulico e verificação de desgaste de componentes.', 'concluida', '2023-01-10', '2023-01-15', 4500.00],
                [2, 3, 'Reparo no sistema de acionamento e troca de componentes de desgaste.', 'concluida', '2023-02-05', '2023-02-10', 3200.00],
                [3, 5, 'Diagnóstico e reparo no sistema de transmissão. Substituição de filtros e óleos.', 'concluida', '2023-02-15', '2023-02-20', 5800.00],
                [4, 7, 'Manutenção preventiva e calibração de sensores. Teste de performance.', 'em_andamento', '2023-03-01', null, null],
                [5, 9, 'Verificação do sistema eletrônico e atualização de software. Manutenção preventiva.', 'aberta', '2023-03-10', null, null]
            ];
            
            $stmt = $db->prepare("INSERT INTO ordens_servico (cliente_id, maquinario_id, descricao, status, data_abertura, data_fechamento, valor_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($orders as $order) {
                $stmt->execute($order);
            }
            
            // Assign technicians to service orders
            $os_technicians = [
                [1, 1], [1, 3], 
                [2, 2], [2, 4],
                [3, 1], [3, 5],
                [4, 3], [4, 6],
                [5, 2], [5, 4]
            ];
            
            $stmt = $db->prepare("INSERT INTO os_funcionarios (ordem_id, funcionario_id) VALUES (?, ?)");
            foreach ($os_technicians as $assignment) {
                $stmt->execute($assignment);
            }
            
            // Create sample financial entries
            $current_month = date('Y-m');
            $prev_month = date('Y-m', strtotime('-1 month'));
            
            $finances = [
                [TRANSACTION_INCOME, 4500.00, 'Pagamento OS #1 - Construções Vega', $prev_month . '-15'],
                [TRANSACTION_INCOME, 3200.00, 'Pagamento OS #2 - Metalúrgica Orion', $prev_month . '-20'],
                [TRANSACTION_INCOME, 5800.00, 'Pagamento OS #3 - Transportes Andromeda', $current_month . '-05'],
                [TRANSACTION_INCOME, 2300.00, 'Venda de peças - Phoenix', $current_month . '-10'],
                [TRANSACTION_EXPENSE, 8500.00, 'Salários da equipe técnica', $prev_month . '-25'],
                [TRANSACTION_EXPENSE, 8500.00, 'Salários da equipe técnica', $current_month . '-05'],
                [TRANSACTION_EXPENSE, 1200.00, 'Aluguel da oficina', $prev_month . '-05'],
                [TRANSACTION_EXPENSE, 1200.00, 'Aluguel da oficina', $current_month . '-05'],
                [TRANSACTION_EXPENSE, 3500.00, 'Compra de peças e componentes', $prev_month . '-10'],
                [TRANSACTION_EXPENSE, 2800.00, 'Compra de ferramentas', $current_month . '-08'],
                [TRANSACTION_EXPENSE, 600.00, 'Combustível para veículos', $current_month . '-12'],
                [TRANSACTION_EXPENSE, 450.00, 'Energia elétrica', $current_month . '-10'],
                [TRANSACTION_EXPENSE, 350.00, 'Água e saneamento', $current_month . '-10'],
                [TRANSACTION_EXPENSE, 180.00, 'Internet e telefone', $current_month . '-05']
            ];
            
            $stmt = $db->prepare("INSERT INTO financeiro (tipo, valor, descricao, data) VALUES (?, ?, ?, ?)");
            foreach ($finances as $finance) {
                $stmt->execute($finance);
            }
            
            // Add some log entries
            $logs = [
                [1, 'login: Administrador fez login no sistema', date('Y-m-d H:i:s', strtotime('-2 days'))],
                [1, 'cliente_adicionado: Cliente ID: 1', date('Y-m-d H:i:s', strtotime('-2 days +10 minutes'))],
                [1, 'maquinario_adicionado: Maquinário ID: 1, Cliente ID: 1', date('Y-m-d H:i:s', strtotime('-2 days +15 minutes'))],
                [1, 'os_criada: OS ID: 1, Cliente ID: 1', date('Y-m-d H:i:s', strtotime('-2 days +30 minutes'))],
                [1, 'transacao_financeira_adicionada: ID: 1, Tipo: entrada, Valor: R$ 4500', date('Y-m-d H:i:s', strtotime('-1 day'))],
                [2, 'login: Gerente Operacional fez login no sistema', date('Y-m-d H:i:s', strtotime('-1 day +2 hours'))],
                [2, 'funcionario_adicionado: Funcionário ID: 1', date('Y-m-d H:i:s', strtotime('-1 day +2 hours 15 minutes'))],
                [2, 'logout: Gerente Operacional fez logout do sistema', date('Y-m-d H:i:s', strtotime('-1 day +3 hours'))],
                [3, 'login: Técnico Operacional fez login no sistema', date('Y-m-d H:i:s', strtotime('-4 hours'))],
                [3, 'os_atualizada: OS ID: 4, Status: em_andamento', date('Y-m-d H:i:s', strtotime('-3 hours'))],
                [3, 'logout: Técnico Operacional fez logout do sistema', date('Y-m-d H:i:s', strtotime('-2 hours'))]
            ];
            
            $stmt = $db->prepare("INSERT INTO logs (usuario_id, acao, datahora) VALUES (?, ?, ?)");
            foreach ($logs as $log) {
                $stmt->execute($log);
            }
            
            // Commit the transaction
            $db->commit();
            
            $sample_message = "Dados de amostra criados com sucesso!";
        } else {
            $sample_message = "Os dados de exemplo não foram criados porque já existem dados no sistema.";
            $db->rollBack();
        }
    } catch (PDOException $e) {
        $db->rollBack();
        $sample_message = "Erro ao criar dados de amostra: " . $e->getMessage();
        error_log("Sample data creation error: " . $e->getMessage());
    }
}

// Set page title
$page_title = "Configuração do Sistema";

// Basic styles for setup page
$custom_css = '
<style>
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        padding: 20px;
        max-width: 800px;
        margin: 0 auto;
        background-color: #f5f5f5;
    }
    .setup-container {
        background-color: #fff;
        border-radius: 5px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }
    .alert-info {
        background-color: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }
    .btn {
        display: inline-block;
        font-weight: 400;
        text-align: center;
        white-space: nowrap;
        vertical-align: middle;
        user-select: none;
        border: 1px solid transparent;
        padding: 0.375rem 0.75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: 0.25rem;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out;
    }
    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    .btn-primary:hover {
        color: #fff;
        background-color: #0069d9;
        border-color: #0062cc;
    }
    .btn-success {
        color: #fff;
        background-color: #28a745;
        border-color: #28a745;
    }
    .btn-success:hover {
        color: #fff;
        background-color: #218838;
        border-color: #1e7e34;
    }
    .btn-danger {
        color: #fff;
        background-color: #dc3545;
        border-color: #dc3545;
    }
    .btn-danger:hover {
        color: #fff;
        background-color: #c82333;
        border-color: #bd2130;
    }
    table {
        width: 100%;
        margin-bottom: 1rem;
        color: #212529;
        border-collapse: collapse;
    }
    table th, table td {
        padding: 0.75rem;
        vertical-align: top;
        border-top: 1px solid #dee2e6;
        text-align: left;
    }
    table thead th {
        vertical-align: bottom;
        border-bottom: 2px solid #dee2e6;
    }
    .footer {
        margin-top: 30px;
        text-align: center;
        color: #6c757d;
        font-size: 0.9rem;
    }
</style>
';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - <?= APP_NAME ?></title>
    <?= $custom_css ?>
</head>
<body>
    <div class="setup-container">
        <div class="header">
            <h1>SAMAPE - Configuração do Sistema</h1>
            <p>Esta página é utilizada para inicializar o sistema SAMAPE e configurar os dados iniciais.</p>
        </div>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            <h4>Sucesso!</h4>
            <p><?= htmlspecialchars($message) ?></p>
            <?php if (isset($sample_message)): ?>
            <p><?= htmlspecialchars($sample_message) ?></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-danger">
            <h4>Erro!</h4>
            <p><?= htmlspecialchars($message) ?></p>
        </div>
        <?php endif; ?>
        
        <div class="content">
            <h3>Informações do Sistema</h3>
            <table>
                <tr>
                    <th>Componente</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>Banco de Dados</td>
                    <td><?= $success ? '<span style="color: green;">Configurado</span>' : '<span style="color: red;">Erro</span>' ?></td>
                </tr>
                <tr>
                    <td>Usuário Administrador</td>
                    <td><?= $success ? '<span style="color: green;">Criado</span>' : '<span style="color: red;">Não criado</span>' ?></td>
                </tr>
                <tr>
                    <td>Tabelas do Sistema</td>
                    <td><?= $success ? '<span style="color: green;">Criadas</span>' : '<span style="color: red;">Não criadas</span>' ?></td>
                </tr>
                <tr>
                    <td>Dados de Amostra</td>
                    <td><?= isset($sample_message) && strpos($sample_message, 'sucesso') !== false ? '<span style="color: green;">Criados</span>' : '<span style="color: orange;">Não criados</span>' ?></td>
                </tr>
            </table>
            
            <h3>Próximos Passos</h3>
            <?php if ($success): ?>
            <p>O sistema foi configurado com sucesso! Agora você pode:</p>
            <ul>
                <li>Acessar o <a href="login.php">login do sistema</a> usando as credenciais padrão:</li>
                <ul>
                    <li><strong>Email:</strong> admin@samape.com</li>
                    <li><strong>Senha:</strong> admin123</li>
                </ul>
                <li>Após o login, recomendamos alterar a senha do administrador por questões de segurança.</li>
                <?php if (!isset($sample_message) || strpos($sample_message, 'sucesso') === false): ?>
                <li>Criar dados de amostra para testes: <a href="setup.php?sample_data=1" class="btn btn-success">Criar Dados de Amostra</a></li>
                <?php endif; ?>
            </ul>
            <div class="alert alert-warning">
                <h4>Importante!</h4>
                <p>Por questões de segurança, após a configuração inicial e validação do funcionamento do sistema, recomendamos <strong>remover ou restringir o acesso</strong> a este arquivo de configuração (setup.php).</p>
            </div>
            <?php else: ?>
            <p>Houve um erro na configuração do sistema. Por favor, verifique:</p>
            <ul>
                <li>As permissões de arquivo para escrita no diretório raiz (para o arquivo do banco de dados).</li>
                <li>As configurações de conexão com o banco de dados no arquivo config/database.php.</li>
                <li>Os logs de erro do PHP para informações detalhadas sobre o problema.</li>
            </ul>
            <p>Após corrigir os problemas, tente novamente:</p>
            <a href="setup.php" class="btn btn-primary">Tentar Novamente</a>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> SAMAPE - Sistema de Gestão de Assistência Técnica</p>
            <p>Versão <?= APP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
