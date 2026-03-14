 <?php
/**
 * Página de login com autenticação de dois fatores via SMS
 */

session_start();
require_once 'config/database.php';
require_once 'includes/auth.php';

$auth = new Auth();
$error = '';
$step = 1; // 1 = formulário, 2 = verificação 2FA

// Processa login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['primeiro_nome']) && isset($_POST['telefone'])) {
        // Passo 1: Enviar código
        $result = $auth->login($_POST['primeiro_nome'], $_POST['telefone']);
        
        if ($result['success']) {
            $_SESSION['temp_telefone'] = $_POST['telefone'];
            $step = 2;
        } else {
            $error = $result['message'];
        }
    } elseif (isset($_POST['codigo_verificacao'])) {
        // Passo 2: Verificar código
        $result = $auth->verify2FA($_POST['codigo_verificacao']);
        
        if ($result['success']) {
            header('Location: agendar.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Barbershop Premium</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <!-- Logo e título -->
            <div class="login-header">
                <h1>✂️ BARBERSHOP</h1>
                <p>Faça login para agendar</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- Passo 1: Formulário de login -->
                <form method="POST" class="login-form" id="loginForm">
                    <div class="form-group">
                        <label for="primeiro_nome">
                            <span class="label-icon">👤</span>
                            Primeiro Nome
                        </label>
                        <input type="text" 
                               id="primeiro_nome" 
                               name="primeiro_nome" 
                               required 
                               placeholder="Digite seu primeiro nome"
                               autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="telefone">
                            <span class="label-icon">📱</span>
                            Telefone
                        </label>
                        <input type="tel" 
                               id="telefone" 
                               name="telefone" 
                               required 
                               placeholder="(11) 99999-9999"
                               class="phone-mask">
                        <small>Seu telefone é seguro e usado apenas para confirmações</small>
                    </div>

                    <button type="submit" class="btn-login">
                        ENVIAR CÓDIGO
                        <span class="btn-icon">→</span>
                    </button>
                </form>

            <?php else: ?>
                <!-- Passo 2: Verificação 2FA -->
                <form method="POST" class="login-form" id="verifyForm">
                    <div class="form-group">
                        <label for="codigo_verificacao">
                            <span class="label-icon">🔐</span>
                            Código de Verificação
                        </label>
                        <input type="text" 
                               id="codigo_verificacao" 
                               name="codigo_verificacao" 
                               required 
                               placeholder="000000"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               class="code-input"
                               autocomplete="off">
                        <small>Enviamos um código de 6 dígitos para <?= htmlspecialchars($_SESSION['temp_telefone'] ?? '') ?></small>
                    </div>

                    <button type="submit" class="btn-login">
                        VERIFICAR
                        <span class="btn-icon">✓</span>
                    </button>

                    <div class="resend-code">
                        <a href="#" onclick="resendCode()">Reenviar código</a>
                    </div>
                </form>

                <!-- Timer para reenvio -->
                <div class="timer" id="timer"></div>
            <?php endif; ?>

            <!-- Informações de segurança -->
            <div class="security-info">
                <p>🔒 Seus dados estão seguros</p>
                <p>⚡ Autenticação de dois fatores via SMS</p>
            </div>
        </div>
    </div>

    <script>
        // Máscara para telefone
        document.getElementById('telefone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
            } else if (value.length > 5) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,5})/, '($1) $2');
            } else {
                value = value.replace(/^(\d*)/, '($1');
            }
            
            e.target.value = value;
        });

        // Timer para reenvio
        let timeLeft = 300; // 5 minutos em segundos
        const timerElement = document.getElementById('timer');

        if (timerElement) {
            const timer = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerElement.textContent = `Código expira em: ${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    timerElement.textContent = 'Código expirado. Solicite um novo.';
                }
                
                timeLeft--;
            }, 1000);
        }

        // Função para reenviar código
        function resendCode() {
            fetch('api/resend-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      alert('Novo código enviado!');
                      timeLeft = 300; // Reset timer
                  } else {
                      alert(data.message || 'Erro ao reenviar código. Tente novamente.');
                  }
              });
        }
    </script>
</body>
</html>
