 <?php
/**
 * Funções para envio de SMS
 * Integração com API de SMS (Twilio como exemplo)
 */

class SMSManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Envia lembrete de agendamento
     * @param int $agendamento_id ID do agendamento
     * @return bool Sucesso do envio
     */
    public function sendAppointmentReminder($agendamento_id) {
        try {
            // Busca dados do agendamento
            $stmt = $this->db->prepare("
                SELECT a.*, c.primeiro_nome, c.telefone_original,
                       GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
                LEFT JOIN servicos s ON ag.servico_id = s.id
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$agendamento_id]);
            $agendamento = $stmt->fetch();
            
            if (!$agendamento || empty($agendamento['telefone_original'])) {
                return false;
            }
            
            // Verifica rate limit
            $telefone_hash = hash('sha256', $agendamento['telefone_original']);
            if (!$this->checkRateLimit($telefone_hash)) {
                error_log("Rate limit excedido para {$telefone_hash}");
                return false;
            }
            
            // Formata data e hora
            $data_formatada = date('d/m/Y', strtotime($agendamento['data_agendamento']));
            $hora_formatada = date('H:i', strtotime($agendamento['hora_agendamento']));
            
            // Monta mensagem
            $mensagem = "Olá {$agendamento['primeiro_nome']}! Lembrete: Seu agendamento na Barbershop é amanhã, {$data_formatada} às {$hora_formatada}. Serviços: {$agendamento['servicos']}. Confirme em: " . BASE_URL . "/confirmar.php?token={$agendamento['token_confirmacao']}";
            
            // Em produção: Integrar com API real
            // Exemplo com Twilio (comentado)
            /*
            $twilio = new Twilio\Rest\Client(TWILIO_SID, TWILIO_TOKEN);
            $twilio->messages->create(
                $agendamento['telefone_original'],
                [
                    'from' => TWILIO_PHONE,
                    'body' => $mensagem
                ]
            );
            */
            
            // Simula envio com log
            error_log("SMS de lembrete enviado para {$agendamento['telefone_original']}: {$mensagem}");
            
            // Registra o envio
            $stmt = $this->db->prepare("
                UPDATE agendamentos SET sms_enviado = TRUE 
                WHERE id = ?
            ");
            $stmt->execute([$agendamento_id]);
            
            // Log para rate limit
            $stmt = $this->db->prepare("
                INSERT INTO sms_logs (telefone_hash) VALUES (?)
            ");
            $stmt->execute([$telefone_hash]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Erro ao enviar SMS: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Envia confirmação de agendamento
     * @param int $agendamento_id ID do agendamento
     */
    public function sendAppointmentConfirmation($agendamento_id) {
        // Similar ao reminder, mas com mensagem de confirmação
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, c.primeiro_nome, c.telefone_original,
                       GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
                FROM agendamentos a
                JOIN clientes c ON a.cliente_id = c.id
                LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
                LEFT JOIN servicos s ON ag.servico_id = s.id
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$agendamento_id]);
            $agendamento = $stmt->fetch();
            
            if (!$agendamento || empty($agendamento['telefone_original'])) {
                return false;
            }
            
            $data_formatada = date('d/m/Y', strtotime($agendamento['data_agendamento']));
            $hora_formatada = date('H:i', strtotime($agendamento['hora_agendamento']));
            
            $mensagem = "Olá {$agendamento['primeiro_nome']}! Seu agendamento na Barbershop foi confirmado para {$data_formatada} às {$hora_formatada}. Serviços: {$agendamento['servicos']}. Para cancelar: " . BASE_URL . "/cancelar.php?id={$agendamento['id']}";
            
            error_log("SMS de confirmação enviado: {$mensagem}");
            
            return true;
            
        } catch (Exception $e) {
            error_log('Erro ao enviar SMS: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica rate limit para SMS
     * @param string $telefone_hash Hash do telefone
     * @return bool
     */
    private function checkRateLimit($telefone_hash) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM sms_logs 
            WHERE telefone_hash = ? 
            AND enviado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$telefone_hash]);
        $result = $stmt->fetch();
        
        return $result['count'] < 3;
    }
}
?>
