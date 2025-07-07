<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mes_atual = isset($_GET['mes']) ? $_GET['mes'] : date('m/Y');
$usuario_filtro = isset($_GET['usuario']) ? $_GET['usuario'] : '';

// Obter dados do resumo mensal da conta
$resumo_mensal = getResumoMensalConta($mes_atual, $conta['id'], $usuario_filtro ? $usuario_filtro : null);

// Calcular totais
$total_parcelas = 0;
$total_pago = 0;
$total_pendente = 0;

foreach ($resumo_mensal as $item) {
    $total_parcelas += $item['total_parcelas'];
    $total_pago += $item['total_pago'];
    $total_pendente += $item['total_pendente'];
}

// Obter salários da conta
$salarios = getSalariosConta($conta['id'], $mes_atual);

$total_salario = 0;
foreach ($salarios as $salario) {
    $total_salario += $salario['salario_base'] + $salario['va'] + $salario['vr'] + $salario['outros_beneficios'];
}

// Obter usuários da conta para o filtro
$usuarios_conta = getUsuariosConta($conta['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Bem-vindo(a), <?php echo htmlspecialchars($usuario['nome']); ?>! - <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="cartoes.php">Meus Cartões</a></li>
                <li><a href="compras.php">Nova Compra</a></li>
                <li><a href="salarios.php">Salários</a></li>
                <li><a href="usuarios.php">Usuários</a></li>
                <li><a href="logout.php">Sair</a></li>
            </ul>
        </div>
    </div>

    <div class="container">
        <!-- Filtros -->
        <div class="filters">
            <form method="GET">
                <div class="form-group">
                    <label for="mes">Mês/Ano:</label>
                    <input type="text" id="mes" name="mes" value="<?php echo htmlspecialchars($mes_atual); ?>" placeholder="MM/YYYY">
                </div>
                
                <div class="form-group">
                    <label for="usuario">Membro da Família:</label>
                    <select id="usuario" name="usuario">
                        <option value="">Todos os membros</option>
                        <?php foreach ($usuarios_conta as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $usuario_filtro == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="dashboard.php" class="btn btn-secondary">Limpar</a>
            </form>
        </div>

        <!-- Estatísticas -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3><?php echo formatarMoeda($total_parcelas); ?></h3>
                <p>Total de Parcelas</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo formatarMoeda($total_pendente); ?></h3>
                <p>Pendente</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo formatarMoeda($total_pago); ?></h3>
                <p>Pago</p>
            </div>
            
            <div class="stat-card">
                <h3><?php echo formatarMoeda($total_salario); ?></h3>
                <p>Total Salários + Benefícios</p>
            </div>
        </div>

        <!-- Resumo por Cartão -->
        <div class="card">
            <h2>Resumo por Cartão - <?php echo htmlspecialchars($mes_atual); ?></h2>
            
            <?php if (empty($resumo_mensal)): ?>
                <p>Nenhuma parcela encontrada para este período.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Membro</th>
                            <th>Cartão</th>
                            <th>Total Parcelas</th>
                            <th>Pendente</th>
                            <th>Pago</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumo_mensal as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['usuario_nome']); ?></td>
                                <td><?php echo htmlspecialchars($item['cartao_nome']); ?></td>
                                <td><?php echo formatarMoeda($item['total_parcelas']); ?></td>
                                <td><?php echo formatarMoeda($item['total_pendente']); ?></td>
                                <td><?php echo formatarMoeda($item['total_pago']); ?></td>
                                <td>
                                    <?php if ($item['total_pendente'] > 0): ?>
                                        <span class="badge badge-warning">Pendente</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Pago</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Salários e Benefícios -->
        <?php if (!empty($salarios)): ?>
        <div class="card">
            <h2>Salários e Benefícios - <?php echo htmlspecialchars($mes_atual); ?></h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Membro</th>
                        <th>Salário Base</th>
                        <th>VA</th>
                        <th>VR</th>
                        <th>Outros</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salarios as $salario): ?>
                        <?php $total_usuario = $salario['salario_base'] + $salario['va'] + $salario['vr'] + $salario['outros_beneficios']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($salario['usuario_nome']); ?></td>
                            <td><?php echo formatarMoeda($salario['salario_base']); ?></td>
                            <td><?php echo formatarMoeda($salario['va']); ?></td>
                            <td><?php echo formatarMoeda($salario['vr']); ?></td>
                            <td><?php echo formatarMoeda($salario['outros_beneficios']); ?></td>
                            <td><strong><?php echo formatarMoeda($total_usuario); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 