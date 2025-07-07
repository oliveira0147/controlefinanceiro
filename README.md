# Sistema de Controle Financeiro Familiar

Sistema simples em PHP para controle financeiro pessoal/familiar com cartões de crédito, compras parceladas e salários.

## 🚀 Como Usar

### 1. Configuração Inicial
1. Copie os arquivos para seu servidor web (XAMPP, por exemplo)
2. Crie um banco MySQL chamado `controle_financeiro`
3. Execute o arquivo `database.sql` no seu banco
4. Copie `config/database.php.example` para `config/database.php`
5. Ajuste as configurações do banco no arquivo

### 2. Primeiro Acesso
- Acesse `http://localhost/ControleFinanceiro/`
- Crie sua conta familiar
- Faça login e comece a usar

## 📋 Funcionalidades

- **Contas Familiares**: Cada família tem sua conta isolada
- **Cartões de Crédito**: Cadastre seus cartões
- **Compras Parceladas**: Registre compras com parcelamento automático
- **Gerenciamento de Parcelas**: Marque parcelas como pagas
- **Salários**: Controle salários e benefícios
- **Dashboard**: Resumo financeiro mensal

## Dashboard e Responsabilidade Compartilhada

- O dashboard mostra os gastos individuais de cada membro da família, considerando a responsabilidade compartilhada definida em cada compra.
- O detalhamento e os totais exibidos são referentes ao **mês selecionado** no filtro do dashboard (campo "Mês/Ano").
- Se uma compra parcelada não tiver parcela para o mês selecionado, ela não aparecerá no detalhamento daquele mês.
- Para visualizar compras de outros meses, basta alterar o filtro de mês no topo do dashboard.
- Os cards de compras mostram todas as compras do cartão, independente do mês.

**Dica:** Para ver o quanto cada pessoa deve pagar em cada mês, altere o filtro de mês e veja o detalhamento atualizado.

## 🔧 Requisitos

- PHP 7.4+
- MySQL 5.7+
- Servidor web (Apache/Nginx)

## 📁 Estrutura

```
ControleFinanceiro/
├── config/          # Configurações do banco
├── includes/        # Funções do sistema
├── assets/          # CSS e recursos
├── database.sql     # Estrutura do banco
├── *.php           # Páginas do sistema
└── README.md       # Este arquivo
```

## 🛠️ Configuração Rápida

### XAMPP
1. Copie a pasta para `htdocs/`
2. Crie banco no phpMyAdmin
3. Execute `database.sql`
4. Configure `config/database.php`
5. Acesse no navegador

### Configuração do Banco
```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'controle_financeiro');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 📞 Suporte

Para dúvidas: natas18.oliveira@gmail.com

---

**Versão:** 1.3.0 | **Última atualização:** Julho 2025 