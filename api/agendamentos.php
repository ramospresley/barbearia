<?php
/**
 * API para gerenciamento de agendamentos
 * Endpoint: /api/agendamentos.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/sms.php';

$auth = new Auth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Verifica autenticação para métodos que não são GET públicos
if ($method !== 'GET' && !$auth->checkAuth()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

switch ($method) {
    case 'GET':
        // Lista agendamentos
        if (isset($_GET['id'])) {
            // Busca um agendamento específico
            getAgendamento($_GET['id']);
        } else if (isset($_GET['data'])) {
            // Busca agendamentos por data
            getAgendamentosPorData($_GET['data']);
        } else if (isset($_GET['cliente'])) {
            // Busca agendamentos do cliente
            getAgendamentosCliente();
        } else {
            // Lista todos (com filtros)
            listarAgendamentos();
        }
        break;
        
    case 'POST':
        // Cria novo agendamento
        criarAgendamento();
        break;
        
    case 'PUT':
        // Atualiza agendamento
        atualizarAgendamento();
        break;
        
    case 'DELETE':
        // Cancela agendamento
        if (isset($_GET['id'])) {
            cancelarAgendamento($_GET['id']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

/**
 * Lista todos os agendamentos com filtros
 */
function listarAgendamentos() {
    global $db;
    
    try {
        $where = [];
        $params = [];
        
        // Filtros
        if (isset($_GET['status'])) {
            $where[] = "a.status = ?";
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['data_inicio'])) {
            $where[] = "a.data_agendamento >= ?";
            $params[] = $_GET['data_inicio'];
        }
        
        if (isset($_GET['data_fim'])) {
            $where[] = "a.data_agendamento <= ?";
            $params[] = $_GET['data_fim'];
        }
        
        if (isset($_GET['cliente_id'])) {
            $where[] = "a.cliente_id = ?";
            $params[] = $_GET['cliente_id'];
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        $sql = "
            SELECT 
                a.*,
                c.primeiro_nome,
                c.telefone_original,
                c.telefone_hash,
                GROUP_CONCAT(DISTINCT s.nome SEPARATOR ', ') as servicos,
                GROUP_CONCAT(DISTINCT s.preco SEPARATOR ',') as precos,
                GROUP_CONCAT(DISTINCT s.id SEPARATOR ',') as servico_ids
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            {$whereClause}
            GROUP BY a.id
            ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $agendamentos = $stmt->fetchAll();
        
        // Formata os dados
        foreach ($agendamentos as &$ag) {
            $ag['data_formatada'] = date('d/m/Y', strtotime($ag['data_agendamento']));
            $ag['hora_formatada'] = date('H:i', strtotime($ag['hora_agendamento']));
            $ag['criado_em_formatado'] = date('d/m/Y H:i', strtotime($ag['criado_em']));
            
            // Calcula total
            if ($ag['precos']) {
                $precos = explode(',', $ag['precos']);
                $ag['total'] = array_sum($precos);
                $ag['total_formatado'] = 'R$ ' . number_format($ag['total'], 2, ',', '.');
            } else {
                $ag['total'] = 0;
                $ag['total_formatado'] = 'R$ 0,00';
            }
            
            // Array de serviços
            if ($ag['servico_ids']) {
                $ag['servicos_array'] = [
                    'ids' => explode(',', $ag['servico_ids']),
                    'nomes' => explode(', ', $ag['servicos']),
                    'precos' => explode(',', $ag['precos'])
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'data' => $agendamentos,
            'total' => count($agendamentos),
            'filtros' => [
                'status' => $_GET['status'] ?? 'todos',
                'periodo' => isset($_GET['data_inicio']) ? $_GET['data_inicio'] . ' até ' . ($_GET['data_fim'] ?? 'hoje') : 'todos'
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao listar agendamentos',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Busca um agendamento específico
 */
function getAgendamento($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT 
                a.*,
                c.primeiro_nome,
                c.telefone_original,
                c.telefone_hash,
                GROUP_CONCAT(s.id) as servico_ids,
                GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos,
                GROUP_CONCAT(s.preco) as precos
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            WHERE a.id = ?
            GROUP BY a.id
        ");
        
        $stmt->execute([$id]);
        $agendamento = $stmt->fetch();
        
        if (!$agendamento) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
            return;
        }
        
        // Formata dados
        $agendamento['data_formatada'] = date('d/m/Y', strtotime($agendamento['data_agendamento']));
        $agendamento['hora_formatada'] = date('H:i', strtotime($agendamento['hora_agendamento']));
        
        if ($agendamento['precos']) {
            $precos = explode(',', $agendamento['precos']);
            $agendamento['total'] = array_sum($precos);
            $agendamento['total_formatado'] = 'R$ ' . number_format($agendamento['total'], 2, ',', '.');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $agendamento
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar agendamento',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Busca agendamentos por data
 */
function getAgendamentosPorData($data) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT 
                a.*,
                c.primeiro_nome,
                GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            WHERE a.data_agendamento = ?
            AND a.status != 'cancelado'
            GROUP BY a.id
            ORDER BY a.hora_agendamento ASC
        ");
        
        $stmt->execute([$data]);
        $agendamentos = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'agendamentos' => $agendamentos,
            'total' => count($agendamentos)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar agendamentos por data'
        ]);
    }
}

/**
 * Busca agendamentos do cliente logado
 */
function getAgendamentosCliente() {
    global $db, $auth;
    
    try {
        $user = $auth->checkAuth();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Não autorizado']);
            return;
        }
        
        $stmt = $db->prepare("
            SELECT 
                a.*,
                GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos,
                GROUP_CONCAT(s.preco SEPARATOR ',') as precos
            FROM agendamentos a
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            WHERE a.cliente_id = ?
            GROUP BY a.id
            ORDER BY a.data_agendamento DESC, a.hora_agendamento DESC
        ");
        
        $stmt->execute([$user['user_id']]);
        $agendamentos = $stmt->fetchAll();
        
        foreach ($agendamentos as &$ag) {
            $ag['data_formatada'] = date('d/m/Y', strtotime($ag['data_agendamento']));
            $ag['hora_formatada'] = date('H:i', strtotime($ag['hora_agendamento']));
            
            if ($ag['precos']) {
                $precos = explode(',', $ag['precos']);
                $ag['total'] = array_sum($precos);
                $ag['total_formatado'] = 'R$ ' . number_format($ag['total'], 2, ',', '.');
            }
            
            // Status em português
            $status_labels = [
                'agendado' => 'Agendado',
                'confirmado' => 'Confirmado',
                'cancelado' => 'Cancelado',
                'concluido' => 'Concluído'
            ];
            $ag['status_label'] = $status_labels[$ag['status']] ?? $ag['status'];
        }
        
        echo json_encode([
            'success' => true,
            'agendamentos' => $agendamentos
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao buscar agendamentos do cliente'
        ]);
    }
}

/**
 * Cria um novo agendamento
 */
function criarAgendamento() {
    global $db, $auth;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validações
        if (!isset($data['servicos']) || empty($data['servicos'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selecione pelo menos um serviço']);
            return;
        }
        
        if (!isset($data['data']) || !isset($data['hora'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Data e hora são obrigatórios']);
            return;
        }
        
        $user = $auth->checkAuth();
        
        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Faça login para agendar']);
            return;
        }
        
        // Verifica se horário está disponível
        if (!isHorarioDisponivel($data['data'], $data['hora'])) {
            echo json_encode(['success' => false, 'message' => 'Horário não disponível']);
            return;
        }
        
        // Inicia transação
        $db->beginTransaction();
        
        // Gera token de confirmação
        $token = generateConfirmationToken();
        
        // Insere agendamento
        $stmt = $db->prepare("
            INSERT INTO agendamentos (cliente_id, data_agendamento, hora_agendamento, token_confirmacao) 
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $user['user_id'],
            $data['data'],
            $data['hora'],
            $token
        ]);
        
        $agendamento_id = $db->lastInsertId();
        
        // Insere serviços do agendamento
        $stmt = $db->prepare("INSERT INTO agendamento_servicos (agendamento_id, servico_id) VALUES (?, ?)");
        
        foreach ($data['servicos'] as $servico_id) {
            $stmt->execute([$agendamento_id, $servico_id]);
        }
        
        // Commit da transação
        $db->commit();
        
        // Envia SMS de confirmação
        $sms = new SMSManager();
        $sms->sendAppointmentConfirmation($agendamento_id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Agendamento realizado com sucesso!',
            'agendamento_id' => $agendamento_id,
            'token' => $token
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao criar agendamento',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Atualiza um agendamento
 */
function atualizarAgendamento() {
    global $db;
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID do agendamento não fornecido']);
            return;
        }
        
        $updates = [];
        $params = [];
        
        // Campos que podem ser atualizados
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['data'])) {
            $updates[] = "data_agendamento = ?";
            $params[] = $data['data'];
        }
        
        if (isset($data['hora'])) {
            $updates[] = "hora_agendamento = ?";
            $params[] = $data['hora'];
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nenhum campo para atualizar']);
            return;
        }
        
        $params[] = $data['id'];
        
        $sql = "UPDATE agendamentos SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Agendamento atualizado com sucesso'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar agendamento'
        ]);
    }
}

/**
 * Cancela um agendamento
 */
function cancelarAgendamento($id) {
    global $db;
    
    try {
        // Verifica se o agendamento existe
        $stmt = $db->prepare("SELECT status FROM agendamentos WHERE id = ?");
        $stmt->execute([$id]);
        $agendamento = $stmt->fetch();
        
        if (!$agendamento) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Agendamento não encontrado']);
            return;
        }
        
        if ($agendamento['status'] == 'cancelado') {
            echo json_encode(['success' => false, 'message' => 'Agendamento já está cancelado']);
            return;
        }
        
        // Cancela o agendamento
        $stmt = $db->prepare("UPDATE agendamentos SET status = 'cancelado' WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Agendamento cancelado com sucesso'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao cancelar agendamento'
        ]);
    }
}

