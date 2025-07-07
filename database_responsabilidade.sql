-- Script para adicionar funcionalidade de responsabilidade compartilhada
-- Execute este script no seu banco de dados existente

USE controle_financeiro;

-- Adicionar colunas de responsabilidade na tabela compras
ALTER TABLE compras 
ADD COLUMN responsavel_principal_id INT NULL,
ADD COLUMN responsavel_secundario_id INT NULL,
ADD COLUMN percentual_principal DECIMAL(5,2) DEFAULT 100.00,
ADD COLUMN percentual_secundario DECIMAL(5,2) DEFAULT 0.00,
ADD COLUMN valor_principal DECIMAL(10,2) NULL,
ADD COLUMN valor_secundario DECIMAL(10,2) NULL,
ADD FOREIGN KEY (responsavel_principal_id) REFERENCES usuarios(id) ON DELETE SET NULL,
ADD FOREIGN KEY (responsavel_secundario_id) REFERENCES usuarios(id) ON DELETE SET NULL;

-- Atualizar compras existentes para ter o dono do cartão como responsável principal
UPDATE compras c 
JOIN cartoes ca ON c.cartao_id = ca.id 
SET c.responsavel_principal_id = ca.usuario_id,
    c.percentual_principal = 100.00,
    c.percentual_secundario = 0.00,
    c.valor_principal = c.valor_total,
    c.valor_secundario = 0.00;

-- Tornar as colunas obrigatórias após atualizar dados existentes
-- Separando cada MODIFY em um comando ALTER TABLE diferente
ALTER TABLE compras MODIFY COLUMN responsavel_principal_id INT NOT NULL;
ALTER TABLE compras MODIFY COLUMN percentual_principal DECIMAL(5,2) NOT NULL DEFAULT 100.00;
ALTER TABLE compras MODIFY COLUMN percentual_secundario DECIMAL(5,2) NOT NULL DEFAULT 0.00;
ALTER TABLE compras MODIFY COLUMN valor_principal DECIMAL(10,2) NOT NULL;
ALTER TABLE compras MODIFY COLUMN valor_secundario DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Adicionar índice para melhor performance
CREATE INDEX idx_compras_responsaveis ON compras(responsavel_principal_id, responsavel_secundario_id); 