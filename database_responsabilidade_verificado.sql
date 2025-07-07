-- Script para adicionar funcionalidade de responsabilidade compartilhada
-- Execute este script no seu banco de dados existente
-- Este script verifica se as colunas já existem antes de criar

USE controle_financeiro;

-- Verificar e adicionar colunas se não existirem
-- 1. responsavel_principal_id
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'responsavel_principal_id') = 0,
    'ALTER TABLE compras ADD COLUMN responsavel_principal_id INT NULL',
    'SELECT "Coluna responsavel_principal_id já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. responsavel_secundario_id
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'responsavel_secundario_id') = 0,
    'ALTER TABLE compras ADD COLUMN responsavel_secundario_id INT NULL',
    'SELECT "Coluna responsavel_secundario_id já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. percentual_principal
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'percentual_principal') = 0,
    'ALTER TABLE compras ADD COLUMN percentual_principal DECIMAL(5,2) DEFAULT 100.00',
    'SELECT "Coluna percentual_principal já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. percentual_secundario
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'percentual_secundario') = 0,
    'ALTER TABLE compras ADD COLUMN percentual_secundario DECIMAL(5,2) DEFAULT 0.00',
    'SELECT "Coluna percentual_secundario já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. valor_principal
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'valor_principal') = 0,
    'ALTER TABLE compras ADD COLUMN valor_principal DECIMAL(10,2) NULL',
    'SELECT "Coluna valor_principal já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. valor_secundario
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'controle_financeiro' 
     AND TABLE_NAME = 'compras' 
     AND COLUMN_NAME = 'valor_secundario') = 0,
    'ALTER TABLE compras ADD COLUMN valor_secundario DECIMAL(10,2) NULL',
    'SELECT "Coluna valor_secundario já existe" as resultado'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Atualizar compras existentes (só se responsavel_principal_id for NULL)
UPDATE compras c 
JOIN cartoes ca ON c.cartao_id = ca.id 
SET c.responsavel_principal_id = ca.usuario_id,
    c.percentual_principal = 100.00,
    c.percentual_secundario = 0.00,
    c.valor_principal = c.valor_total,
    c.valor_secundario = 0.00
WHERE c.responsavel_principal_id IS NULL;

-- Verificar se todas as colunas necessárias existem
SELECT 
    COLUMN_NAME,
    CASE 
        WHEN COLUMN_NAME IN ('responsavel_principal_id', 'responsavel_secundario_id', 'percentual_principal', 'percentual_secundario', 'valor_principal', 'valor_secundario') 
        THEN 'EXISTE' 
        ELSE 'NÃO EXISTE' 
    END as status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'controle_financeiro' 
AND TABLE_NAME = 'compras' 
AND COLUMN_NAME IN ('responsavel_principal_id', 'responsavel_secundario_id', 'percentual_principal', 'percentual_secundario', 'valor_principal', 'valor_secundario'); 