<?php
/**
 * Classe para logging de mensagens WhatsApp
 */

class WhatsAppLog {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Registra envio de mensagem
     */
    public function registrar($dados) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO whatsapp_logs 
                (para, mensagem, status, provider, resposta, erro, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $dados['para'] ?? null,
                $dados['mensagem'] ?? null,
                $dados['status'] ?? 'enviado',
                $dados['provider'] ?? 'desconhecido',
                $dados['resposta'] ?? null,
                $dados['erro'] ?? null
            ]);
            
            return $this->db->lastInsertId();
            
        } catch (PDOException $e) {
            error_log('Erro ao registrar log WhatsApp: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Busca logs por número
     */
    public function buscarPorNumero($numero, $limite = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM whatsapp_logs 
            WHERE para = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$numero, $limite]);
        return $stmt->fetchAll();
    }
    
    /**
     * Busca logs por período
     */
    public function buscarPorPeriodo($data_inicio, $data_fim) {
        $stmt = $this->db->prepare("
            SELECT * FROM whatsapp_logs 
            WHERE DATE(created_at) BETWEEN ? AND ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$data_inicio, $data_fim]);
        return $stmt->fetchAll();
    }
    
    /**
     * Estatísticas de envio
     */
    public function getEstatisticas($periodo = 'hoje') {
        switch ($periodo) {
            case 'hoje':
                $where = "DATE(created_at) = CURDATE()";
                break;
            case 'semana':
                $where = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'mes':
                $where = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            default:
                $where = "1=1";
        }
        
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as enviados,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as falhas,
                COUNT(DISTINCT para) as numeros_unicos,
                provider,
                DATE(created_at) as data
            FROM whatsapp_logs
            WHERE {$where}
            GROUP BY provider, DATE(created_at)
            ORDER BY data DESC
        ");
        
        return $stmt->fetchAll();
    }
}