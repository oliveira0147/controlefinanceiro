<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

$compra_id = isset($_GET['compra_id']) ? intval($_GET['compra_id']) : 0;

if ($compra_id <= 0) {
    header('Location: compras.php');
    exit();
}

// Obter dados da compra
$pdo = conectarDB();
$stmt = $pdo->prepare("
    SELECT c.*, ca.nome as cartao_nome, ca.usuario_id 
    FROM compras c 
    JOIN cartoes ca ON c.cartao_id = ca.id 
    WHERE c.id = ?
");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch();

if (!$compra) {
    header('Location: compras.php');
    exit();
}

// Verificar se o usuário tem permissão para ver esta compra (deve ser o dono do cartão)
if ($compra['usuario_id'] != $usuario['id']) {
    header('Location: compras.php');
    exit();
}

// Processar alteração de status da parcela
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'alterar_status') {
        $parcela_id = intval($_POST['parcela_id']);
        $novo_status = $_POST['novo_status'];
        $data_pagamento = $_POST['data_pagamento'];
        
        if ($novo_status == 'paga' && empty($data_pagamento)) {
            $erro = 'Data de pagamento é obrigatória quando marcar como paga.';
        } else {
            // Verificar se a parcela pertence à compra do usuário
            $stmt = $pdo->prepare("
                SELECT p.id FROM parcelas p 
                JOIN compras c ON p.compra_id = c.id 
                JOIN cartoes ca ON c.cartao_id = ca.id 
                WHERE p.id = ? AND ca.usuario_id = ?
            ");
            $stmt->execute([$parcela_id, $usuario['id']]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE parcelas SET status = ?, data_pagamento = ? WHERE id = ? AND compra_id = ?");
                
                if ($stmt->execute([$novo_status, $data_pagamento ?: null, $parcela_id, $compra_id])) {
                    $mensagem = 'Status da parcela atualizado com sucesso!';
                } else {
                    $erro = 'Erro ao atualizar status da parcela.';
                }
            } else {
                $erro = 'Parcela não encontrada ou sem permissão.';
            }
        }
    }
}

// Obter parcelas da compra
$parcelas = getParcelasCompra($compra_id);

// Calcular estatísticas
$total_parcelas = count($parcelas);
$parcelas_pagas = 0;
$parcelas_pendentes = 0;
$total_pago = 0;
$total_pendente = 0;

foreach ($parcelas as $parcela) {
    if ($parcela['status'] == 'paga') {
        $parcelas_pagas++;
        $total_pago += $parcela['valor_parcela'];
    } else {
        $parcelas_pendentes++;
        $total_pendente += $parcela['valor_parcela'];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parcelas - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Gerenciar Parcelas - <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="cartoes.php">Meus Cartões</a></li>
                <li><a href="compras.php">Nova Compra</a></li>
                <li><a href="salarios.php">Salários</a></li>
                <li><a href="usuarios.php">Usuários</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <?php if ($mensagem): ?>
            <div class="alert alert-success"><?php echo $mensagem; ?></div>
        <?php endif; ?>
        
        <?php if ($erro): ?>
            <div class="alert alert-danger"><?php echo $erro; ?></div>
        <?php endif; ?>

        <!-- Informações da Compra -->
        <div class="card">
            <h2>Detalhes da Sua Compra</h2>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($compra['descricao']); ?></h3>
                    <p>Descrição</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($compra['cartao_nome']); ?></h3>
                    <p>Seu Cartão</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo formatarMoeda($compra['valor_total']); ?></h3>
                    <p>Valor Total</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $compra['num_parcelas']; ?>x <?php echo formatarMoeda($compra['valor_total'] / $compra['num_parcelas']); ?></h3>
                    <p>Parcelas</p>
                </div>
            </div>
        </div>

        <!-- Estatísticas das Parcelas -->
        <div class="card">
            <h2>Resumo das Suas Parcelas</h2>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo $parcelas_pagas; ?>/<?php echo $total_parcelas; ?></h3>
                    <p>Parcelas Pagas</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo formatarMoeda($total_pago); ?></h3>
                    <p>Total Pago</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo formatarMoeda($total_pendente); ?></h3>
                    <p>Total Pendente</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $parcelas_pendentes; ?></h3>
                    <p>Parcelas Pendentes</p>
                </div>
            </div>
        </div>

        <!-- Lista de Parcelas -->
        <div class="card">
            <h2>Suas Parcelas</h2>
            
            <?php if (empty($parcelas)): ?>
                <p>Nenhuma parcela encontrada.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Parcela</th>
                            <th>Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                            <th>Data Pagamento</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelas as $parcela): ?>
                            <tr>
                                <td><?php echo $parcela['numero_parcela']; ?>ª</td>
                                <td><?php echo formatarMoeda($parcela['valor_parcela']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($parcela['data_vencimento'])); ?></td>
                                <td>
                                    <?php if ($parcela['status'] == 'paga'): ?>
                                        <span class="badge badge-success">Paga</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $parcela['data_pagamento'] ? date('d/m/Y', strtotime($parcela['data_pagamento'])) : '-'; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="acao" value="alterar_status">
                                        <input type="hidden" name="parcela_id" value="<?php echo $parcela['id']; ?>">
                                        
                                        <?php if ($parcela['status'] == 'pendente'): ?>
                                            <input type="hidden" name="novo_status" value="paga">
                                            <input type="date" name="data_pagamento" required style="margin-right: 0.5rem;">
                                            <button type="submit" class="btn btn-success btn-sm">Marcar como Paga</button>
                                        <?php else: ?>
                                            <input type="hidden" name="novo_status" value="pendente">
                                            <input type="hidden" name="data_pagamento" value="">
                                            <button type="submit" class="btn btn-warning btn-sm">Marcar como Pendente</button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="compras.php?cartao_id=<?php echo $compra['cartao_id']; ?>" class="btn btn-secondary">Voltar para Compras</a>
        </div>
    </div>
</body>
</html> 