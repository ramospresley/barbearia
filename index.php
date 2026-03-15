 <?php
/**
 * Página inicial do sistema Barbershop
 * Apresenta os serviços e chama para ação de agendamento
 */

session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Busca serviços ativos do banco de dados
$db = Database::getInstance()->getConnection();
$stmt = $db->query("SELECT * FROM servicos WHERE ativo = TRUE ORDER BY id");
$servicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barbearia Premium - Agendamento Online</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Cabeçalho com estilo barbearia americana -->
    <header class="header">
        <div class="container">
            <h1>✂️ BARBEARIA PREMIUM</h1>
            <p>Barbearia Tradicional desde 2010</p>
            <div class="header-decoration">
                <span class="decoration-line"></span>
                <span class="decoration-icon">⚜️</span>
                <span class="decoration-line"></span>
            </div>
        </div>
    </header>

    <!-- Seção de serviços -->
    <main class="container">
        <section class="services-section">
            <h2 class="section-title">NOSSOS SERVIÇOS</h2>
            <p class="section-subtitle">Escolha o serviço que deseja e agende seu horário</p>
            
            <div class="services-grid">
                <?php foreach ($servicos as $servico): ?>
                <div class="service-card" onclick="selectService(<?= $servico['id'] ?>)">
                    <div class="service-icon">
                        <?php 
                        // Ícones diferentes para cada serviço
                        $icones = ['💈', '🧔', '⚗️', '🎯'];
                        echo $icones[$servico['id'] - 1] ?? '✂️';
                        ?>
                    </div>
                    <h3><?= htmlspecialchars($servico['nome']) ?></h3>
                    <p class="service-description"><?= htmlspecialchars($servico['descricao']) ?></p>
                    <div class="service-price">
                        R$ <?= number_format($servico['preco'], 2, ',', '.') ?>
                    </div>
                    <div class="service-duration">
                        <span class="duration-icon">⏱️</span> <?= $servico['tempo_estimado'] ?> min
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Combos personalizados -->
            <div class="combos-section">
                <h3 class="combos-title">🎁 COMBOS ESPECIAIS</h3>
                <div class="combos-grid">
                    <div class="combo-card" onclick="selectCombo('Corte + Barba')">
                        <h4>CORTE + BARBA</h4>
                        <p>R$ 70,00</p>
                        <small>Economia de R$ 10,00</small>
                    </div>
                    <div class="combo-card" onclick="selectCombo('Corte + Químico')">
                        <h4>CORTE + PROCEDIMENTO QUÍMICO</h4>
                        <p>R$ 150,00</p>
                        <small>Economia de R$ 15,00</small>
                    </div>
                    <div class="combo-card" onclick="selectCombo('Completo')">
                        <h4>COMPLETO</h4>
                        <p>R$ 200,00</p>
                        <small>Todos os serviços</small>
                    </div>
                </div>
            </div>

            <!-- Botão de ação -->
            <div class="cta-section">
                <a href="agendar.php" class="btn-primary">
                    AGENDAR HORÁRIO
                    <span class="btn-arrow">→</span>
                </a>
            </div>
        </section>

        <!-- Depoimentos -->
        <section class="testimonials">
            <h2 class="section-title">O QUE NOSSOS CLIENTES DIZEM</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-stars">★★★★★</div>
                    <p>"Melhor barbearia da cidade! Ambiente incrível e profissionais top."</p>
                    <div class="testimonial-author">- João Silva</div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">★★★★★</div>
                    <p>"Atendimento premium, saí sentindo outro homem. Recomendo!"</p>
                    <div class="testimonial-author">- Pedro Santos</div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-stars">★★★★★</div>
                    <p>"Facilidade no agendamento online e pontualidade impecável."</p>
                    <div class="testimonial-author">- Carlos Oliveira</div>
                </div>
            </div>
        </section>
    </main>

    <!-- Rodapé -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <h4>BARBERSHOP PREMIUM</h4>
                    <p>📍 Rua dos Barbeiros, 123 - Centro</p>
                    <p>📞 (11) 99999-9999</p>
                    <p>⏰ Ter-Sáb: 08h às 23h | Dom: 09h às 18h</p>
                </div>
                <div class="footer-social">
                    <a href="#" class="social-icon">📷</a>
                    <a href="#" class="social-icon">📘</a>
                    <a href="#" class="social-icon">📱</a>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Barbershop Premium. Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <script>
        // Funções para seleção de serviços
        function selectService(id) {
            // Armazena serviço selecionado na sessão via AJAX
            fetch('api/selecionar-servico.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({servico_id: id})
            }).then(() => {
                window.location.href = 'agendar.php';
            });
        }

        function selectCombo(nome) {
            // Armazena combo selecionado
            fetch('api/selecionar-combo.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({combo_nome: nome})
            }).then(() => {
                window.location.href = 'agendar.php';
            });
        }
    </script>
</body>
</html>
