<?php
/**
 * API para integração com WhatsApp
 * Endpoint: /api/whatsapp.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/WhatsApp.php';

$auth = new Auth();
$user = $auth->checkAuth();

// Verifica autenticação
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

$whatsapp = new WhatsApp();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['action'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
            exit();
        }
        
        switch ($data['action']) {
            case 'enviar_mensagem':
                enviarMensagem($whatsapp, $data);
                break;
                
            case 'enviar_template':
                enviarTemplate($whatsapp, $data);
                break;
                
            case 'confirmacao_agendamento':
                enviarConfirmacao($whatsapp, $data);
                break;
                
            case 'lembrete_agendamento':
                enviarLembrete($whatsapp, $data);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        }
        break;
        
    case 'GET':
        if (isset($_GET['status'])) {
            verificarStatus($whatsapp);
        } elseif (isset($_GET['logs'])) {
            buscarLogs();
        } elseif (isset($_GET['estatisticas'])) {
            buscarEstatisticas();
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

function enviarMensagem($whatsapp, $data) {
    if (!isset($data['para']) || !isset($data['mensagem'])) {
        echo json_encode(['success' => false, 'message' => 'Número e mensagem são obrigatórios']);
        return;
    }
    
    $resultado = $whatsapp->sendMessage($data['para'], $data['mensagem']);
    echo json_encode($resultado);
}

function enviarTemplate($whatsapp, $data) {
    if (!isset($data['para']) || !isset($data['template'])) {
        echo json_encode(['success' => false, 'message' => 'Número e template são obrigatórios']);
        return;
    }
    
    $variaveis = $data['variaveis'] ?? [];
    $resultado = $whatsapp->sendTemplate($data['para'], $data['template'], $variaveis);
    echo json_encode($resultado);
}

function enviarConfirmacao($whatsapp, $data) {
    if (!isset($data['agendamento_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento é obrigatório']);
        return;
    }
    
    $resultado = $whatsapp->sendConfirmacaoAgendamento($data['agendamento_id']);
    echo json_encode($resultado);
}

function enviarLembrete($whatsapp, $data) {
    if (!isset($data['agendamento_id'])) {
        echo json_encode(['success' => false, 'message' => 'ID do agendamento é obrigatório']);
        return;
    }
    
    $resultado = $whatsapp->sendLembreteAgendamento($data['agendamento_id']);
    echo json_encode($resultado);
}

function verificarStatus($whatsapp) {
    echo json_encode([
        'success' => true,
        'provider' => $whatsapp->provider,
        'simulando' => $whatsapp->config['options']['simular_envio'],
        'status' => 'operacional'
    ]);
}

function buscarLogs() {
    global $whatsapp;
    $log = new WhatsAppLog();
    
    $numero = $_GET['numero'] ?? null;
    $limite = $_GET['limite'] ?? 50;
    
    if ($numero) {
        $logs = $log->buscarPorNumero($numero, $limite);
    } else {
        // Busca todos (últimos 100)
        $logs = $log->buscarPorPeriodo(
            date('Y-m-d', strtotime('-30 days')),
            date('Y-m-d')
        );
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
}

function buscarEstatisticas() {
    $log = new WhatsAppLog();
    $periodo = $_GET['periodo'] ?? 'hoje';
    
    $estatisticas = $log->getEstatisticas($periodo);
    
    echo json_encode([
        'success' => true,
        'periodo' => $periodo,
        'estatisticas' => $estatisticas
    ]);
}