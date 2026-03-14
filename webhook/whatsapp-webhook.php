<?php
/**
 * Webhook para receber mensagens e confirmações do WhatsApp
 * URL: https://seudominio.com/webhook/whatsapp-webhook.php
 */

require_once '../config/database.php';
require_once '../includes/WhatsAppLog.php';

// Log do webhook recebido
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Registra no banco
$db = Database::getInstance()->getConnection();
$stmt = $db->prepare("
    INSERT INTO whatsapp_webhooks (payload, tipo, created_at) 
    VALUES (?, ?, NOW())
");
$stmt->execute([$payload, $data['type'] ?? 'desconhecido']);

// Processa diferentes tipos de webhook
if (isset($data['type'])) {
    switch ($data['type']) {
        case 'message':
            processarMensagemRecebida($data);
            break;
        case 'status':
            atualizarStatusMensagem($data);
            break;
        case 'delivery':
            confirmarEntrega($data);
            break;
    }
}

// Resposta para o provedor
http_response_code(200);
echo json_encode(['status' => 'received']);

function processarMensagemRecebida($data) {
    // Implementar lógica para responder automaticamente
    // Ex: receber confirmações, responder com menu, etc.
}

function atualizarStatusMensagem($data) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE whatsapp_logs 
        SET status = ?, updated_at = NOW() 
        WHERE message_id = ?
    ");
    $stmt->execute([$data['status'], $data['message_id']]);
}

function confirmarEntrega($data) {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("
        UPDATE whatsapp_logs 
        SET status = 'entregue', updated_at = NOW() 
        WHERE message_id = ?
    ");
    $stmt->execute([$data['message_id']]);
}
?>