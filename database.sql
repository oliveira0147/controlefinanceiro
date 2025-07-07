-- Script de criação do banco de dados para Controle Financeiro Familiar
-- Execute este script no seu MySQL para criar o banco e as tabelas

CREATE DATABASE IF NOT EXISTS controle_financeiro;
USE controle_financeiro;

-- Tabela de contas familiares
CREATE TABLE contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de usuários (agora vinculada a uma conta)
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conta_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE CASCADE
);

-- Tabela de cartões de crédito
CREATE TABLE cartoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    limite DECIMAL(10,2) DEFAULT 0.00,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Tabela de compras
CREATE TABLE compras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cartao_id INT NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    num_parcelas INT NOT NULL,
    mes_inicio VARCHAR(7) NOT NULL, -- formato MM/YYYY
    data_compra DATE NOT NULL,
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cartao_id) REFERENCES cartoes(id) ON DELETE CASCADE
);

-- Tabela de parcelas
CREATE TABLE parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compra_id INT NOT NULL,
    numero_parcela INT NOT NULL,
    valor_parcela DECIMAL(10,2) NOT NULL,
    mes_vencimento VARCHAR(7) NOT NULL, -- formato MM/YYYY
    status ENUM('pendente', 'paga') DEFAULT 'pendente',
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    FOREIGN KEY (compra_id) REFERENCES compras(id) ON DELETE CASCADE
);

-- Tabela de salários e benefícios
CREATE TABLE salarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    salario_base DECIMAL(10,2) NOT NULL,
    va DECIMAL(10,2) DEFAULT 0.00,
    vr DECIMAL(10,2) DEFAULT 0.00,
    outros_beneficios DECIMAL(10,2) DEFAULT 0.00,
    mes_ano VARCHAR(7) NOT NULL, -- formato MM/YYYY
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Inserir dados de exemplo
INSERT INTO contas (nome) VALUES 
('Família Silva');

INSERT INTO usuarios (conta_id, nome, email, senha) VALUES 
(1, 'João Silva', 'joao@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'), -- senha: password
(1, 'Maria Silva', 'maria@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- senha: password

INSERT INTO cartoes (usuario_id, nome, limite) VALUES 
(1, 'Nubank', 5000.00),
(1, 'Pan', 3000.00),
(2, 'Itaú', 4000.00);

INSERT INTO salarios (usuario_id, salario_base, va, vr, mes_ano) VALUES 
(1, 5000.00, 600.00, 400.00, '12/2024'),
(2, 4500.00, 600.00, 400.00, '12/2024'); 