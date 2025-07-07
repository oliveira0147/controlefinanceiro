<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// Função para verificar se o usuário está logado
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Função para obter dados do usuário logado
function getUsuarioLogado() {
    if (isset($_SESSION['usuario_id'])) {
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT u.*, c.nome as conta_nome FROM usuarios u JOIN contas c ON u.conta_id = c.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Função para obter dados da conta do usuário
function getContaUsuario($usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT c.* FROM contas c JOIN usuarios u ON c.id = u.conta_id WHERE u.id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch();
}

// Função para verificar se o usuário tem permissão para acessar dados de outro usuário da mesma conta
function verificarPermissaoConta($usuario_id, $conta_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND conta_id = ?");
    $stmt->execute([$usuario_id, $conta_id]);
    return $stmt->fetch() !== false;
}

// Função para calcular parcelas automaticamente
function calcularParcelas($valor_total, $num_parcelas, $mes_inicio) {
    $parcelas = [];
    $valor_parcela = $valor_total / $num_parcelas;
    
    // Converter mes_inicio (MM/YYYY) para DateTime
    $data_inicio = DateTime::createFromFormat('m/Y', $mes_inicio);
    
    for ($i = 1; $i <= $num_parcelas; $i++) {
        $data_vencimento = clone $data_inicio;
        $data_vencimento->add(new DateInterval('P' . ($i - 1) . 'M'));
        
        $parcelas[] = [
            'numero' => $i,
            'valor' => $valor_parcela,
            'mes_vencimento' => $data_vencimento->format('m/Y'),
            'data_vencimento' => $data_vencimento->format('Y-m-d')
        ];
    }
    
    return $parcelas;
}

// Função para calcular parcelas considerando as já pagas
function calcularParcelasComPagas($valor_total, $num_parcelas, $mes_inicio, $parcelas_pagas) {
    $parcelas = [];
    $valor_parcela = $valor_total / $num_parcelas;
    
    // Converter mes_inicio (MM/YYYY) para DateTime
    $data_inicio = DateTime::createFromFormat('m/Y', $mes_inicio);
    
    for ($i = 1; $i <= $num_parcelas; $i++) {
        $data_vencimento = clone $data_inicio;
        $data_vencimento->add(new DateInterval('P' . ($i - 1) . 'M'));
        
        // Determinar status da parcela
        $status = 'pendente';
        $data_pagamento = null;
        
        if ($i <= $parcelas_pagas) {
            $status = 'paga';
            // Usar a data de vencimento como data de pagamento (aproximada)
            $data_pagamento = $data_vencimento->format('Y-m-d');
        }
        
        $parcelas[] = [
            'numero' => $i,
            'valor' => $valor_parcela,
            'mes_vencimento' => $data_vencimento->format('m/Y'),
            'data_vencimento' => $data_vencimento->format('Y-m-d'),
            'status' => $status,
            'data_pagamento' => $data_pagamento
        ];
    }
    
    return $parcelas;
}

// Função para formatar valor monetário
function formatarMoeda($valor) {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Função para obter todos os usuários da mesma conta
function getUsuariosConta($conta_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE conta_id = ? ORDER BY nome");
    $stmt->execute([$conta_id]);
    return $stmt->fetchAll();
}

// Função para obter cartões de um usuário
function getCartoesUsuario($usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM cartoes WHERE usuario_id = ? ORDER BY nome");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll();
}

// Função para obter compras de um cartão
function getComprasCartao($cartao_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u1.nome as responsavel_principal_nome,
               u2.nome as responsavel_secundario_nome
        FROM compras c
        LEFT JOIN usuarios u1 ON c.responsavel_principal_id = u1.id
        LEFT JOIN usuarios u2 ON c.responsavel_secundario_id = u2.id
        WHERE c.cartao_id = ? 
        ORDER BY c.data_compra DESC
    ");
    $stmt->execute([$cartao_id]);
    return $stmt->fetchAll();
}

// Função para obter compras de um usuário (como responsável)
function getComprasUsuario($usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               ca.nome as cartao_nome,
               u1.nome as responsavel_principal_nome,
               u2.nome as responsavel_secundario_nome
        FROM compras c
        JOIN cartoes ca ON c.cartao_id = ca.id
        LEFT JOIN usuarios u1 ON c.responsavel_principal_id = u1.id
        LEFT JOIN usuarios u2 ON c.responsavel_secundario_id = u2.id
        WHERE c.responsavel_principal_id = ? OR c.responsavel_secundario_id = ?
        ORDER BY c.data_compra DESC
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    return $stmt->fetchAll();
}

// Função para obter membros da família (excluindo o usuário atual)
function getMembrosFamilia($conta_id, $excluir_usuario_id = null) {
    $pdo = conectarDB();
    $sql = "SELECT id, nome FROM usuarios WHERE conta_id = ?";
    $params = [$conta_id];
    
    if ($excluir_usuario_id) {
        $sql .= " AND id != ?";
        $params[] = $excluir_usuario_id;
    }
    
    $sql .= " ORDER BY nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para calcular valores baseado em percentuais
function calcularValoresResponsabilidade($valor_total, $percentual_principal, $percentual_secundario) {
    $valor_principal = ($valor_total * $percentual_principal) / 100;
    $valor_secundario = ($valor_total * $percentual_secundario) / 100;
    
    return [
        'valor_principal' => round($valor_principal, 2),
        'valor_secundario' => round($valor_secundario, 2)
    ];
}

// Função para obter parcelas de uma compra
function getParcelasCompra($compra_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM parcelas WHERE compra_id = ? ORDER BY numero_parcela");
    $stmt->execute([$compra_id]);
    return $stmt->fetchAll();
}

// Função para obter resumo mensal da conta
function getResumoMensalConta($mes_ano, $conta_id, $usuario_id = null) {
    $pdo = conectarDB();
    
    $sql = "SELECT 
                ca.nome as cartao_nome,
                u.nome as usuario_nome,
                SUM(p.valor_parcela) as total_parcelas,
                COUNT(p.id) as num_parcelas,
                SUM(CASE WHEN p.status = 'paga' THEN p.valor_parcela ELSE 0 END) as total_pago,
                SUM(CASE WHEN p.status = 'pendente' THEN p.valor_parcela ELSE 0 END) as total_pendente
            FROM parcelas p
            JOIN compras co ON p.compra_id = co.id
            JOIN cartoes ca ON co.cartao_id = ca.id
            JOIN usuarios u ON ca.usuario_id = u.id
            WHERE p.mes_vencimento = ? AND u.conta_id = ?";
    
    $params = [$mes_ano, $conta_id];
    
    if ($usuario_id) {
        $sql .= " AND ca.usuario_id = ?";
        $params[] = $usuario_id;
    }
    
    $sql .= " GROUP BY ca.id, ca.nome, u.nome ORDER BY u.nome, ca.nome";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para obter salário do usuário
function getSalarioUsuario($usuario_id, $mes_ano) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT * FROM salarios WHERE usuario_id = ? AND mes_ano = ?");
    $stmt->execute([$usuario_id, $mes_ano]);
    return $stmt->fetch();
}

// Função para obter salários da conta
function getSalariosConta($conta_id, $mes_ano) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT s.*, u.nome as usuario_nome 
        FROM salarios s 
        JOIN usuarios u ON s.usuario_id = u.id 
        WHERE u.conta_id = ? AND s.mes_ano = ?
        ORDER BY u.nome
    ");
    $stmt->execute([$conta_id, $mes_ano]);
    return $stmt->fetchAll();
}

// Função para criar nova conta
function criarConta($nome_conta) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("INSERT INTO contas (nome) VALUES (?)");
    $stmt->execute([$nome_conta]);
    return $pdo->lastInsertId();
}

// Função para verificar se email já existe em qualquer conta
function emailExiste($email) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch() !== false;
}

// Função para obter uma compra específica
function getCompra($compra_id, $usuario_id) {
    $pdo = conectarDB();
    $stmt = $pdo->prepare("
        SELECT c.*, 
               ca.nome as cartao_nome,
               u1.nome as responsavel_principal_nome,
               u2.nome as responsavel_secundario_nome
        FROM compras c
        JOIN cartoes ca ON c.cartao_id = ca.id
        LEFT JOIN usuarios u1 ON c.responsavel_principal_id = u1.id
        LEFT JOIN usuarios u2 ON c.responsavel_secundario_id = u2.id
        WHERE c.id = ? AND ca.usuario_id = ?
    ");
    $stmt->execute([$compra_id, $usuario_id]);
    return $stmt->fetch();
}

// Função para atualizar uma compra
function atualizarCompra($compra_id, $dados) {
    $pdo = conectarDB();
    
    try {
        $pdo->beginTransaction();
        
        // Atualizar dados da compra
        $stmt = $pdo->prepare("
            UPDATE compras SET 
                descricao = ?, 
                valor_total = ?, 
                num_parcelas = ?, 
                mes_inicio = ?, 
                data_compra = ?,
                responsavel_principal_id = ?,
                responsavel_secundario_id = ?,
                percentual_principal = ?,
                percentual_secundario = ?,
                valor_principal = ?,
                valor_secundario = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $dados['descricao'],
            $dados['valor_total'],
            $dados['num_parcelas'],
            $dados['mes_inicio'],
            $dados['data_compra'],
            $dados['responsavel_principal_id'],
            $dados['responsavel_secundario_id'],
            $dados['percentual_principal'],
            $dados['percentual_secundario'],
            $dados['valor_principal'],
            $dados['valor_secundario'],
            $compra_id
        ]);
        
        // Excluir parcelas antigas
        $stmt = $pdo->prepare("DELETE FROM parcelas WHERE compra_id = ?");
        $stmt->execute([$compra_id]);
        
        // Inserir novas parcelas
        $stmt_parcela = $pdo->prepare("
            INSERT INTO parcelas (compra_id, numero_parcela, valor_parcela, mes_vencimento, data_vencimento, status, data_pagamento) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($dados['parcelas'] as $parcela) {
            $stmt_parcela->execute([
                $compra_id,
                $parcela['numero'],
                $parcela['valor'],
                $parcela['mes_vencimento'],
                $parcela['data_vencimento'],
                $parcela['status'],
                $parcela['data_pagamento']
            ]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?> 