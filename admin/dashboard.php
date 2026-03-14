<?php
/**
 * Painel administrativo do barbeiro
 * Acesso restrito para administradores
 */

session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/WhatsApp.php';

$auth = new Auth();
$user = $auth->checkAuth();

// Verifica se está autenticado
if (!$user) {
    header('Location: ../login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Processa ações do POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancelar_agendamento':
                cancelarAgendamento($_POST['agendamento_id']);
                break;
            case 'bloquear_horario':
                bloquearHorario($_POST['data'], $_POST['hora'], $_POST['motivo']);
                break;
            case 'novo_servico':
                criarServico($_POST['nome'], $_POST['descricao'], $_POST['preco'], $_POST['tempo']);
                break;
            case 'confirmar_agendamento':
                confirmarAgendamento($_POST['agendamento_id']);
                break;
            case 'desbloquear_horario':
                desbloquearHorario($_POST['bloqueio_id']);
                break;
            case 'enviar_whatsapp':
                enviarWhatsApp($_POST['agendamento_id'], $_POST['tipo_mensagem']);
                break;
        }
    }
}

// Busca estatísticas
$stats = getEstatisticas();
$agendamentosHoje = getAgendamentosHoje();
$proximosAgendamentos = getProximosAgendamentos();
$servicos = getServicos();
$horariosBloqueados = getHorariosBloqueados();

/**
 * Funções auxiliares
 */
function getEstatisticas() {
    global $db;
    
    $stats = [];
    
    // Total agendamentos hoje
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM agendamentos 
        WHERE data_agendamento = CURDATE() 
        AND status != 'cancelado'
    ");
    $stmt->execute();
    $stats['agendamentos_hoje'] = $stmt->fetch()['total'];
    
    // Agendamentos confirmados
    $stmt = $db->prepare("
        SELECT COUNT(*) as total FROM agendamentos 
        WHERE data_agendamento >= CURDATE() 
        AND status = 'confirmado'
    ");
    $stmt->execute();
    $stats['confirmados'] = $stmt->fetch()['total'];
    
    // Total clientes
    $stmt = $db->query("SELECT COUNT(*) as total FROM clientes");
    $stats['total_clientes'] = $stmt->fetch()['total'];
    
    // Faturamento do mês
    $stmt = $db->prepare("
        SELECT SUM(s.preco) as total
        FROM agendamentos a
        JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
        JOIN servicos s ON ag.servico_id = s.id
        WHERE MONTH(a.data_agendamento) = MONTH(CURDATE())
        AND YEAR(a.data_agendamento) = YEAR(CURDATE())
        AND a.status IN ('confirmado', 'concluido')
    ");
    $stmt->execute();
    $stats['faturamento_mes'] = $stmt->fetch()['total'] ?? 0;
    
    // Estatísticas WhatsApp
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_whatsapp,
                SUM(CASE WHEN status = 'enviado' THEN 1 ELSE 0 END) as whatsapp_enviados,
                SUM(CASE WHEN status = 'falha' THEN 1 ELSE 0 END) as whatsapp_falhas
            FROM whatsapp_logs 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $whatsapp_stats = $stmt->fetch();
        $stats['whatsapp_hoje'] = $whatsapp_stats['total_whatsapp'] ?? 0;
        $stats['whatsapp_sucesso'] = $whatsapp_stats['whatsapp_enviados'] ?? 0;
    } catch (Exception $e) {
        $stats['whatsapp_hoje'] = 0;
        $stats['whatsapp_sucesso'] = 0;
    }
    
    return $stats;
}

function getAgendamentosHoje() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT 
            a.*,
            c.primeiro_nome,
            c.telefone_original,
            GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
        FROM agendamentos a
        JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
        LEFT JOIN servicos s ON ag.servico_id = s.id
        WHERE a.data_agendamento = CURDATE()
        AND a.status != 'cancelado'
        GROUP BY a.id
        ORDER BY a.hora_agendamento ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getProximosAgendamentos() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT 
            a.*,
            c.primeiro_nome,
            c.telefone_original,
            GROUP_CONCAT(s.nome SEPARATOR ', ') as servicos
        FROM agendamentos a
        JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN agendamento_servicos ag ON a.id = ag.agendamento_id
        LEFT JOIN servicos s ON ag.servico_id = s.id
        WHERE a.data_agendamento >= CURDATE()
        AND a.status IN ('agendado', 'confirmado')
        GROUP BY a.id
        ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC
        LIMIT 10
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getServicos() {
    global $db;
    $stmt = $db->query("SELECT * FROM servicos WHERE ativo = TRUE ORDER BY nome ASC");
    return $stmt->fetchAll();
}

function getHorariosBloqueados() {
    global $db;
    $stmt = $db->query("
        SELECT * FROM horarios_bloqueados 
        WHERE data_bloqueio >= CURDATE()
        ORDER BY data_bloqueio ASC, hora_bloqueio ASC
    ");
    return $stmt->fetchAll();
}

function cancelarAgendamento($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE agendamentos 
            SET status = 'cancelado' 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = "Agendamento cancelado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao cancelar agendamento";
    }
    
    header('Location: dashboard.php');
    exit();
}

function confirmarAgendamento($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE agendamentos 
            SET status = 'confirmado' 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = "Agendamento confirmado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao confirmar agendamento";
    }
    
    header('Location: dashboard.php');
    exit();
}

