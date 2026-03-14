 <?php
/**
 * API para gerenciamento de SMS
 * Endpoint: /api/sms.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../config/database.php';
require_once '../includes/sms.php';
require_once '../includes/auth.php';

$auth = new Auth();
$smsManager = new SMSManager();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Verifica autenticação
if (!$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

switch ($method) {
    case 'POST':
        if (isset($_GET['reenviar'])) {
            // Reenvia código de verificação
            reenviarCodigo();
        } else if (isset($_GET['lembrete'])) {
            // Envia lembrete manualmente
            enviarLembrete();
        } else if (isset($_GET['teste'])) {
            // Envia SMS de teste
            enviarTeste();
        }
        break;
        
    case 'GET':
        if (isset($_GET['status'])) {
            // Verifica status do serviço de SMS
            verificarStatus();
        } else if (isset($_GET['logs'])) {
            // Busca logs de SMS
            getLogsSMS();
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

/**
 * Reenvia código de verificação
 */
function reenviarCodigo() {
    global $db;
    
    try {
        if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_telefone'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
            return;
        }
        
        // Verifica rate limit
        $telefone_hash = hash('sha256', $_SESSION['temp_telefone']);
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as count FROM sms_logs 
            WHERE telefone_hash = ? 
            AND enviado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$telefone_hash]);
        $result = $stmt->fetch();
        
        if ($result['count'] >= 3) {
            echo json_encode(['success' => false, 'message' => 'Limite de tentativas excedido. Tente novamente mais tarde.']);
            return;
        }
        
        // Gera novo código
        $novo_codigo = sprintf("%06d", mt_rand(1, 999999));
        $_SESSION['verification_code'] = $novo_codigo;
        $_SESSION['verification_expires'] = time() + 300; // 5 minutos
        
        // Simula envio de SMS
        error_log("Novo código enviado para {$_SESSION['temp_telefone']}: {$novo_codigo}");
        
        // Registra no log
        $stmt = $db->prepare("INSERT INTO sms_logs (telefone_hash) VALUES (?)");
        $stmt->execute([$telefone_hash]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Código reenviado com sucesso'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao reenviar código'
        ]);
    }
}

/**
 * Envia lembrete de agendamento
 */
function enviarLembrete() {
    global $smsManager;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['agendamento_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID do agendamento é obrigatório']);
            return;
        }
        
        $result = $smsManager->sendAppointmentReminder($data['agendamento_id']);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Lembrete enviado com sucesso'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao enviar lembrete'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao enviar lembrete'
        ]);
    }
}

/**
 * Envia SMS de teste
 */
function enviarTeste() {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['telefone']) || !isset($data['mensagem'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Telefone e mensagem são obrigatórios']);
            return;
        }
        
        // Simula envio
        error_log("SMS de teste para {$data['telefone']}: {$data['mensagem']}");
        
        echo json_encode([
            'success' => true,
            'message' => 'SMS de teste enviado (simulado)'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao enviar SMS de teste'
        ]);
    }
}

/**
 * Verifica status do serviço de SMS
 */
function verificarStatus() {
    echo json_encode([
        'success' => true,
        'status' => 'operacional',
        'modo' => 'simulado', // Em produção seria 'real' com integração
        'limite_diario' => 100,
        'enviados_hoje' => 0
    ]);
}

/**
 * Busca logs de SMS
 */
function getLogsSMS() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT * FROM sms_logs 
            ORDER BY enviado_em DESC 
            LIMIT 50
        ");
        
        $logs = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar logs'
        ]);
    }
}
?>
