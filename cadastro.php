<?php
require_once __DIR__ . '/includes/functions.php';

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome_conta = trim($_POST['nome_conta']);
    $nome = trim($_POST['nome']);
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    if (empty($nome_conta) || empty($nome) || empty($email) || empty($senha) || empty($confirmar_senha)) {
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
            try {
                $pdo->beginTransaction();
                
                // Criar nova conta
                $conta_id = criarConta($nome_conta);
                
                // Criptografar senha
                $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                
                // Inserir novo usuário
                $stmt = $pdo->prepare("INSERT INTO usuarios (conta_id, nome, email, senha) VALUES (?, ?, ?, ?)");
                $stmt->execute([$conta_id, $nome, $email, $senha_hash]);
                
                $pdo->commit();
                $mensagem = 'Conta familiar criada com sucesso! Você já pode fazer login.';
                // Limpar campos após sucesso
                $nome_conta = $nome = $email = '';
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao criar conta: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro - Controle Financeiro</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Controle Financeiro Familiar</h1>
            <p>Criar nova conta familiar</p>
        </div>
    </div>

    <div class="container">
        <div class="login-container">
            <div class="card">
                <h2>Criar Conta Familiar</h2>
                
                <?php if ($mensagem): ?>
                    <div class="alert alert-success"><?php echo $mensagem; ?></div>
                <?php endif; ?>
                
                <?php if ($erro): ?>
                    <div class="alert alert-danger"><?php echo $erro; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="nome_conta">Nome da Conta Familiar:</label>
                        <input type="text" id="nome_conta" name="nome_conta" required value="<?php echo isset($_POST['nome_conta']) ? htmlspecialchars($_POST['nome_conta']) : ''; ?>" placeholder="Ex: Família Silva, Casa dos Santos...">
                    </div>
                    
                    <div class="form-group">
                        <label for="nome">Seu Nome Completo:</label>
                        <input type="text" id="nome" name="nome" required value="<?php echo isset($_POST['nome']) ? htmlspecialchars($_POST['nome']) : ''; ?>" placeholder="Seu nome completo">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Seu Email:</label>
                        <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="seu@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="senha">Senha:</label>
                        <input type="password" id="senha" name="senha" required minlength="6" placeholder="Mínimo 6 caracteres">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar Senha:</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha" required minlength="6" placeholder="Confirme sua senha">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Criar Conta Familiar</button>
                </form>
                
                <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <p style="color: #666; font-size: 0.9rem;">
                        Já possui uma conta? 
                        <a href="login.php" style="color: #667eea; text-decoration: none;">Faça login aqui</a>
                    </p>
                    <p style="color: #666; font-size: 0.8rem; margin-top: 0.5rem;">
                        <strong>Nota:</strong> Após criar a conta, você poderá adicionar outros membros da família através do sistema.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 