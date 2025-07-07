<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

if (!isset($_GET['id'])) {
    header('Location: compras.php');
    exit();
}

$compra_id = intval($_GET['id']);
$compra = getCompra($compra_id, $usuario['id']);
if (!$compra) {
    header('Location: compras.php');
    exit();
}

$cartoes = getCartoesUsuario($usuario['id']);
$parcelas = getParcelasCompra($compra_id);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao']) && $_POST['acao'] == 'editar_compra') {
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
    } else {
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
            $valor_total = $valor_parcela * $num_parcelas;
            $valores_resp = calcularValoresResponsabilidade($valor_total, $percentual_principal, $percentual_secundario);
            
            $data_compra_obj = new DateTime($data_compra);
            $mes_inicio = $data_compra_obj->format('m/Y');
            
            if ($parcelas_pagas > 0) {
                $data_inicio = clone $data_compra_obj;
                $data_inicio->sub(new DateInterval('P' . $parcelas_pagas . 'M'));
                $mes_inicio = $data_inicio->format('m/Y');
            }
            
            $novas_parcelas = calcularParcelasComPagas($valor_total, $num_parcelas, $mes_inicio, $parcelas_pagas);
            
            try {
                $dados_compra = [
                    'descricao' => $descricao,
                    'valor_total' => $valor_total,
                    'num_parcelas' => $num_parcelas,
                    'mes_inicio' => $mes_inicio,
                    'data_compra' => $data_compra,
                    'responsavel_principal_id' => $usuario['id'],
                    'responsavel_secundario_id' => $responsavel_secundario_id,
                    'percentual_principal' => $percentual_principal,
                    'percentual_secundario' => $percentual_secundario,
                    'valor_principal' => $valores_resp['valor_principal'],
                    'valor_secundario' => $valores_resp['valor_secundario'],
                    'parcelas' => $novas_parcelas
                ];
                
                atualizarCompra($compra_id, $dados_compra);
                $mensagem = 'Compra atualizada com sucesso!';
                
                $compra = getCompra($compra_id, $usuario['id']);
                $parcelas = getParcelasCompra($compra_id);
                
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar compra: ' . $e->getMessage();
            }
        }
    }
}

