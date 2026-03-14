 <?php
/**
 * Configuração do banco de dados
 * Arquivo de conexão PDO com MySQL
 */

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'barbershop');
define('DB_USER', 'root');
define('DB_PASS', ''); // Senha padrão do XAMPP é vazia
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $connection;
    
    /**
     * Construtor privado para padrão Singleton
     */
    private function __construct() {
        try {
            // String de conexão DSN
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            
            // Opções PDO para melhor performance e segurança
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Lança exceções em erros
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Retorna arrays associativos
                PDO::ATTR_EMULATE_PREPARES => false, // Usa prepared statements nativos
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET
            ];
            
            // Cria a conexão
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Log do erro e mensagem amigável para o usuário
            error_log('Erro de conexão: ' . $e->getMessage());
            die('Erro ao conectar ao banco de dados. Por favor, tente novamente mais tarde.');
        }
    }
    
    /**
     * Método para obter a instância única da conexão
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Retorna a conexão PDO
     * @return PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Previne clonagem do objeto
     */
    private function __clone() {}
    
    /**
     * Previne desserialização
     */
    public function __wakeup() {}
}
?>
