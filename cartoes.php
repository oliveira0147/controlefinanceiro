<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

// Processar formulário de novo cartão
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'adicionar_cartao') {
        $nome = trim($_POST['nome']);
        $limite = floatval($_POST['limite']);
        
        if (empty($nome)) {
            $erro = 'Nome do cartão é obrigatório.';
        } else {
            $pdo = conectarDB();
            $stmt = $pdo->prepare("INSERT INTO cartoes (usuario_id, nome, limite) VALUES (?, ?, ?)");
            
            if ($stmt->execute([$usuario['id'], $nome, $limite])) {
                $mensagem = 'Cartão adicionado com sucesso!';
            } else {
                $erro = 'Erro ao adicionar cartão.';
            }
        }
    }
}

// Obter cartões do usuário
$cartoes = getCartoesUsuario($usuario['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Cartões - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Gerenciar Seus Cartões de Crédito - <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="cartoes.php" class="active">Meus Cartões</a></li>
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

        <!-- Adicionar Novo Cartão -->
        <div class="card">
            <h2>Adicionar Seu Novo Cartão</h2>
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar_cartao">
                
                <div class="form-group">
                    <label for="nome">Nome do Cartão:</label>
                    <input type="text" id="nome" name="nome" required placeholder="Ex: Nubank, Pan, Itaú...">
                </div>
                
                <div class="form-group">
                    <label for="limite">Limite do Cartão (opcional):</label>
                    <input type="number" id="limite" name="limite" step="0.01" min="0" placeholder="0.00">
                </div>
                
                <button type="submit" class="btn btn-primary">Adicionar Cartão</button>
            </form>
        </div>

        <!-- Lista de Cartões -->
        <div class="card">
            <h2>Seus Cartões</h2>
            
            <?php if (empty($cartoes)): ?>
                <p>Você ainda não possui cartões cadastrados.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome do Cartão</th>
                            <th>Limite</th>
                            <th>Data de Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cartoes as $cartao): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cartao['nome']); ?></td>
                                <td><?php echo $cartao['limite'] > 0 ? formatarMoeda($cartao['limite']) : 'Não informado'; ?></td>
                                <td><?php echo date('d/m/Y', strtotime($cartao['data_criacao'])); ?></td>
                                <td>
                                    <a href="compras.php?cartao_id=<?php echo $cartao['id']; ?>" class="btn btn-primary btn-sm">Ver Compras</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Resumo dos Cartões -->
        <?php if (!empty($cartoes)): ?>
        <div class="card">
            <h2>Resumo dos Seus Cartões</h2>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo count($cartoes); ?></h3>
                    <p>Total de Cartões</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php 
                        $total_limite = 0;
                        foreach ($cartoes as $cartao) {
                            $total_limite += $cartao['limite'];
                        }
                        echo formatarMoeda($total_limite);
                    ?></h3>
                    <p>Limite Total</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 