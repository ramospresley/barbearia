<?php
/**
 * Classe para integração com WhatsApp
 * Gerencia envio de mensagens via diferentes provedores
 */

require_once __DIR__ . '/../config/whatsapp.php';

class WhatsApp {
    private $provider;
    private $config;
    private $db;
    private $log;
    
    /**
     * Construtor
     * @param string $provider Provedor a ser usado (opcional)
     */
    public function __construct($provider = null) {
        $this->config = require __DIR__ . '/../config/whatsapp.php';
        $this->provider = $provider ?? $this->config['provider'];
        $this->db = Database::getInstance()->getConnection();
        $this->log = new WhatsAppLog();
    }
    
    /**
     * Envia mensagem via WhatsApp
     * @param string $para Número do destinatário
     * @param string $mensagem Conteúdo da mensagem
     * @param array $options Opções adicionais
     * @return array Resultado do envio
     */
    public function sendMessage($para, $mensagem, $options = []) {
        // Valida número
        $numero = $this->formatarNumero($para);
        if (!$this->validarNumero($numero)) {
            return [
                'success' => false,
                'message' => 'Número de WhatsApp inválido'
            ];
        }
        
        // Verifica rate limit
        if (!$this->checkRateLimit($numero)) {
            return [
                'success' => false,
                'message' => 'Limite de mensagens excedido. Tente novamente mais tarde.'
            ];
        }
        
        // Verifica modo simulação
        if ($this->config['options']['simular_envio']) {
            return $this->simularEnvio($numero, $mensagem, $options);
        }
        
        // Escolhe provedor
        try {
            switch ($this->provider) {
                case 'meta':
                    $resultado = $this->sendViaMeta($numero, $mensagem, $options);
                    break;
                case 'twilio':
                    $resultado = $this->sendViaTwilio($numero, $mensagem, $options);
                    break;
                case 'whapi':
                    $resultado = $this->sendViaWhapi($numero, $mensagem, $options);
                    break;
                default:
                    $resultado = $this->simularEnvio($numero, $mensagem, $options);
            }
            
            // Registra no log
            $this->log->registrar([
                'para' => $numero,
                'mensagem' => $mensagem,
                'status' => $resultado['success'] ? 'enviado' : 'falha',
                'provider' => $this->provider,
                'resposta' => json_encode($resultado)
            ]);
            
            return $resultado;
            
        } catch (Exception $e) {
            $this->log->registrar([
                'para' => $numero,
                'mensagem' => $mensagem,
                'status' => 'erro',
                'provider' => $this->provider,
                'erro' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro ao enviar mensagem: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Envia mensagem template
     * @param string $para Número do destinatário
     * @param string $template Nome do template
     * @param array $variaveis Variáveis para substituir
     * @return array Resultado do envio
     */
    public function sendTemplate($para, $template, $variaveis = []) {
        if (!isset($this->config['templates'][$template])) {
            return [
                'success' => false,
                'message' => 'Template não encontrado'
            ];
        }
        
        $template_data = $this->config['templates'][$template];
        $mensagem = $this->processarTemplate($template_data, $variaveis);
        
        $options = [
            'template' => true,
            'template_name' => $template_data['name'] ?? null
        ];
        
        return $this->sendMessage($para, $mensagem, $options);
    }
    
    /**
     * Envia confirmação de agendamento
     * @param int $agendamento_id ID do agendamento
     * @return array Resultado
     */
    public function sendConfirmacaoAgendamento($agendamento_id) {
        // Busca dados do agendamento
        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                c.primeiro_nome,
                c.telefone_original,
                GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            WHERE a.id = ?
            GROUP BY a.id
        ");
        $stmt->execute([$agendamento_id]);
        $ag = $stmt->fetch();
        
        if (!$ag || empty($ag['telefone_original'])) {
            return ['success' => false, 'message' => 'Agendamento não encontrado'];
        }
        
        // Prepara variáveis
        $data_formatada = date('d/m/Y', strtotime($ag['data_agendamento']));
        $hora_formatada = date('H:i', strtotime($ag['hora_agendamento']));
        $link_confirmar = BASE_URL . "/confirmar.php?token=" . $ag['token_confirmacao'];
        $link_cancelar = BASE_URL . "/cancelar.php?id=" . $ag['id'];
        
        // Envia template
        return $this->sendTemplate(
            $ag['telefone_original'],
            'confirmacao',
            [
                $ag['primeiro_nome'],
                $data_formatada,
                $hora_formatada,
                $ag['servicos'],
                $link_confirmar,
                $link_cancelar
            ]
        );
    }
    
    /**
     * Envia lembrete de agendamento
     * @param int $agendamento_id ID do agendamento
     * @return array Resultado
     */
    public function sendLembreteAgendamento($agendamento_id) {
        // Busca dados do agendamento
        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                c.primeiro_nome,
                c.telefone_original,
                GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
            FROM agendamentos a
            JOIN clientes c ON a.cliente_id = c.id
            LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
            LEFT JOIN servicos s ON ag.servico_id = s.id
            WHERE a.id = ?
            GROUP BY a.id
        ");
        $stmt->execute([$agendamento_id]);
        $ag = $stmt->fetch();
        
        if (!$ag || empty($ag['telefone_original'])) {
            return ['success' => false, 'message' => 'Agendamento não encontrado'];
        }
        
        // Prepara variáveis
        $data_formatada = date('d/m/Y', strtotime($ag['data_agendamento']));
        $hora_formatada = date('H:i', strtotime($ag['hora_agendamento']));
        
        // Envia template
        return $this->sendTemplate(
            $ag['telefone_original'],
            'lembrete',
            [
                $ag['primeiro_nome'],
                $data_formatada,
                $hora_formatada,
                $ag['servicos']
            ]
        );
    }
    
    /**
     * Envia código 2FA
     * @param string $telefone Número do cliente
     * @param string $codigo Código de verificação
     * @return array Resultado
     */
    public function sendCodigo2FA($telefone, $codigo) {
        return $this->sendTemplate(
            $telefone,
            'codigo_2fa',
            [$codigo]
        );
    }
    
    /**
     * Envia via Meta (WhatsApp Business API)
     */
    private function sendViaMeta($para, $mensagem, $options = []) {
        $meta_config = $this->config['meta'];
        
        $url = $meta_config['base_url'] . $meta_config['api_version'] . 
               '/' . $meta_config['phone_number_id'] . '/messages';
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $para,
            'type' => 'text',
            'text' => ['body' => $mensagem]
        ];
        
        // Se for template, ajusta estrutura
        if (isset($options['template']) && $options['template']) {
            $data['type'] = 'template';
            $data['template'] = [
                'name' => $options['template_name'],
                'language' => ['code' => 'pt_BR']
            ];
            unset($data['text']);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $meta_config['token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['options']['timeout']);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'message' => 'Mensagem enviada via Meta',
                'data' => json_decode($response, true)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erro na API Meta',
                'http_code' => $http_code,
                'response' => $response
            ];
        }
    }
    
    /**
     * Envia via Twilio
     */
    private function sendViaTwilio($para, $mensagem, $options = []) {
        $twilio = $this->config['twilio'];
        
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . 
               $twilio['account_sid'] . '/Messages.json';
        
        $data = [
            'To' => 'whatsapp:' . $para,
            'From' => $twilio['from_number'],
            'Body' => $mensagem
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $twilio['account_sid'] . ':' . $twilio['auth_token']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'message' => 'Mensagem enviada via Twilio',
                'data' => json_decode($response, true)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erro na API Twilio',
                'http_code' => $http_code
            ];
        }
    }
    
    /**
     * Envia via Whapi.Cloud
     */
    private function sendViaWhapi($para, $mensagem, $options = []) {
        $whapi = $this->config['whapi'];
        
        $url = $whapi['base_url'] . 'messages/text';
        
        $data = [
            'to' => $para,
            'body' => $mensagem,
            'typing_time' => 5,
            'no_link_preview' => false
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $whapi['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'message' => 'Mensagem enviada via Whapi.Cloud',
                'data' => json_decode($response, true)
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Erro na API Whapi.Cloud',
                'http_code' => $http_code
            ];
        }
    }
    
    /**
     * Simula envio (modo desenvolvimento)
     */
    private function simularEnvio($para, $mensagem, $options = []) {
        // Gera ID simulado
        $message_id = 'simulado_' . uniqid() . '_' . date('YmdHis');
        
        // Log da simulação
        error_log("[WHATSAPP SIMULAÇÃO] Para: $para | Mensagem: $mensagem");
        
        return [
            'success' => true,
            'simulado' => true,
            'message' => 'Mensagem simulada com sucesso',
            'message_id' => $message_id,
            'para' => $para,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Formata número para padrão internacional
     */
    private function formatarNumero($numero) {
        // Remove caracteres não numéricos
        $numero = preg_replace('/[^0-9]/', '', $numero);
        
        // Adiciona código do país se necessário
        if (strlen($numero) == 11) { // Brasil com DDD
            $numero = '55' . $numero;
        } elseif (strlen($numero) == 10) { // Brasil sem 9
            $numero = '55' . substr($numero, 0, 2) . '9' . substr($numero, 2);
        }
        
        return $numero;
    }
    
    /**
     * Valida número de WhatsApp
     */
    private function validarNumero($numero) {
        // Verifica se está na lista de testes
        if (in_array($numero, $this->config['numeros_teste'])) {
            return true;
        }
        
        // Validação básica
        return preg_match('/^55[1-9][0-9]{10,11}$/', $numero);
    }
    
    /**
     * Verifica rate limit
     */
    private function checkRateLimit($numero) {
        if ($this->config['options']['simular_envio']) {
            return true;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(CASE WHEN enviado_em > DATE_SUB(NOW(), INTERVAL 1 MINUTE) THEN 1 END) as por_minuto,
                COUNT(CASE WHEN enviado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as por_hora,
                COUNT(CASE WHEN DATE(enviado_em) = CURDATE() THEN 1 END) as por_dia
            FROM whatsapp_logs
            WHERE para = ?
        ");
        $stmt->execute([$numero]);
        $counts = $stmt->fetch();
        
        if ($counts['por_minuto'] >= $this->config['rate_limit']['max_por_minuto']) {
            return false;
        }
        
        if ($counts['por_hora'] >= $this->config['rate_limit']['max_por_hora']) {
            return false;
        }
        
        if ($counts['por_dia'] >= $this->config['rate_limit']['max_por_dia']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Processa template substituindo variáveis
     */
    private function processarTemplate($template, $variaveis) {
        $mensagem = $template['body'] ?? '';
        
        foreach ($variaveis as $index => $valor) {
            $mensagem = str_replace('{{' . ($index + 1) . '}}', $valor, $mensagem);
        }
        
        return $mensagem;
    }
}