/**
 * Verifica se horário está disponível (função auxiliar)
 */
function isHorarioDisponivel($data, $hora) {
    global $db;
    
    // Horários fixos permitidos
    $horarios_permitidos = [
        '08:20', '09:00', '09:40', '10:30', '11:20', '12:00',
        '13:00', '13:40', '14:20', '15:00', '15:40', '16:30',
        '17:40', '18:20', '19:20', '20:00', '21:00', '21:40',
        '22:30', '23:00'
    ];
    
    if (!in_array($hora, $horarios_permitidos)) {
        return false;
    }
    
    // Verifica se já existe agendamento
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM agendamentos 
        WHERE data_agendamento = ? 
        AND hora_agendamento = ? 
        AND status IN ('agendado', 'confirmado')
    ");
    $stmt->execute([$data, $hora]);
    $agendado = $stmt->fetch();
    
    if ($agendado['count'] > 0) {
        return false;
    }
    
    // Verifica se está bloqueado
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM horarios_bloqueados 
        WHERE data_bloqueio = ? AND hora_bloqueio = ?
    ");
    $stmt->execute([$data, $hora]);
    $bloqueado = $stmt->fetch();
    
    return $bloqueado['count'] === 0;
}

/**
 * Gera token único para confirmação
 */
function generateConfirmationToken() {
    return bin2hex(random_bytes(32));
}
?>