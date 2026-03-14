 <?php
/**
 * Página principal de agendamento
 * Exibe calendário e horários disponíveis
 */

session_start();
require_once 'config/env.php'; 
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verifica autenticação
$auth = new Auth();
$user = $auth->checkAuth();

if (!$user) {
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Busca serviços para o select
$stmt = $db->query("SELECT * FROM servicos WHERE ativo = TRUE");
$servicos = $stmt->fetchAll();

// Data selecionada (hoje por padrão)
$data_selecionada = $_GET['data'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendar Horário - Barbershop Premium</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header com navegação -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1>📅 AGENDAR HORÁRIO</h1>
                <nav class="header-nav">
                    <a href="index.php">🏠 Início</a>
                    <a href="agendar.php" class="active">📅 Agendar</a>
                    <a href="meus-agendamentos.php">📋 Meus Agendamentos</a>
                    <a href="api/logout.php">🚪 Sair</a>
                </nav>
            </div>
            <div class="user-welcome">
                <span>👤 Olá, <?= htmlspecialchars($user['nome']) ?></span>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="agendamento-container">
            <!-- Barra de progresso -->
            <div class="progress-bar">
                <div class="progress-step active">
                    <span class="step-number">1</span>
                    <span class="step-label">Serviços</span>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <span class="step-number">2</span>
                    <span class="step-label">Data/Hora</span>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <span class="step-number">3</span>
                    <span class="step-label">Confirmar</span>
                </div>
            </div>

            <!-- Passo 1: Seleção de serviços -->
            <div class="step-content" id="step1">
                <h2>Selecione os serviços desejados</h2>
                
                <div class="services-selection">
                    <?php foreach ($servicos as $servico): ?>
                    <label class="service-checkbox">
                        <input type="checkbox" 
                               name="servicos[]" 
                               value="<?= $servico['id'] ?>"
                               data-preco="<?= $servico['preco'] ?>"
                               data-tempo="<?= $servico['tempo_estimado'] ?>"
                               onchange="updateTotal()">
                        <span class="checkbox-custom"></span>
                        <div class="service-info">
                            <span class="service-name"><?= htmlspecialchars($servico['nome']) ?></span>
                            <span class="service-price">R$ <?= number_format($servico['preco'], 2, ',', '.') ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>

                <!-- Combos rápidos -->
                <div class="quick-combos">
                    <h3>Combos rápidos</h3>
                    <button onclick="selectCombo('corte-barba')" class="combo-btn">
                        Corte + Barba (R$ 70,00)
                    </button>
                    <button onclick="selectCombo('completo')" class="combo-btn">
                        Completo (R$ 200,00)
                    </button>
                </div>

                <div class="total-preview">
                    <span>Total:</span>
                    <span id="totalValue">R$ 0,00</span>
                    <span>Tempo estimado:</span>
                    <span id="totalTime">0 min</span>
                </div>

                <button class="btn-next" onclick="nextStep(2)" id="nextBtn" disabled>
                    PRÓXIMO →
                </button>
            </div>

            <!-- Passo 2: Seleção de data e hora -->
            <div class="step-content" id="step2" style="display: none;">
                <h2>Escolha data e horário</h2>

                <!-- Calendário -->
                <div class="calendar-container">
                    <div class="calendar-header">
                        <button onclick="changeMonth(-1)">←</button>
                        <h3 id="monthYear"></h3>
                        <button onclick="changeMonth(1)">→</button>
                    </div>
                    
                    <div class="calendar-weekdays">
                        <div>DOM</div>
                        <div>SEG</div>
                        <div>TER</div>
                        <div>QUA</div>
                        <div>QUI</div>
                        <div>SEX</div>
                        <div>SÁB</div>
                    </div>
                    
                    <div class="calendar-days" id="calendarDays"></div>
                </div>

                <!-- Horários disponíveis -->
                <div class="time-slots-container" id="timeSlots">
                    <h3>Horários disponíveis para <span id="dataSelecionada"></span></h3>
                    <div class="time-slots" id="timeSlotsGrid">
                        <!-- Será preenchido via AJAX -->
                        <p class="loading">Selecione uma data...</p>
                    </div>
                </div>

                <div class="step-buttons">
                    <button class="btn-prev" onclick="prevStep(1)">← VOLTAR</button>
                    <button class="btn-next" onclick="nextStep(3)" id="nextStep2Btn" disabled>
                        PRÓXIMO →
                    </button>
                </div>
            </div>

            <!-- Passo 3: Confirmação -->
            <div class="step-content" id="step3" style="display: none;">
                <h2>Confirme seu agendamento</h2>

                <div class="confirmation-card">
                    <h3>Resumo do agendamento</h3>
                    
                    <div class="confirmation-details">
                        <p><strong>Serviços:</strong> <span id="confirmServicos"></span></p>
                        <p><strong>Data:</strong> <span id="confirmData"></span></p>
                        <p><strong>Horário:</strong> <span id="confirmHora"></span></p>
                        <p><strong>Total:</strong> <span id="confirmTotal"></span></p>
                    </div>

                    <div class="confirmation-actions">
                        <button class="btn-prev" onclick="prevStep(2)">← VOLTAR</button>
                        <button class="btn-confirm" onclick="confirmAgendamento()">
                            CONFIRMAR AGENDAMENTO
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Variáveis globais
        let selectedDate = '<?= $data_selecionada ?>';
        let selectedTime = '';
        let selectedServices = [];
        let currentMonth = new Date().getMonth();
        let currentYear = new Date().getFullYear();

        // Função para atualizar total
        function updateTotal() {
            const checkboxes = document.querySelectorAll('input[name="servicos[]"]:checked');
            let total = 0;
            let tempo = 0;
            selectedServices = [];

            checkboxes.forEach(cb => {
                total += parseFloat(cb.dataset.preco);
                tempo += parseInt(cb.dataset.tempo);
                selectedServices.push(cb.value);
            });

            document.getElementById('totalValue').textContent = 
                'R$ ' + total.toFixed(2).replace('.', ',');
            
            document.getElementById('totalTime').textContent = 
                tempo + ' min';

            // Habilita/desabilita botão próximo
            document.getElementById('nextBtn').disabled = selectedServices.length === 0;
        }

        // Função para selecionar combo
        function selectCombo(tipo) {
            const checkboxes = document.querySelectorAll('input[name="servicos[]"]');
            checkboxes.forEach(cb => cb.checked = false);

            if (tipo === 'corte-barba') {
                checkboxes[0].checked = true; // Corte
                checkboxes[1].checked = true; // Barba
            } else if (tipo === 'completo') {
                checkboxes.forEach(cb => cb.checked = true);
            }

            updateTotal();
        }

        // Navegação entre steps
        function nextStep(step) {
            if (step === 2 && selectedServices.length === 0) {
                alert('Selecione pelo menos um serviço');
                return;
            }

            if (step === 3 && !selectedDate && !selectedTime) {
                alert('Selecione data e horário');
                return;
            }

            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'none';
            
            document.getElementById('step' + step).style.display = 'block';

            if (step === 2) {
                renderCalendar();
            } else if (step === 3) {
                loadConfirmation();
            }
        }

        function prevStep(step) {
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step3').style.display = 'none';
            
            document.getElementById('step' + step).style.display = 'block';
        }

        // Funções do calendário
        function renderCalendar() {
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                               'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
            
            document.getElementById('monthYear').textContent = 
                monthNames[currentMonth] + ' ' + currentYear;

            let html = '';
            
            // Dias vazios no início
            for (let i = 0; i < firstDay; i++) {
                html += '<div class="calendar-day empty"></div>';
            }

            // Dias do mês
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = currentYear + '-' + 
                               String(currentMonth + 1).padStart(2, '0') + '-' + 
                               String(day).padStart(2, '0');
                
                const isToday = dateStr === new Date().toISOString().split('T')[0];
                const isSelected = dateStr === selectedDate;
                
                html += `<div class="calendar-day ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''}" 
                              onclick="selectDate('${dateStr}')">
                            ${day}
                        </div>`;
            }

            document.getElementById('calendarDays').innerHTML = html;
        }

        function changeMonth(delta) {
            currentMonth += delta;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar();
        }

        function selectDate(date) {
            selectedDate = date;
            
            // Atualiza visual
            document.querySelectorAll('.calendar-day').forEach(day => {
                day.classList.remove('selected');
            });
            
            event.target.classList.add('selected');
            
            // Formata data para exibição
            const [year, month, day] = date.split('-');
            document.getElementById('dataSelecionada').textContent = 
                `${day}/${month}/${year}`;
            
            // Carrega horários
            loadTimeSlots(date);
        }

        function loadTimeSlots(date) {
            fetch(`api/horarios.php?data=${date}`)
                .then(response => response.json())
                .then(data => {
                    const grid = document.getElementById('timeSlotsGrid');
                    
                    if (data.horarios && data.horarios.length > 0) {
                        let html = '';
                        data.horarios.forEach(hora => {
                            html += `<div class="time-slot" onclick="selectTime('${hora}')">
                                        ${hora}
                                    </div>`;
                        });
                        grid.innerHTML = html;
                    } else {
                        grid.innerHTML = '<p class="no-slots">Nenhum horário disponível nesta data</p>';
                    }
                });
        }

        function selectTime(hora) {
            selectedTime = hora;
            
            // Remove seleção anterior
            document.querySelectorAll('.time-slot').forEach(slot => {
                slot.classList.remove('selected');
            });
            
            event.target.classList.add('selected');
            
            // Habilita botão próximo
            document.getElementById('nextStep2Btn').disabled = false;
        }

        function loadConfirmation() {
            // Busca nomes dos serviços
            const servicosNomes = [];
            document.querySelectorAll('input[name="servicos[]"]:checked').forEach(cb => {
                servicosNomes.push(cb.closest('.service-checkbox').querySelector('.service-name').textContent);
            });
            
            document.getElementById('confirmServicos').textContent = servicosNomes.join(', ');
            
            const [year, month, day] = selectedDate.split('-');
            document.getElementById('confirmData').textContent = `${day}/${month}/${year}`;
            
            document.getElementById('confirmHora').textContent = selectedTime;
            
            document.getElementById('confirmTotal').textContent = 
                document.getElementById('totalValue').textContent;
        }

        function confirmAgendamento() {
            const data = {
                servicos: selectedServices,
                data: selectedDate,
                hora: selectedTime
            };

            fetch('api/agendamentos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'confirmacao.php?id=' + data.agendamento_id;
                } else {
                    alert('Erro ao agendar: ' + data.message);
                }
            });
        }

        // Inicialização
        renderCalendar();
    </script>
</body>
</html>
