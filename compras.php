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
        $percentual_principal = floatval($_POST['percentual_principal']);
        $percentual_secundario = floatval($_POST['percentual_secundario']);
        $responsavel_secundario_id = !empty($_POST['responsavel_secundario_id']) ? intval($_POST['responsavel_secundario_id']) : null;
        
        if (empty($descricao) || $valor_parcela <= 0 || $num_parcelas <= 0 || $parcelas_pagas < 0) {
            $erro = 'Todos os campos são obrigatórios e devem ser válidos.';
        } elseif ($parcelas_pagas > $num_parcelas) {
            $erro = 'Quantidade de parcelas pagas não pode ser maior que o total de parcelas.';
        } elseif ($percentual_principal + $percentual_secundario != 100) {
            $erro = 'A soma dos percentuais deve ser 100%.';
        } elseif ($responsavel_secundario_id && $percentual_secundario <= 0) {
            $erro = 'Selecione um percentual para o cônjuge.';
        } elseif ($responsavel_secundario_id && $percentual_principal <= 0) {
            $erro = 'Você deve ter pelo menos 1% de responsabilidade.';
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
                
                // Calcular valores de responsabilidade
                $valores_resp = calcularValoresResponsabilidade($valor_total, $percentual_principal, $percentual_secundario);
                
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
                    
                    // Inserir compra com responsabilidade
                    $stmt = $pdo->prepare("
                        INSERT INTO compras (
                            cartao_id, descricao, valor_total, num_parcelas, mes_inicio, data_compra,
                            responsavel_principal_id, responsavel_secundario_id, 
                            percentual_principal, percentual_secundario,
                            valor_principal, valor_secundario
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $cartao_id, $descricao, $valor_total, $num_parcelas, $mes_inicio, $data_compra,
                        $usuario['id'], $responsavel_secundario_id,
                        $percentual_principal, $percentual_secundario,
                        $valores_resp['valor_principal'], $valores_resp['valor_secundario']
                    ]);
                    
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
    } elseif ($_POST['acao'] == 'excluir_compra') {
        $compra_id = intval($_POST['compra_id']);
        
        // Verificar se a compra pertence ao usuário
        $pdo = conectarDB();
        $stmt = $pdo->prepare("SELECT c.id, c.descricao FROM compras c JOIN cartoes ca ON c.cartao_id = ca.id WHERE c.id = ? AND ca.usuario_id = ?");
        $stmt->execute([$compra_id, $usuario['id']]);
        $compra = $stmt->fetch();
        
        if ($compra) {
            try {
                $pdo->beginTransaction();
                
                // Excluir parcelas primeiro
                $stmt = $pdo->prepare("DELETE FROM parcelas WHERE compra_id = ?");
                $stmt->execute([$compra_id]);
                
                // Excluir a compra
                $stmt = $pdo->prepare("DELETE FROM compras WHERE id = ?");
                $stmt->execute([$compra_id]);
                
                $pdo->commit();
                $mensagem = 'Compra "' . htmlspecialchars($compra['descricao']) . '" excluída com sucesso!';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao excluir compra: ' . $e->getMessage();
            }
        } else {
            $erro = 'Compra não encontrada ou sem permissão.';
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
    <style>
        .form-toggle {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .form-toggle:hover {
            background: #e9ecef;
        }
        
        .form-toggle h3 {
            margin: 0;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .form-toggle .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s ease;
        }
        
        .form-toggle.active .toggle-icon {
            transform: rotate(180deg);
        }
        
        .form-content {
            display: none;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        
        .form-toggle.active .form-content {
            display: block;
        }
        
        .compras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .compra-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }
        
        .compra-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .compra-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .compra-titulo {
            font-weight: bold;
            color: #495057;
            margin: 0;
        }
        
        .compra-valor {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
        
        .compra-detalhes {
            font-size: 0.9em;
            color: #6c757d;
            margin: 5px 0;
        }
        
        .compra-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-pendente {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-paga {
            background: #d4edda;
            color: #155724;
        }
    </style>
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

        <!-- Nova Compra - Formulário Expansível -->
        <div class="form-toggle" onclick="toggleForm()">
            <h3>
                <span>➕ Registrar Nova Compra</span>
                <span class="toggle-icon">▼</span>
            </h3>
            <div class="form-content" onclick="event.stopPropagation()">
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
                        
                        <!-- Responsabilidade Compartilhada -->
                        <div class="form-group">
                            <label>Responsabilidade da Compra:</label>
                            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background-color: #f9f9f9;">
                                <div style="margin-bottom: 10px;">
                                    <label style="font-weight: bold; color: #495057;">Você (Responsável Principal):</label>
                                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                        <input type="number" id="percentual_principal" name="percentual_principal" min="0" max="100" value="100" style="width: 80px;" onchange="calcularResponsabilidade()">
                                        <span>%</span>
                                        <span id="valor_principal_display" style="font-weight: bold; color: #28a745;"></span>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 10px;">
                                    <label for="responsavel_secundario" style="font-weight: bold; color: #495057;">Compartilhar com:</label>
                                    <select id="responsavel_secundario" name="responsavel_secundario_id" style="width: 100%; margin-top: 5px;" onchange="calcularResponsabilidade()">
                                        <option value="">Ninguém (só eu)</option>
                                        <?php 
                                        $membros = getMembrosFamilia($conta['id'], $usuario['id']);
                                        foreach ($membros as $membro): 
                                        ?>
                                            <option value="<?php echo $membro['id']; ?>"><?php echo htmlspecialchars($membro['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div id="div_secundario" style="display: none;">
                                    <label style="font-weight: bold; color: #495057;">Percentual do Cônjuge:</label>
                                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                        <input type="number" id="percentual_secundario" name="percentual_secundario" min="0" max="100" value="0" style="width: 80px;" onchange="calcularResponsabilidade()">
                                        <span>%</span>
                                        <span id="valor_secundario_display" style="font-weight: bold; color: #dc3545;"></span>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 15px; padding: 10px; background-color: #e9ecef; border-radius: 5px;">
                                    <strong>Total: <span id="total_percentual">100</span>%</strong>
                                    <div id="erro_percentual" style="color: #dc3545; font-size: 0.9em; margin-top: 5px; display: none;">
                                        A soma dos percentuais deve ser 100%
                                    </div>
                                </div>
                            </div>
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
        </div>

        <!-- Compras do Cartão Selecionado -->
        <?php if ($cartao_selecionado > 0 && !empty($compras)): ?>
        <div class="card">
            <h2>Suas Compras do Cartão</h2>
            <div class="compras-grid">
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
                    <div class="compra-card">
                        <div class="compra-header">
                            <h4 class="compra-titulo"><?php echo htmlspecialchars($compra['descricao']); ?></h4>
                            <span class="compra-valor"><?php echo formatarMoeda($compra['valor_total']); ?></span>
                        </div>
                        
                        <div class="compra-detalhes">
                            <strong>Parcelas:</strong> <?php echo $compra['num_parcelas']; ?>x <?php echo formatarMoeda($compra['valor_total'] / $compra['num_parcelas']); ?>
                        </div>
                        
                        <div class="compra-detalhes">
                            <strong>Status:</strong> 
                            <span class="compra-status status-paga"><?php echo $parcelas_pagas; ?> pagas</span>
                            <span class="compra-status status-pendente"><?php echo $parcelas_pendentes; ?> pendentes</span>
                        </div>
                        
                        <div class="compra-detalhes">
                            <strong>Responsabilidade:</strong>
                            <?php if ($compra['responsavel_secundario_id']): ?>
                                <div style="margin-top: 5px;">
                                    <span style="color: #28a745;"><?php echo htmlspecialchars($compra['responsavel_principal_nome']); ?>: <?php echo $compra['percentual_principal']; ?>% (<?php echo formatarMoeda($compra['valor_principal']); ?>)</span>
                                    <br>
                                    <span style="color: #dc3545;"><?php echo htmlspecialchars($compra['responsavel_secundario_nome']); ?>: <?php echo $compra['percentual_secundario']; ?>% (<?php echo formatarMoeda($compra['valor_secundario']); ?>)</span>
                                </div>
                            <?php else: ?>
                                <span style="color: #28a745;"><?php echo htmlspecialchars($compra['responsavel_principal_nome']); ?>: 100%</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="compra-detalhes">
                            <strong>Início:</strong> <?php echo htmlspecialchars($compra['mes_inicio']); ?>
                        </div>
                        
                        <div class="compra-detalhes">
                            <strong>Data:</strong> <?php echo date('d/m/Y', strtotime($compra['data_compra'])); ?>
                        </div>
                        
                        <div style="margin-top: 10px;">
                            <a href="parcelas.php?compra_id=<?php echo $compra['id']; ?>" class="btn btn-primary btn-sm">Ver Parcelas</a>
                            <button onclick="confirmarExclusaoCompra(<?php echo $compra['id']; ?>, '<?php echo htmlspecialchars($compra['descricao']); ?>')" class="btn btn-danger btn-sm">Excluir</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Formulário oculto para exclusão de compras -->
    <form id="formExcluirCompra" method="POST" style="display: none;">
        <input type="hidden" name="acao" value="excluir_compra">
        <input type="hidden" name="compra_id" id="compra_id_excluir">
    </form>

    <script>
        // Auto-preenchimento da data atual
        document.getElementById('data_compra').value = '<?php echo date('Y-m-d'); ?>';
        
        // Cálculo automático do valor total e mês de início
        document.getElementById('valor_parcela').addEventListener('input', calcularValores);
        document.getElementById('num_parcelas').addEventListener('input', calcularValores);
        document.getElementById('parcelas_pagas').addEventListener('input', calcularValores);
        document.getElementById('data_compra').addEventListener('change', calcularValores);
        
        // Responsabilidade compartilhada
        document.getElementById('responsavel_secundario').addEventListener('change', function() {
            const divSecundario = document.getElementById('div_secundario');
            if (this.value) {
                divSecundario.style.display = 'block';
            } else {
                divSecundario.style.display = 'none';
                document.getElementById('percentual_secundario').value = 0;
                calcularResponsabilidade();
            }
        });
        
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
            
            // Recalcular responsabilidade
            calcularResponsabilidade();
        }
        
        function calcularResponsabilidade() {
            const valorParcela = parseFloat(document.getElementById('valor_parcela').value) || 0;
            const numParcelas = parseInt(document.getElementById('num_parcelas').value) || 1;
            const valorTotal = valorParcela * numParcelas;
            
            const percentualPrincipal = parseFloat(document.getElementById('percentual_principal').value) || 0;
            const percentualSecundario = parseFloat(document.getElementById('percentual_secundario').value) || 0;
            const totalPercentual = percentualPrincipal + percentualSecundario;

            document.getElementById('total_percentual').textContent = totalPercentual.toFixed(2);

            if (totalPercentual > 100) {
                document.getElementById('erro_percentual').style.display = 'block';
                document.getElementById('percentual_principal').value = Math.max(0, 100 - percentualSecundario);
                return;
            } else {
                document.getElementById('erro_percentual').style.display = 'none';
            }

            // Atualizar valores exibidos
            const valorPrincipal = (valorTotal * percentualPrincipal) / 100;
            const valorSecundario = (valorTotal * percentualSecundario) / 100;
            
            document.getElementById('valor_principal_display').textContent = 'R$ ' + valorPrincipal.toFixed(2).replace('.', ',');
            document.getElementById('valor_secundario_display').textContent = 'R$ ' + valorSecundario.toFixed(2).replace('.', ',');
        }

        function confirmarExclusaoCompra(compraId, descricao) {
            if (confirm('Tem certeza que deseja excluir a compra "' + descricao + '"?')) {
                document.getElementById('compra_id_excluir').value = compraId; // Preenche o campo oculto
                document.getElementById('formExcluirCompra').submit(); // Envia o formulário
            }
        }

        // Toggle do formulário
        function toggleForm() {
            const formToggle = document.querySelector('.form-toggle');
            formToggle.classList.toggle('active');
        }
        
        // Calcular valores iniciais
        calcularValores();
    </script>
</body>
</html> 