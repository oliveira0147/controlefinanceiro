<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

// Processar formulário de salário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'adicionar_salario') {
        $usuario_id = intval($_POST['usuario_id']);
        $salario_base = floatval($_POST['salario_base']);
        $va = floatval($_POST['va']);
        $vr = floatval($_POST['vr']);
        $outros_beneficios = floatval($_POST['outros_beneficios']);
        $mes_ano = trim($_POST['mes_ano']);
        
        if ($salario_base <= 0 || empty($mes_ano)) {
            $erro = 'Salário base e mês/ano são obrigatórios.';
        } elseif (!preg_match('/^\d{2}\/\d{4}$/', $mes_ano)) {
            $erro = 'Formato do mês/ano deve ser MM/YYYY.';
        } else {
            // Verificar se o usuário pertence à mesma conta
            if (verificarPermissaoConta($usuario_id, $conta['id'])) {
                $pdo = conectarDB();
                
                // Verificar se já existe salário para este mês/usuário
                $stmt = $pdo->prepare("SELECT id FROM salarios WHERE usuario_id = ? AND mes_ano = ?");
                $stmt->execute([$usuario_id, $mes_ano]);
                
                if ($stmt->fetch()) {
                    $erro = 'Já existe um registro de salário para este membro neste mês.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO salarios (usuario_id, salario_base, va, vr, outros_beneficios, mes_ano) VALUES (?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$usuario_id, $salario_base, $va, $vr, $outros_beneficios, $mes_ano])) {
                        $mensagem = 'Salário registrado com sucesso!';
                    } else {
                        $erro = 'Erro ao registrar salário.';
                    }
                }
            } else {
                $erro = 'Membro não encontrado na sua conta familiar.';
            }
        }
    }
}

// Obter usuários da mesma conta
$usuarios_conta = getUsuariosConta($conta['id']);

// Obter salários do usuário logado
$salarios_usuario = [];
$pdo = conectarDB();
$stmt = $pdo->prepare("SELECT * FROM salarios WHERE usuario_id = ? ORDER BY mes_ano DESC");
$stmt->execute([$usuario['id']]);
$salarios_usuario = $stmt->fetchAll();

// Obter todos os salários da conta (para o dashboard consolidado)
$todos_salarios = getSalariosConta($conta['id'], date('m/Y')); // Mês atual por padrão
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salários - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Gerenciar Salários e Benefícios - <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="cartoes.php">Meus Cartões</a></li>
                <li><a href="compras.php">Nova Compra</a></li>
                <li><a href="salarios.php" class="active">Salários</a></li>
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

        <!-- Adicionar Novo Salário -->
        <div class="card">
            <h2>Registrar Salário e Benefícios</h2>
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar_salario">
                
                <div class="form-group">
                    <label for="usuario_id">Membro da Família:</label>
                    <select id="usuario_id" name="usuario_id" required>
                        <option value="">Selecione um membro</option>
                        <?php foreach ($usuarios_conta as $u): ?>
                            <option value="<?php echo $u['id']; ?>">
                                <?php echo htmlspecialchars($u['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="mes_ano">Mês/Ano (MM/YYYY):</label>
                    <input type="text" id="mes_ano" name="mes_ano" required placeholder="12/2024" pattern="\d{2}/\d{4}">
                </div>
                
                <div class="form-group">
                    <label for="salario_base">Salário Base:</label>
                    <input type="number" id="salario_base" name="salario_base" step="0.01" min="0" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="va">Vale Alimentação (VA):</label>
                    <input type="number" id="va" name="va" step="0.01" min="0" placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="vr">Vale Refeição (VR):</label>
                    <input type="number" id="vr" name="vr" step="0.01" min="0" placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="outros_beneficios">Outros Benefícios:</label>
                    <input type="number" id="outros_beneficios" name="outros_beneficios" step="0.01" min="0" placeholder="0.00">
                </div>
                
                <button type="submit" class="btn btn-primary">Registrar Salário</button>
            </form>
        </div>

        <!-- Meus Salários -->
        <div class="card">
            <h2>Meus Salários</h2>
            
            <?php if (empty($salarios_usuario)): ?>
                <p>Você ainda não possui salários registrados.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Mês/Ano</th>
                            <th>Salário Base</th>
                            <th>VA</th>
                            <th>VR</th>
                            <th>Outros</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salarios_usuario as $salario): ?>
                            <?php $total = $salario['salario_base'] + $salario['va'] + $salario['vr'] + $salario['outros_beneficios']; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($salario['mes_ano']); ?></td>
                                <td><?php echo formatarMoeda($salario['salario_base']); ?></td>
                                <td><?php echo formatarMoeda($salario['va']); ?></td>
                                <td><?php echo formatarMoeda($salario['vr']); ?></td>
                                <td><?php echo formatarMoeda($salario['outros_beneficios']); ?></td>
                                <td><strong><?php echo formatarMoeda($total); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Salários da Família (Dashboard Consolidado) -->
        <div class="card">
            <h2>Salários da Família - <?php echo date('m/Y'); ?></h2>
            
            <?php if (empty($todos_salarios)): ?>
                <p>Nenhum salário registrado para este mês na conta familiar.</p>
            <?php else: ?>
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
                        <?php 
                        $total_geral = 0;
                        foreach ($todos_salarios as $salario): 
                            $total = $salario['salario_base'] + $salario['va'] + $salario['vr'] + $salario['outros_beneficios'];
                            $total_geral += $total;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($salario['usuario_nome']); ?></td>
                                <td><?php echo formatarMoeda($salario['salario_base']); ?></td>
                                <td><?php echo formatarMoeda($salario['va']); ?></td>
                                <td><?php echo formatarMoeda($salario['vr']); ?></td>
                                <td><?php echo formatarMoeda($salario['outros_beneficios']); ?></td>
                                <td><strong><?php echo formatarMoeda($total); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f8f9fa; font-weight: bold;">
                            <td colspan="5">Total da Família</td>
                            <td><?php echo formatarMoeda($total_geral); ?></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>

        <!-- Estatísticas -->
        <?php if (!empty($todos_salarios)): ?>
        <div class="card">
            <h2>Estatísticas da Família</h2>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo count($todos_salarios); ?></h3>
                    <p>Membros com Salário</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo count($usuarios_conta); ?></h3>
                    <p>Total de Membros</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo formatarMoeda($total_geral); ?></h3>
                    <p>Total da Família</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo formatarMoeda($total_geral / count($todos_salarios)); ?></h3>
                    <p>Média por Membro</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-preenchimento do mês atual
        document.getElementById('mes_ano').value = '<?php echo date('m/Y'); ?>';
    </script>
</body>
</html> 