function bloquearHorario($data, $hora, $motivo) {
    global $db;
    
    try {
        // Verifica se já existe agendamento para este horário
        $stmt = $db->prepare("
            SELECT id FROM agendamentos 
            WHERE data_agendamento = ? AND hora_agendamento = ? 
            AND status IN ('agendado', 'confirmado')
        ");
        $stmt->execute([$data, $hora]);
        
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = "Não é possível bloquear - já existe agendamento neste horário";
            header('Location: dashboard.php');
            exit();
        }
        
        // Verifica se já está bloqueado
        $stmt = $db->prepare("
            SELECT id FROM horarios_bloqueados 
            WHERE data_bloqueio = ? AND hora_bloqueio = ?
        ");
        $stmt->execute([$data, $hora]);
        
        if ($stmt->fetch()) {
            $_SESSION['error_message'] = "Horário já está bloqueado";
            header('Location: dashboard.php');
            exit();
        }
        
        // Insere bloqueio
        $stmt = $db->prepare("
            INSERT INTO horarios_bloqueados (data_bloqueio, hora_bloqueio, motivo) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$data, $hora, $motivo ?: 'Bloqueado pelo administrador']);
        
        $_SESSION['success_message'] = "Horário bloqueado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao bloquear horário";
    }
    
    header('Location: dashboard.php');
    exit();
}

function desbloquearHorario($id) {
    global $db;
    
    try {
        $stmt = $db->prepare("DELETE FROM horarios_bloqueados WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['success_message'] = "Horário desbloqueado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao desbloquear horário";
    }
    
    header('Location: dashboard.php');
    exit();
}

function criarServico($nome, $descricao, $preco, $tempo) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO servicos (nome, descricao, preco, tempo_estimado) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nome, $descricao, $preco, $tempo]);
        
        $_SESSION['success_message'] = "Serviço criado com sucesso!";
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao criar serviço";
    }
    
    header('Location: dashboard.php');
    exit();
}

