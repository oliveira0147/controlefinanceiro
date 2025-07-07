<?php
require_once __DIR__ . '/includes/functions.php';
verificarLogin();

$usuario = getUsuarioLogado();
$conta = getContaUsuario($usuario['id']);
$mensagem = '';
$erro = '';

// Processar formulário de novo usuário
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] == 'adicionar_usuario') {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'];
        $confirmar_senha = $_POST['confirmar_senha'];
        
        if (empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
            $erro = 'Por favor, preencha todos os campos.';
        } elseif ($senha !== $confirmar_senha) {
            $erro = 'As senhas não coincidem.';
        } elseif (strlen($senha) < 6) {
            $erro = 'A senha deve ter pelo menos 6 caracteres.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Email inválido.';
        } else {
            $pdo = conectarDB();
            
            // Verificar se o email já existe
            if (emailExiste($email)) {
                $erro = 'Este email já está cadastrado.';
            } else {
                // Criptografar senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Inserir novo usuário na mesma conta
                $stmt = $pdo->prepare("INSERT INTO usuarios (conta_id, nome, email, senha) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$conta['id'], $nome, $email, $senha_hash])) {
                    $mensagem = 'Usuário adicionado à conta familiar com sucesso!';
                } else {
                    $erro = 'Erro ao adicionar usuário.';
                }
            }
        }
    } elseif ($_POST['acao'] == 'excluir_usuario') {
        $usuario_id = intval($_POST['usuario_id']);
        
        if ($usuario_id == $usuario['id']) {
            $erro = 'Você não pode excluir sua própria conta.';
        } else {
            // Verificar se o usuário pertence à mesma conta
            if (verificarPermissaoConta($usuario_id, $conta['id'])) {
                $pdo = conectarDB();
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND conta_id = ?");
                
                if ($stmt->execute([$usuario_id, $conta['id']])) {
                    $mensagem = 'Usuário removido da conta familiar com sucesso!';
                } else {
                    $erro = 'Erro ao remover usuário.';
                }
            } else {
                $erro = 'Usuário não encontrado na sua conta familiar.';
            }
        }
    }
}

// Obter usuários da mesma conta
$usuarios = getUsuariosConta($conta['id']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Gerenciar Usuários da Conta: <?php echo htmlspecialchars($conta['nome']); ?></p>
        </div>
    </div>

    <div class="nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="cartoes.php">Meus Cartões</a></li>
                <li><a href="compras.php">Nova Compra</a></li>
                <li><a href="salarios.php">Salários</a></li>
                <li><a href="usuarios.php" class="active">Usuários</a></li>
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

        <!-- Adicionar Novo Usuário -->
        <div class="card">
            <h2>Adicionar Membro à Conta Familiar</h2>
            <form method="POST">
                <input type="hidden" name="acao" value="adicionar_usuario">
                
                <div class="form-group">
                    <label for="nome">Nome Completo:</label>
                    <input type="text" id="nome" name="nome" required placeholder="Nome completo do membro da família">
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required placeholder="email@exemplo.com">
                </div>
                
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required minlength="6" placeholder="Mínimo 6 caracteres">
                </div>
                
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha:</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6" placeholder="Confirme a senha">
                </div>
                
                <button type="submit" class="btn btn-primary">Adicionar à Conta Familiar</button>
            </form>
        </div>

        <!-- Lista de Usuários -->
        <div class="card">
            <h2>Membros da Conta Familiar</h2>
            
            <?php if (empty($usuarios)): ?>
                <p>Nenhum membro cadastrado na conta familiar.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Data de Cadastro</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['nome']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($u['data_criacao'])); ?></td>
                                <td>
                                    <?php if ($u['id'] == $usuario['id']): ?>
                                        <span class="badge badge-success">Você</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Membro</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['id'] != $usuario['id']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja remover este membro da conta familiar?')">
                                            <input type="hidden" name="acao" value="excluir_usuario">
                                            <input type="hidden" name="usuario_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Remover</button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: #666; font-size: 0.9rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Estatísticas -->
        <?php if (!empty($usuarios)): ?>
        <div class="card">
            <h2>Estatísticas da Conta</h2>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3><?php echo count($usuarios); ?></h3>
                    <p>Membros da Família</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo htmlspecialchars($conta['nome']); ?></h3>
                    <p>Nome da Conta</p>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo date('d/m/Y', strtotime($conta['data_criacao'])); ?></h3>
                    <p>Criada em</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Validação de senha em tempo real
        document.getElementById('confirmar_senha').addEventListener('input', function() {
            const senha = document.getElementById('senha').value;
            const confirmar = this.value;
            
            if (senha !== confirmar) {
                this.setCustomValidity('As senhas não coincidem');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 