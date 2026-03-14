-- Banco de dados para Sistema de Agendamento Barbershop
-- Compatível com MySQL/MariaDB

CREATE DATABASE IF NOT EXISTS barbershop;
USE barbershop;

-- Tabela de serviços oferecidos
CREATE TABLE servicos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    preco DECIMAL(10,2),
    tempo_estimado INT COMMENT 'Tempo em minutos',
    ativo BOOLEAN DEFAULT TRUE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de clientes com hash do telefone
CREATE TABLE clientes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    primeiro_nome VARCHAR(50) NOT NULL,
    telefone_hash VARCHAR(255) NOT NULL COMMENT 'Hash do telefone para segurança',
    telefone_original VARCHAR(20) COMMENT 'Armazenado apenas temporariamente para SMS',
    ultimo_login TIMESTAMP NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telefone_hash (telefone_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de agendamentos
CREATE TABLE agendamentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cliente_id INT NOT NULL,
    data_agendamento DATE NOT NULL,
    hora_agendamento TIME NOT NULL,
    status ENUM('agendado', 'confirmado', 'cancelado', 'concluido') DEFAULT 'agendado',
    token_confirmacao VARCHAR(64) UNIQUE,
    sms_enviado BOOLEAN DEFAULT FALSE,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    INDEX idx_data_hora (data_agendamento, hora_agendamento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de serviços do agendamento
CREATE TABLE agendamento_servicos (
    agendamento_id INT NOT NULL,
    servico_id INT NOT NULL,
    PRIMARY KEY (agendamento_id, servico_id),
    FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id) ON DELETE CASCADE,
    FOREIGN KEY (servico_id) REFERENCES servicos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de horários bloqueados
CREATE TABLE horarios_bloqueados (
    id INT PRIMARY KEY AUTO_INCREMENT,
    data_bloqueio DATE NOT NULL,
    hora_bloqueio TIME NOT NULL,
    motivo VARCHAR(255),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_data_hora (data_bloqueio, hora_bloqueio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela para rate limiting de SMS
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telefone_hash VARCHAR(255) NOT NULL,
    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_telefone_tempo (telefone_hash, enviado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir serviços padrão
INSERT INTO servicos (nome, descricao, preco, tempo_estimado) VALUES
('Corte', 'Corte de cabelo tradicional', 45.00, 30),
('Barba', 'Barba com toalha quente e navalha', 35.00, 25),
('Procedimento químico', 'Progressiva, luzes ou coloração', 120.00, 90),
('Corte + Barba', 'Combo completo com desconto', 70.00, 50);