function enviarWhatsApp($agendamento_id, $tipo) {
    global $db;
    
    try {
        // Inclui classe WhatsApp se existir
        if (file_exists('../includes/WhatsApp.php')) {
            require_once '../includes/WhatsApp.php';
            $whatsapp = new WhatsApp();
            
            switch ($tipo) {
                case 'confirmacao':
                    $resultado = $whatsapp->sendConfirmacaoAgendamento($agendamento_id);
                    break;
                case 'lembrete':
                    $resultado = $whatsapp->sendLembreteAgendamento($agendamento_id);
                    break;
                default:
                    $resultado = ['success' => false, 'message' => 'Tipo inválido'];
            }
            
            if ($resultado['success']) {
                $_SESSION['success_message'] = "WhatsApp enviado com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao enviar WhatsApp: " . $resultado['message'];
            }
        } else {
            $_SESSION['error_message'] = "Módulo WhatsApp não disponível";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Erro ao enviar WhatsApp: " . $e->getMessage();
    }
    
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Barbershop Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-page">
    <!-- Header administrativo -->
    <header class="admin-header">
        <div class="container">
            <div class="admin-header-content">
                <h1>✂️ PAINEL DO BARBEIRO</h1>
                <div class="admin-user">
                    <span>👤 <?= htmlspecialchars($user['nome']) ?></span>
                    <a href="../api/logout.php" class="btn-logout">Sair</a>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <!-- Mensagens de feedback -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success_message'] ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <?= $_SESSION['error_message'] ?>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Cards de estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['agendamentos_hoje'] ?></span>
                    <span class="stat-label">Agendamentos Hoje</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['confirmados'] ?></span>
                    <span class="stat-label">Confirmados</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <span class="stat-value"><?= $stats['total_clientes'] ?></span>
                    <span class="stat-label">Clientes</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <span class="stat-value">R$ <?= number_format($stats['faturamento_mes'], 2, ',', '.') ?></span>
                    <span class="stat-label">Faturamento Mês</span>
                </div>
            </div>
            
            <!-- Card WhatsApp Status -->
            <div class="stat-card whatsapp-card">
                <div class="stat-icon">📱</div>
                <div class="stat-info">
                    <span class="stat-value" id="whatsappStatus">Verificando...</span>
                    <span class="stat-label">WhatsApp</span>
                    <small class="whatsapp-stats" id="whatsappStats">
                        Hoje: <?= $stats['whatsapp_hoje'] ?> enviados
                    </small>
                </div>
                <div class="whatsapp-indicator" id="whatsappIndicator"></div>
            </div>
        </div>

        <!-- Agendamentos de hoje -->
        <section class="dashboard-section">
            <h2>Agendamentos de Hoje</h2>
            
            <div class="table-responsive">
                <table class="agendamentos-table">
                    <thead>
                        <tr>
                            <th>Horário</th>
                            <th>Cliente</th>
                            <th>Telefone</th>
                            <th>Serviços</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($agendamentosHoje)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum agendamento para hoje</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($agendamentosHoje as $ag): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($ag['hora_agendamento'])) ?></td>
                                <td><?= htmlspecialchars($ag['primeiro_nome']) ?></td>
                                <td><?= htmlspecialchars($ag['telefone_original']) ?></td>
                                <td><?= htmlspecialchars($ag['servicos']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $ag['status'] ?>">
                                        <?= $ag['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="confirmar_agendamento">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <button type="submit" class="btn-action btn-confirm" <?= $ag['status'] != 'agendado' ? 'disabled' : '' ?> title="Confirmar">✓</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="cancelar_agendamento">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <button type="submit" class="btn-action btn-cancel" title="Cancelar">✗</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" class="whatsapp-actions">
                                        <input type="hidden" name="action" value="enviar_whatsapp">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <input type="hidden" name="tipo_mensagem" value="confirmacao">
                                        <button type="submit" class="btn-action btn-whatsapp" title="Enviar confirmação WhatsApp">📱</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" class="whatsapp-actions">
                                        <input type="hidden" name="action" value="enviar_whatsapp">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <input type="hidden" name="tipo_mensagem" value="lembrete">
                                        <button type="submit" class="btn-action btn-whatsapp" title="Enviar lembrete WhatsApp">⏰</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Próximos agendamentos -->
        <section class="dashboard-section">
            <h2>Próximos Agendamentos</h2>
            
            <div class="table-responsive">
                <table class="agendamentos-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Horário</th>
                            <th>Cliente</th>
                            <th>Telefone</th>
                            <th>Serviços</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proximosAgendamentos)): ?>
                            <tr>
                                <td colspan="7" class="text-center">Nenhum agendamento futuro</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($proximosAgendamentos as $ag): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($ag['data_agendamento'])) ?></td>
                                <td><?= date('H:i', strtotime($ag['hora_agendamento'])) ?></td>
                                <td><?= htmlspecialchars($ag['primeiro_nome']) ?></td>
                                <td><?= htmlspecialchars($ag['telefone_original']) ?></td>
                                <td><?= htmlspecialchars($ag['servicos']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $ag['status'] ?>">
                                        <?= $ag['status'] ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="cancelar_agendamento">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <button type="submit" class="btn-action btn-cancel" title="Cancelar">✗</button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="enviar_whatsapp">
                                        <input type="hidden" name="agendamento_id" value="<?= $ag['id'] ?>">
                                        <input type="hidden" name="tipo_mensagem" value="lembrete">
                                        <button type="submit" class="btn-action btn-whatsapp" title="Enviar lembrete WhatsApp">⏰</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Grid de duas colunas -->
        <div class="dashboard-grid">
            <!-- Gerenciar horários bloqueados -->
            <section class="dashboard-section">
                <h2>Horários Bloqueados</h2>
                
                <div class="blocked-slots">
                    <?php if (empty($horariosBloqueados)): ?>
                        <p class="text-center">Nenhum horário bloqueado</p>
                    <?php else: ?>
                        <?php foreach ($horariosBloqueados as $hb): ?>
                        <div class="blocked-slot">
                            <div>
                                <strong><?= date('d/m/Y', strtotime($hb['data_bloqueio'])) ?> às <?= date('H:i', strtotime($hb['hora_bloqueio'])) ?></strong>
                                <p class="motivo"><?= htmlspecialchars($hb['motivo']) ?></p>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="desbloquear_horario">
                                <input type="hidden" name="bloqueio_id" value="<?= $hb['id'] ?>">
                                <button type="submit" class="btn-unblock" onclick="return confirm('Desbloquear este horário?')" title="Desbloquear">✗</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button class="btn-primary" onclick="showBloquearModal()">
                    + Bloquear Horário
                </button>
            </section>

            <!-- Gerenciar serviços -->
            <section class="dashboard-section">
                <h2>Serviços</h2>
                
                <div class="services-list">
                    <?php if (empty($servicos)): ?>
                        <p class="text-center">Nenhum serviço cadastrado</p>
                    <?php else: ?>
                        <?php foreach ($servicos as $s): ?>
                        <div class="service-item">
                            <div class="service-info">
                                <strong><?= htmlspecialchars($s['nome']) ?></strong>
                                <span class="service-price">R$ <?= number_format($s['preco'], 2, ',', '.') ?></span>
                                <span class="service-time"><?= $s['tempo_estimado'] ?> min</span>
                                <p class="service-desc"><?= htmlspecialchars($s['descricao']) ?></p>
                            </div>
                            <button onclick="editarServico(<?= $s['id'] ?>)" class="btn-edit" title="Editar">✎</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button class="btn-primary" onclick="showServicoModal()">
                    + Novo Serviço
                </button>
            </section>
        </div>
        
        <!-- Últimas mensagens WhatsApp -->
        <section class="dashboard-section">
            <h2>📱 Últimas Mensagens WhatsApp</h2>
            <div class="whatsapp-log-preview" id="whatsappLog">
                <p class="text-center">Carregando...</p>
            </div>
            <button class="btn-secondary" onclick="carregarLogsWhatsApp()">
                Atualizar Logs
            </button>
        </section>
    </main>

    <!-- Modal para bloquear horário -->
    <div id="bloquearModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="hideBloquearModal()">&times;</span>
            <h3>Bloquear Horário</h3>
            
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="bloquear_horario">
                
                <div class="form-group">
                    <label>Data</label>
                    <input type="date" name="data" required min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label>Hora</label>
                    <select name="hora" required>
                        <?php
                        $horarios = [
                            '08:20', '09:00', '09:40', '10:30', '11:20', '12:00',
                            '13:00', '13:40', '14:20', '15:00', '15:40', '16:30',
                            '17:40', '18:20', '19:20', '20:00', '21:00', '21:40',
                            '22:30', '23:00'
                        ];
                        foreach ($horarios as $h):
                        ?>
                        <option value="<?= $h ?>"><?= $h ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Motivo (opcional)</label>
                    <input type="text" name="motivo" placeholder="Ex: Folga, manutenção...">
                </div>
                
                <button type="submit" class="btn-primary">Bloquear</button>
            </form>
        </div>
    </div>

    <!-- Modal para novo serviço -->
    <div id="servicoModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="hideServicoModal()">&times;</span>
            <h3>Novo Serviço</h3>
            
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="novo_servico">
                
                <div class="form-group">
                    <label>Nome do Serviço</label>
                    <input type="text" name="nome" required>
                </div>
                
                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Preço (R$)</label>
                        <input type="number" name="preco" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Tempo (min)</label>
                        <input type="number" name="tempo" min="15" step="15" value="30" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Criar Serviço</button>
            </form>
        </div>
    </div>

    <script>
        // Funções para modais
        function showBloquearModal() {
            document.getElementById('bloquearModal').style.display = 'flex';
        }
        
        function hideBloquearModal() {
            document.getElementById('bloquearModal').style.display = 'none';
        }
        
        function showServicoModal() {
            document.getElementById('servicoModal').style.display = 'flex';
        }
        
        function hideServicoModal() {
            document.getElementById('servicoModal').style.display = 'none';
        }
        
        function editarServico(id) {
            alert('Funcionalidade de edição em desenvolvimento');
        }
        
        // Verifica status do WhatsApp
        function verificarStatusWhatsApp() {
            fetch('../api/whatsapp.php?status')
                .then(response => response.json())
                .then(data => {
                    const statusEl = document.getElementById('whatsappStatus');
                    const indicatorEl = document.getElementById('whatsappIndicator');
                    
                    if (data.success) {
                        if (data.simulando) {
                            statusEl.textContent = 'Modo Teste';
                            indicatorEl.className = 'whatsapp-indicator warning';
                        } else {
                            statusEl.textContent = 'Conectado';
                            indicatorEl.className = 'whatsapp-indicator success';
                        }
                    } else {
                        statusEl.textContent = 'Desconectado';
                        indicatorEl.className = 'whatsapp-indicator error';
                    }
                })
                .catch(error => {
                    document.getElementById('whatsappStatus').textContent = 'Erro';
                    document.getElementById('whatsappIndicator').className = 'whatsapp-indicator error';
                });
        }
        
        // Carrega logs do WhatsApp
        function carregarLogsWhatsApp() {
            const logEl = document.getElementById('whatsappLog');
            logEl.innerHTML = '<p class="text-center">Carregando...</p>';
            
            fetch('../api/whatsapp.php?logs&limite=10')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.logs.length > 0) {
                        let html = '<table class="mini-table"><thead><tr><th>Data</th><th>Número</th><th>Status</th></tr></thead><tbody>';
                        
                        data.logs.forEach(log => {
                            const dataFormatada = new Date(log.created_at).toLocaleString('pt-BR');
                            const statusClass = log.status === 'enviado' ? 'success' : (log.status === 'falha' ? 'error' : 'warning');
                            
                            html += `<tr>
                                <td>${dataFormatada}</td>
                                <td>${log.para}</td>
                                <td><span class="status-badge status-${statusClass}">${log.status}</span></td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table>';
                        logEl.innerHTML = html;
                    } else {
                        logEl.innerHTML = '<p class="text-center">Nenhuma mensagem enviada hoje</p>';
                    }
                })
                .catch(error => {
                    logEl.innerHTML = '<p class="text-center error">Erro ao carregar logs</p>';
                });
        }
        
        // Carrega logs ao iniciar
        document.addEventListener('DOMContentLoaded', function() {
            verificarStatusWhatsApp();
            carregarLogsWhatsApp();
            
            // Atualiza a cada 30 segundos
            setInterval(() => {
                verificarStatusWhatsApp();
                carregarLogsWhatsApp();
            }, 30000);
        });
        
        // Fecha modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
    
    <style>
        /* Estilos adicionais para WhatsApp */
        .whatsapp-card {
            position: relative;
            background: linear-gradient(135deg, #25D366, #128C7E);
            color: white;
        }
        
        .whatsapp-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .whatsapp-indicator.success {
            background-color: #4CAF50;
            box-shadow: 0 0 10px #4CAF50;
        }
        
        .whatsapp-indicator.warning {
            background-color: #FFC107;
            box-shadow: 0 0 10px #FFC107;
        }
        
        .whatsapp-indicator.error {
            background-color: #F44336;
            box-shadow: 0 0 10px #F44336;
        }
        
        .whatsapp-stats {
            display: block;
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
            border: none;
        }
        
        .btn-whatsapp:hover {
            background-color: #128C7E;
        }
        
        .whatsapp-actions {
            display: inline-block;
            margin: 0 2px;
        }
        
        .mini-table {
            width: 100%;
            font-size: 14px;
        }
        
        .mini-table th {
            background: var(--primary-gold);
            color: var(--primary-dark);
            padding: 8px;
        }
        
        .mini-table td {
            padding: 8px;
            border-bottom: 1px solid rgba(201, 162, 39, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid var(--primary-gold);
            color: var(--primary-gold);
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: var(--primary-gold);
            color: var(--primary-dark);
        }
    </style>
</body>
</html>