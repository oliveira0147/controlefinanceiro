<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

// Obter cartões do usuário
$cartoes = getCartoesUsuario($usuario['id']);

// Processar formulário de nova compra
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'adicionar_compra') {
        $cartao_id = intval($_POST['cartao_id']);
        $descricao = trim($_POST['descricao']);
        $valor_parcela = floatval($_POST['valor_parcela']);
        $num_parcelas = intval($_POST['num_parcelas']);
        $parcelas_pagas = intval($_POST['parcelas_pagas']);
        $data_compra = $_POST['data_compra'];
        
        if (empty($descricao) || $valor_parcela <= 0 || $num_parcelas <= 0 || $parcelas_pagas < 0) {
            $erro = 'Todos os campos são obrigatórios e devem ser válidos.';
        } elseif ($parcelas_pagas > $num_parcelas) {
            $erro = 'Quantidade de parcelas pagas não pode ser maior que o total de parcelas.';
        } else {
            // Verificar se o cartão pertence ao usuário
            $cartao_permitido = false;
            foreach ($cartoes as $cartao) {
                if ($cartao['id'] == $cartao_id) {
                    $cartao_permitido = true;
                    break;
                }
            }
            
            if (!$cartao_permitido) {
                $erro = 'Cartão não encontrado ou sem permissão.';
            } else {
                // Calcular valor total
                $valor_total = $valor_parcela * $num_parcelas;
                
                // Calcular mês de início baseado nas parcelas já pagas
                $data_compra_obj = new DateTime($data_compra);
                $mes_inicio = $data_compra_obj->format('m/Y');
                
                // Se já foram pagas parcelas, retroceder o mês de início
                if ($parcelas_pagas > 0) {
                    $data_inicio = clone $data_compra_obj;
                    $data_inicio->sub(new DateInterval('P' . $parcelas_pagas . 'M'));
                    $mes_inicio = $data_inicio->format('m/Y');
                }
                
                $pdo = conectarDB();
                
                try {
                    $pdo->beginTransaction();
                    
                    // Inserir compra
                    $stmt = $pdo->prepare("INSERT INTO compras (cartao_id, descricao, valor_total, num_parcelas, mes_inicio, data_compra) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$cartao_id, $descricao, $valor_total, $num_parcelas, $mes_inicio, $data_compra]);
                    
                    $compra_id = $pdo->lastInsertId();
                    
                    // Calcular e inserir parcelas
                    $parcelas = calcularParcelasComPagas($valor_total, $num_parcelas, $mes_inicio, $parcelas_pagas);
                    
                    $stmt_parcela = $pdo->prepare("INSERT INTO parcelas (compra_id, numero_parcela, valor_parcela, mes_vencimento, data_vencimento, status, data_pagamento) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    foreach ($parcelas as $parcela) {
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
                    $mensagem = 'Compra registrada com sucesso! ' . $num_parcelas . ' parcelas foram criadas automaticamente.';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $erro = 'Erro ao registrar compra: ' . $e->getMessage();
                }
            }
        }
    }
}

// Obter compras do cartão selecionado (apenas se o cartão pertencer ao usuário)
$cartao_selecionado = isset($_GET['cartao_id']) ? intval($_GET['cartao_id']) : 0;
$compras = [];
if ($cartao_selecionado > 0) {
    // Verificar se o cartão pertence ao usuário
    $cartao_permitido = false;
    foreach ($cartoes as $cartao) {
        if ($cartao['id'] == $cartao_selecionado) {
            $cartao_permitido = true;
            break;
        }
    }
    
    if ($cartao_permitido) {
        $compras = getComprasCartao($cartao_selecionado);
    } else {
        $erro = 'Cartão não encontrado ou sem permissão.';
        $cartao_selecionado = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova Compra - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Registrar Nova Compra - <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="cartoes.php">Meus Cartões</a></li>
                <li><a href="compras.php" class="active">Nova Compra</a></li>
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

        <!-- Nova Compra -->
        <div class="card">
            <h2>Registrar Nova Compra</h2>
            
            <?php if (empty($cartoes)): ?>
                <div class="alert alert-warning">
                    Você precisa ter pelo menos um cartão cadastrado para registrar compras. 
                    <a href="cartoes.php">Cadastrar cartão</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="adicionar_compra">
                    
                    <div class="form-group">
                        <label for="cartao_id">Seu Cartão:</label>
                        <select id="cartao_id" name="cartao_id" required>
                            <option value="">Selecione um cartão</option>
                            <?php foreach ($cartoes as $cartao): ?>
                                <option value="<?php echo $cartao['id']; ?>" <?php echo $cartao_selecionado == $cartao['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cartao['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="descricao">Descrição da Compra:</label>
                        <input type="text" id="descricao" name="descricao" required placeholder="Ex: Compras no supermercado">
                    </div>
                    
                    <div class="form-group">
                        <label for="valor_parcela">Valor da Parcela:</label>
                        <input type="number" id="valor_parcela" name="valor_parcela" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="num_parcelas">Número Total de Parcelas:</label>
                        <input type="number" id="num_parcelas" name="num_parcelas" min="1" max="24" required value="1">
                    </div>
                    
                    <div class="form-group">
                        <label for="parcelas_pagas">Quantidade de Parcelas Já Pagas:</label>
                        <input type="number" id="parcelas_pagas" name="parcelas_pagas" min="0" max="24" required value="0">
                        <small style="color: #666;">Informe quantas parcelas já foram pagas para calcular automaticamente a data de início</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="data_compra">Data da Compra:</label>
                        <input type="date" id="data_compra" name="data_compra" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <!-- Campos calculados automaticamente -->
                    <div class="form-group">
                        <label>Valor Total Calculado:</label>
                        <input type="text" id="valor_total_calculado" readonly style="background-color: #f8f9fa; font-weight: bold;">
                    </div>
                    
                    <div class="form-group">
                        <label>Mês de Início Calculado:</label>
                        <input type="text" id="mes_inicio_calculado" readonly style="background-color: #f8f9fa; font-weight: bold;">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Registrar Compra</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Compras do Cartão Selecionado -->
        <?php if ($cartao_selecionado > 0 && !empty($compras)): ?>
        <div class="card">
            <h2>Suas Compras do Cartão</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th>Valor Total</th>
                        <th>Parcelas</th>
                        <th>Mês Início</th>
                        <th>Data Compra</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras as $compra): ?>
                        <?php 
                        $parcelas = getParcelasCompra($compra['id']);
                        $parcelas_pagas = 0;
                        $parcelas_pendentes = 0;
                        
                        foreach ($parcelas as $parcela) {
                            if ($parcela['status'] == 'paga') {
                                $parcelas_pagas++;
                            } else {
                                $parcelas_pendentes++;
                            }
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($compra['descricao']); ?></td>
                            <td><?php echo formatarMoeda($compra['valor_total']); ?></td>
                            <td>
                                <?php echo $compra['num_parcelas']; ?>x 
                                <?php echo formatarMoeda($compra['valor_total'] / $compra['num_parcelas']); ?>
                                <br>
                                <small>
                                    <?php echo $parcelas_pagas; ?> pagas, 
                                    <?php echo $parcelas_pendentes; ?> pendentes
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($compra['mes_inicio']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($compra['data_compra'])); ?></td>
                            <td>
                                <a href="parcelas.php?compra_id=<?php echo $compra['id']; ?>" class="btn btn-primary btn-sm">Ver Parcelas</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-preenchimento da data atual
        document.getElementById('data_compra').value = '<?php echo date('Y-m-d'); ?>';
        
        // Cálculo automático do valor total e mês de início
        document.getElementById('valor_parcela').addEventListener('input', calcularValores);
        document.getElementById('num_parcelas').addEventListener('input', calcularValores);
        document.getElementById('parcelas_pagas').addEventListener('input', calcularValores);
        document.getElementById('data_compra').addEventListener('change', calcularValores);
        
        function calcularValores() {
            const valorParcela = parseFloat(document.getElementById('valor_parcela').value) || 0;
            const numParcelas = parseInt(document.getElementById('num_parcelas').value) || 1;
            const parcelasPagas = parseInt(document.getElementById('parcelas_pagas').value) || 0;
            const dataCompra = document.getElementById('data_compra').value;
            
            // Calcular valor total
            const valorTotal = valorParcela * numParcelas;
            document.getElementById('valor_total_calculado').value = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
            
            // Calcular mês de início
            if (dataCompra && parcelasPagas >= 0) {
                const data = new Date(dataCompra);
                const mesInicio = new Date(data.getFullYear(), data.getMonth() - parcelasPagas, data.getDate());
                const mesInicioFormatado = (mesInicio.getMonth() + 1).toString().padStart(2, '0') + '/' + mesInicio.getFullYear();
                document.getElementById('mes_inicio_calculado').value = mesInicioFormatado;
            }
        }
        
        // Calcular valores iniciais
        calcularValores();
    </script>
</body>
</html> 