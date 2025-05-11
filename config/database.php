<?php
/**
 * Database connection configuration
 * Uses PDO for secure database interactions
 */

class Database {
    private $host = '127.0.0.1';
    private $db_name = 'samape';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function connect() {
        $this->conn = null;

        try {
            // SQLite connection for Replit
            $this->conn = new PDO("sqlite:" . $_SERVER['DOCUMENT_ROOT'] . "/database.db");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }

    // Initialize database schema if it doesn't exist
    public function initialize_schema() {
        $conn = $this->connect();
        
        // Create users table
        $conn->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            senha_hash TEXT NOT NULL,
            papel TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create logs table
        $conn->exec("CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER,
            acao TEXT NOT NULL,
            datahora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        )");
        
        // Create clients table
        $conn->exec("CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            cnpj TEXT,
            telefone TEXT,
            email TEXT,
            endereco TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create machinery table
        $conn->exec("CREATE TABLE IF NOT EXISTS maquinarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            tipo TEXT NOT NULL,
            marca TEXT NOT NULL,
            modelo TEXT NOT NULL,
            numero_serie TEXT,
            ano INTEGER,
            ultima_manutencao DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        )");
        
        // Create service orders table
        $conn->exec("CREATE TABLE IF NOT EXISTS ordens_servico (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NOT NULL,
            maquinario_id INTEGER NOT NULL,
            descricao TEXT NOT NULL,
            status TEXT NOT NULL,
            data_abertura DATE NOT NULL,
            data_fechamento DATE,
            valor_total REAL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            FOREIGN KEY (maquinario_id) REFERENCES maquinarios(id)
        )");
        
        // Create employees table
        $conn->exec("CREATE TABLE IF NOT EXISTS funcionarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            cargo TEXT NOT NULL,
            email TEXT,
            ativo INTEGER DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create relationship table between service orders and employees
        $conn->exec("CREATE TABLE IF NOT EXISTS os_funcionarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ordem_id INTEGER NOT NULL,
            funcionario_id INTEGER NOT NULL,
            FOREIGN KEY (ordem_id) REFERENCES ordens_servico(id),
            FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
        )");
        
        // Create financial table
        $conn->exec("CREATE TABLE IF NOT EXISTS financeiro (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tipo TEXT NOT NULL,
            valor REAL NOT NULL,
            descricao TEXT,
            data DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Check if admin user exists, if not create default admin
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usuarios WHERE papel = 'administrador'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] == 0) {
            // Create default admin user
            $default_password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, username, email, senha_hash, papel) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['Administrador', 'admin', 'admin@samape.com', $default_password, 'administrador']);
        }
    }
}
?>
