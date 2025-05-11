<?php
/**
 * Database connection configuration
 * Uses PDO for secure database interactions
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $conn;

    public function __construct() {
        // Use Replit PostgreSQL environment variables
        $this->host = getenv('PGHOST');
        $this->db_name = getenv('PGDATABASE');
        $this->username = getenv('PGUSER');
        $this->password = getenv('PGPASSWORD');
        $this->port = getenv('PGPORT');
    }

    public function connect() {
        $this->conn = null;

        try {
            // PostgreSQL connection for Replit
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->db_name}";
            $this->conn = new PDO($dsn, $this->username, $this->password);
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
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            senha_hash VARCHAR(255) NOT NULL,
            papel VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create logs table
        $conn->exec("CREATE TABLE IF NOT EXISTS logs (
            id SERIAL PRIMARY KEY,
            usuario_id INTEGER,
            acao TEXT NOT NULL,
            datahora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        )");
        
        // Create clients table
        $conn->exec("CREATE TABLE IF NOT EXISTS clientes (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            cnpj VARCHAR(100),
            telefone VARCHAR(50),
            email VARCHAR(255),
            endereco TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create machinery table
        $conn->exec("CREATE TABLE IF NOT EXISTS maquinarios (
            id SERIAL PRIMARY KEY,
            cliente_id INTEGER NOT NULL,
            tipo VARCHAR(100) NOT NULL,
            marca VARCHAR(100) NOT NULL,
            modelo VARCHAR(100) NOT NULL,
            numero_serie VARCHAR(100),
            ano INTEGER,
            ultima_manutencao DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id)
        )");
        
        // Create service orders table
        $conn->exec("CREATE TABLE IF NOT EXISTS ordens_servico (
            id SERIAL PRIMARY KEY,
            cliente_id INTEGER NOT NULL,
            maquinario_id INTEGER NOT NULL,
            descricao TEXT NOT NULL,
            status VARCHAR(50) NOT NULL,
            data_abertura DATE NOT NULL,
            data_fechamento DATE,
            valor_total DECIMAL(10,2),
            satisfaction_rating FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cliente_id) REFERENCES clientes(id),
            FOREIGN KEY (maquinario_id) REFERENCES maquinarios(id)
        )");
        
        // Create employees table
        $conn->exec("CREATE TABLE IF NOT EXISTS funcionarios (
            id SERIAL PRIMARY KEY,
            nome VARCHAR(255) NOT NULL,
            cargo VARCHAR(100) NOT NULL,
            email VARCHAR(255),
            ativo SMALLINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create relationship table between service orders and employees
        $conn->exec("CREATE TABLE IF NOT EXISTS os_funcionarios (
            id SERIAL PRIMARY KEY,
            ordem_id INTEGER NOT NULL,
            funcionario_id INTEGER NOT NULL,
            FOREIGN KEY (ordem_id) REFERENCES ordens_servico(id),
            FOREIGN KEY (funcionario_id) REFERENCES funcionarios(id)
        )");
        
        // Create financial table
        $conn->exec("CREATE TABLE IF NOT EXISTS financeiro (
            id SERIAL PRIMARY KEY,
            tipo VARCHAR(50) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
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
