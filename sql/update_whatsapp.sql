-- Atualização do banco de dados para suporte ao WhatsApp

-- Tabela de logs do WhatsApp
CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    para VARCHAR(20) NOT NULL,
    mensagem TEXT,
    status ENUM('enviado', 'entregue', 'lido', 'falha', 'erro') DEFAULT 'enviado',
    provider VARCHAR(50),
    message_id VARCHAR(100),
    resposta TEXT,
    erro TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_para (para),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Adicionar campo de WhatsApp token em clientes
ALTER TABLE clientes 
ADD COLUMN whatsapp_token VARCHAR(64) NULL AFTER telefone_original,
ADD COLUMN whatsapp_optin BOOLEAN DEFAULT TRUE AFTER whatsapp_token;

-- Tabela para templates personalizados
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT,
    template_id VARCHAR(100),
    componentes TEXT, -- JSON com estrutura do template
    status ENUM('aprovado', 'pendente', 'rejeitado') DEFAULT 'pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para webhooks recebidos
CREATE TABLE IF NOT EXISTS whatsapp_webhooks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payload TEXT,
    tipo VARCHAR(50),
    processado BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_processado (processado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir templates padrão
INSERT INTO whatsapp_templates (nome, template_id, status) VALUES
('agendamento_confirmado', 'confirmacao_template', 'aprovado'),
('lembrete_agendamento', 'lembrete_template', 'aprovado'),
('agendamento_cancelado', 'cancelamento_template', 'aprovado'),
('codigo_verificacao', '2fa_template', 'aprovado')
ON DUPLICATE KEY UPDATE status = 'aprovado';