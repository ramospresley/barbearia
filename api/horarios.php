 <?php
/**
 * API para gerenciamento de horários
 * Endpoint: /api/horarios.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Horários fixos da barbearia
const HORARIOS_FIXOS = [
    '08:20', '09:00', '09:40', '10:30', '11:20', '12:00',
    '13:00', '13:40', '14:20', '15:00', '15:40', '16:30',
    '17:40', '18:20', '19:20', '20:00', '21:00', '21:40',
    '22:30', '23:00'
];

switch ($method) {
    case 'GET':
        if (isset($_GET['data'])) {
            // Retorna horários disponíveis para uma data específica
            getHorariosDisponiveis($_GET['data']);
        } else if (isset($_GET['bloqueados'])) {
            // Retorna horários bloqueados
            getHorariosBloqueados();
        } else {
            // Retorna todos os horários fixos
            echo json_encode([
                'success' => true,
                'horarios' => HORARIOS_FIXOS
            ]);
        }
        break;
        
    case 'POST':
        // Bloqueia um horário
        bloquearHorario();
        break;
        
    case 'DELETE':
        // Desbloqueia um horário
        if (isset($_GET['id'])) {
            desbloquearHorario($_GET['id']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

/**
 * Retorna horários disponíveis para uma data específica
 * @param string $data Data no formato Y-m-d
 */
function getHorariosDisponiveis($data) {
    global $db;
    
    try {
        // Valida formato da data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Formato de data inválido']);
            return;
        }
        
        // Busca agendamentos confirmados para a data
        $stmt = $db->prepare("
            SELECT hora_agendamento 
            FROM agendamentos 
            WHERE data_agendamento = ? 
            AND status IN ('agendado', 'confirmado')
        ");
        $stmt->execute([$data]);
        $agendados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Busca horários bloqueados
        $stmt = $db->prepare("
            SELECT hora_bloqueio 
            FROM horarios_bloqueados 
            WHERE data_bloqueio = ?
        ");
        $stmt->execute([$data]);
        $bloqueados = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Combina horários ocupados
        $ocupados = array_merge($agendados, $bloqueados);
        
        // Filtra horários disponíveis
        $disponiveis = array_values(array_diff(HORARIOS_FIXOS, $ocupados));
        
        // Verifica se a data é válida (não pode ser no passado)
        $hoje = date('Y-m-d');
        $podeAgendar = $data >= $hoje;
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'horarios_disponiveis' => $disponiveis,
            'horarios_ocupados' => array_values($ocupados),
            'total_disponivel' => count($disponiveis),
            'pode_agendar' => $podeAgendar
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar horários',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Retorna todos os horários bloqueados
 */
function getHorariosBloqueados() {
    global $db;
    
    try {
        $stmt = $db->query("
            SELECT * FROM horarios_bloqueados 
            WHERE data_bloqueio >= CURDATE()
            ORDER BY data_bloqueio ASC, hora_bloqueio ASC
        ");
        
        $bloqueados = $stmt->fetchAll();
        
        // Formata os dados
        foreach ($bloqueados as &$b) {
            $b['data_formatada'] = date('d/m/Y', strtotime($b['data_bloqueio']));
            $b['hora_formatada'] = date('H:i', strtotime($b['hora_bloqueio']));
        }
        
        echo json_encode([
            'success' => true,
            'bloqueados' => $bloqueados
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar horários bloqueados'
        ]);
    }
}

/**
 * Bloqueia um horário específico
 */
function bloquearHorario() {
    global $db;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validações
        if (!isset($data['data']) || !isset($data['hora'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data e hora são obrigatórios']);
            return;
        }
        
        // Verifica se horário já está bloqueado
        $stmt = $db->prepare("
            SELECT id FROM horarios_bloqueados 
            WHERE data_bloqueio = ? AND hora_bloqueio = ?
        ");
        $stmt->execute([$data['data'], $data['hora']]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Horário já está bloqueado']);
            return;
        }
        
        // Verifica se horário está disponível para agendamento
        if (!in_array($data['hora'], HORARIOS_FIXOS)) {
            echo json_encode(['success' => false, 'message' => 'Horário inválido']);
            return;
        }
        
        // Insere bloqueio
        $stmt = $db->prepare("
            INSERT INTO horarios_bloqueados (data_bloqueio, hora_bloqueio, motivo) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $data['data'],
            $data['hora'],
            $data['motivo'] ?? 'Bloqueado pelo administrador'
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Horário bloqueado com sucesso',
            'id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao bloquear horário'
        ]);
    }
}

/**
 * Desbloqueia um horário
 * @param int $id ID do bloqueio
 */
function desbloquearHorario($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM horarios_bloqueados WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Horário desbloqueado com sucesso'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Bloqueio não encontrado'
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao desbloquear horário'
        ]);
    }
}
?>
