 <?php
/**
 * Funções de autenticação e gerenciamento de sessão
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Autentica um usuário via primeiro nome e telefone
     * @param string $primeiro_nome Primeiro nome do cliente
     * @param string $telefone Telefone completo
     * @return array|false Dados do cliente ou false se falhar
     */
    public function login($primeiro_nome, $telefone) {
        try {
            // Validação básica
            if (empty($primeiro_nome) || empty($telefone)) {
                return ['success' => false, 'message' => 'Nome e telefone são obrigatórios'];
            }
            
            // Gera hash do telefone para segurança
            $telefone_hash = hash('sha256', $telefone);
            
            // Verifica rate limiting (máximo 3 tentativas por hora)
            if (!$this->checkRateLimit($telefone_hash)) {
                return ['success' => false, 'message' => 'Muitas tentativas. Tente novamente mais tarde.'];
            }
            
            // Busca ou cria o cliente
            $stmt = $this->db->prepare("
                INSERT INTO clientes (primeiro_nome, telefone_hash, telefone_original) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                telefone_original = VALUES(telefone_original),
                ultimo_login = NOW()
            ");
            
            // Armazena telefone original temporariamente para SMS
            $stmt->execute([$primeiro_nome, $telefone_hash, $telefone]);
            
            // Recupera o cliente
            $stmt = $this->db->prepare("SELECT id, primeiro_nome FROM clientes WHERE telefone_hash = ?");
            $stmt->execute([$telefone_hash]);
            $cliente = $stmt->fetch();
            
            // Gera código de verificação (6 dígitos)
            $codigo_verificacao = sprintf("%06d", mt_rand(1, 999999));
            
            // Armazena código na sessão temporária
            $_SESSION['verification_code'] = $codigo_verificacao;
            $_SESSION['verification_expires'] = time() + 300; // 5 minutos
            $_SESSION['temp_user_id'] = $cliente['id'];
            
            // Envia SMS com código (função separada)
            $this->sendVerificationSMS($telefone, $codigo_verificacao);
            
            return ['success' => true, 'message' => 'Código de verificação enviado', 'requires_2fa' => true];
            
        } catch (PDOException $e) {
            error_log('Erro no login: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao processar login'];
        }
    }
    
    /**
     * Verifica o código de autenticação 2FA
     * @param string $codigo Código recebido por SMS
     * @return array Resultado da verificação
     */
    public function verify2FA($codigo) {
        // Verifica se há código na sessão e se não expirou
        if (!isset($_SESSION['verification_code']) || 
            !isset($_SESSION['verification_expires']) ||
            $_SESSION['verification_expires'] < time() ||
            !isset($_SESSION['temp_user_id'])) {
            return ['success' => false, 'message' => 'Código expirado. Faça login novamente.'];
        }
        
        // Verifica se o código está correto
        if ($_SESSION['verification_code'] != $codigo) {
            return ['success' => false, 'message' => 'Código inválido'];
        }
        
        // Busca dados completos do cliente
        $stmt = $this->db->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$_SESSION['temp_user_id']]);
        $cliente = $stmt->fetch();
        
        if (!$cliente) {
            return ['success' => false, 'message' => 'Usuário não encontrado'];
        }
        
        // Gera JWT
        $token = JWT::generate([
            'user_id' => $cliente['id'],
            'nome' => $cliente['primeiro_nome']
        ]);
        
        // Limpa dados temporários
        unset($_SESSION['verification_code']);
        unset($_SESSION['verification_expires']);
        unset($_SESSION['temp_user_id']);
        
        // Armazena token na sessão
        $_SESSION['jwt_token'] = $token;
        $_SESSION['user_id'] = $cliente['id'];
        
        return ['success' => true, 'token' => $token, 'user' => $cliente];
    }
    
    /**
     * Verifica rate limit para SMS
     * @param string $telefone_hash Hash do telefone
     * @return bool True se pode enviar SMS
     */
    private function checkRateLimit($telefone_hash) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM sms_logs 
            WHERE telefone_hash = ? 
            AND enviado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$telefone_hash]);
        $result = $stmt->fetch();
        
        return $result['count'] < 3; // Máximo 3 SMS por hora
    }
    
    /**
     * Simula envio de SMS (em produção, integre com API real)
     * @param string $telefone Telefone do destinatário
     * @param string $codigo Código de verificação
     */
    private function sendVerificationSMS($telefone, $codigo) {
        // Log do envio para rate limiting
        $telefone_hash = hash('sha256', $telefone);
        $stmt = $this->db->prepare("INSERT INTO sms_logs (telefone_hash) VALUES (?)");
        $stmt->execute([$telefone_hash]);
        
        // Em produção: Integrar com serviço de SMS (Twilio, etc)
        // Por enquanto, simulamos o envio com log
        error_log("SMS enviado para {$telefone}: Seu código de verificação é {$codigo}");
        
        // Para testes, armazenamos o código na sessão para debug
        $_SESSION['debug_code'] = $codigo;
    }
    
    /**
     * Verifica se o usuário está autenticado via JWT
     * @return array|false Dados do usuário ou false
     */
    public function checkAuth() {
        if (!isset($_SESSION['jwt_token'])) {
            return false;
        }
        
        $payload = JWT::validate($_SESSION['jwt_token']);
        
        if (!$payload) {
            unset($_SESSION['jwt_token']);
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Faz logout do usuário
     */
    public function logout() {
        unset($_SESSION['jwt_token']);
        unset($_SESSION['user_id']);
        session_destroy();
    }
}
?>
