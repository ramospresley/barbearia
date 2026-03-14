 <?php
/**
 * Funções utilitárias gerais
 */

/**
 * Gera token único para confirmação
 * @return string Token único
 */
function generateConfirmationToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Formata telefone para exibição
 * @param string $telefone Telefone cru
 * @return string Telefone formatado
 */
function formatPhone($telefone) {
    $telefone = preg_replace('/[^0-9]/', '', $telefone);
    
    if (strlen($telefone) === 11) {
        return '(' . substr($telefone, 0, 2) . ') ' . 
               substr($telefone, 2, 5) . '-' . 
               substr($telefone, 7);
    }
    
    return $telefone;
}

/**
 * Verifica se um horário está disponível
 * @param string $data Data no formato Y-m-d
 * @param string $hora Hora no formato H:i
 * @return bool
 */
function isHorarioDisponivel($data, $hora) {
    $db = Database::getInstance()->getConnection();
    
    // Verifica horários fixos permitidos
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
 * Sanitiza input do usuário
 * @param string $input Input cru
 * @return string Input sanitizado
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Retorna lista de horários disponíveis para uma data
 * @param string $data Data no formato Y-m-d
 * @return array Horários disponíveis
 */
function getHorariosDisponiveis($data) {
    $horarios = [
        '08:20', '09:00', '09:40', '10:30', '11:20', '12:00',
        '13:00', '13:40', '14:20', '15:00', '15:40', '16:30',
        '17:40', '18:20', '19:20', '20:00', '21:00', '21:40',
        '22:30', '23:00'
    ];
    
    $disponiveis = [];
    
    foreach ($horarios as $hora) {
        if (isHorarioDisponivel($data, $hora)) {
            $disponiveis[] = $hora;
        }
    }
    
    return $disponiveis;
}
?>