$parcelas_pagas_atual = 0;
foreach ($parcelas as $parcela) {
    if ($parcela['status'] == 'paga') {
        $parcelas_pagas_atual++;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Compra - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Editar Compra - <?php echo htmlspecialchars($conta['nome']); ?></p>
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

        <div class="card">
            <h2>Editar Compra: <?php echo htmlspecialchars($compra['descricao']); ?></h2>
            
            <form method="POST">
                <input type="hidden" name="acao" value="editar_compra">
                
                <div class="form-group">
                    <label for="cartao_id">Cartão:</label>
                    <select id="cartao_id" name="cartao_id" required>
                        <?php foreach ($cartoes as $cartao): ?>
                            <option value="<?php echo $cartao['id']; ?>" <?php echo $compra['cartao_id'] == $cartao['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cartao['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="descricao">Descrição da Compra:</label>
                    <input type="text" id="descricao" name="descricao" required value="<?php echo htmlspecialchars($compra['descricao']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="valor_parcela">Valor da Parcela:</label>
                    <input type="number" id="valor_parcela" name="valor_parcela" step="0.01" min="0.01" required value="<?php echo $compra['valor_total'] / $compra['num_parcelas']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="num_parcelas">Número Total de Parcelas:</label>
                    <input type="number" id="num_parcelas" name="num_parcelas" min="1" max="24" required value="<?php echo $compra['num_parcelas']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="parcelas_pagas">Quantidade de Parcelas Já Pagas:</label>
                    <input type="number" id="parcelas_pagas" name="parcelas_pagas" min="0" max="24" required value="<?php echo $parcelas_pagas_atual; ?>">
                    <small style="color: #666;">Atualmente: <?php echo $parcelas_pagas_atual; ?> parcelas pagas</small>
                </div>
                
                <div class="form-group">
                    <label for="data_compra">Data da Compra:</label>
                    <input type="date" id="data_compra" name="data_compra" required value="<?php echo $compra['data_compra']; ?>">
                </div>
                
                <!-- Responsabilidade Compartilhada -->
                <div class="form-group">
                    <label>Responsabilidade da Compra:</label>
                    <div style="border: 1px solid #ddd; padding: 15px; border-radius: 5px; background-color: #f9f9f9;">
                        <div style="margin-bottom: 10px;">
                            <label style="font-weight: bold; color: #495057;">Você (Responsável Principal):</label>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                <input type="number" id="percentual_principal" name="percentual_principal" min="0" max="100" value="<?php echo $compra['percentual_principal']; ?>" style="width: 80px;" onchange="calcularResponsabilidade()">
                                <span>%</span>
                                <span id="valor_principal_display" style="font-weight: bold; color: #28a745;"><?php echo formatarMoeda($compra['valor_principal']); ?></span>
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
                                    <option value="<?php echo $membro['id']; ?>" <?php echo $compra['responsavel_secundario_id'] == $membro['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($membro['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="div_secundario" style="display: <?php echo $compra['responsavel_secundario_id'] ? 'block' : 'none'; ?>;">
                            <label style="font-weight: bold; color: #495057;">Percentual do Cônjuge:</label>
                            <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px;">
                                <input type="number" id="percentual_secundario" name="percentual_secundario" min="0" max="100" value="<?php echo $compra['percentual_secundario']; ?>" style="width: 80px;" onchange="calcularResponsabilidade()">
                                <span>%</span>
                                <span id="valor_secundario_display" style="font-weight: bold; color: #dc3545;"><?php echo formatarMoeda($compra['valor_secundario']); ?></span>
                            </div>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 10px; background-color: #e9ecef; border-radius: 5px;">
                            <strong>Total: <span id="total_percentual"><?php echo $compra['percentual_principal'] + $compra['percentual_secundario']; ?></span>%</strong>
                            <div id="erro_percentual" style="color: #dc3545; font-size: 0.9em; margin-top: 5px; display: none;">
                                A soma dos percentuais deve ser 100%
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Campos calculados -->
                <div class="form-group">
                    <label>Valor Total Calculado:</label>
                    <input type="text" id="valor_total_calculado" readonly style="background-color: #f8f9fa; font-weight: bold;" value="<?php echo formatarMoeda($compra['valor_total']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Mês de Início Calculado:</label>
                    <input type="text" id="mes_inicio_calculado" readonly style="background-color: #f8f9fa; font-weight: bold;" value="<?php echo htmlspecialchars($compra['mes_inicio']); ?>">
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Atualizar Compra</button>
                    <a href="compras.php?cartao_id=<?php echo $compra['cartao_id']; ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('valor_parcela').addEventListener('input', calcularValores);
        document.getElementById('num_parcelas').addEventListener('input', calcularValores);
        document.getElementById('parcelas_pagas').addEventListener('input', calcularValores);
        document.getElementById('data_compra').addEventListener('change', calcularValores);
        
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
            
            const valorTotal = valorParcela * numParcelas;
            document.getElementById('valor_total_calculado').value = 'R$ ' + valorTotal.toFixed(2).replace('.', ',');
            
            if (dataCompra && parcelasPagas >= 0) {
                const data = new Date(dataCompra);
                const mesInicio = new Date(data.getFullYear(), data.getMonth() - parcelasPagas, data.getDate());
                const mesInicioFormatado = (mesInicio.getMonth() + 1).toString().padStart(2, '0') + '/' + mesInicio.getFullYear();
                document.getElementById('mes_inicio_calculado').value = mesInicioFormatado;
            }
            
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

            const valorPrincipal = (valorTotal * percentualPrincipal) / 100;
            const valorSecundario = (valorTotal * percentualSecundario) / 100;
            
            document.getElementById('valor_principal_display').textContent = 'R$ ' + valorPrincipal.toFixed(2).replace('.', ',');
            document.getElementById('valor_secundario_display').textContent = 'R$ ' + valorSecundario.toFixed(2).replace('.', ',');
        }
        
        calcularValores();
    </script>
</body>
</html> 