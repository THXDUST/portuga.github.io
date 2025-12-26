# Sistema de Autenticação Completo - Portuga Restaurante

## Índice
1. [Visão Geral](#visão-geral)
2. [Recursos](#recursos)
3. [Requisitos](#requisitos)
4. [Instalação](#instalação)
5. [Configuração](#configuração)
6. [Estrutura do Banco de Dados](#estrutura-do-banco-de-dados)
7. [Configuração OAuth](#configuração-oauth)
8. [Uso da API](#uso-da-api)
9. [Segurança](#segurança)
10. [Troubleshooting](#troubleshooting)
11. [Deployment no InfinityFree](#deployment-no-infinityfree)

## Visão Geral

Sistema completo de autenticação para o restaurante Portuga, implementando:
- ✅ Registro e login com email/senha
- ✅ Autenticação OAuth (Google, Facebook, Instagram)
- ✅ Dupla encriptação de senhas (bcrypt + HMAC-SHA256)
- ✅ Proteção contra SQL Injection
- ✅ Proteção CSRF
- ✅ Rate limiting para prevenir força bruta
- ✅ Verificação de email
- ✅ Gerenciamento de sessões seguras

## Recursos

### Segurança Implementada
- **Dupla Encriptação**: Senhas são primeiro criptografadas com bcrypt (cost 12) e depois com HMAC-SHA256
- **PDO com Prepared Statements**: Proteção completa contra SQL Injection
- **CSRF Tokens**: Tokens únicos para cada sessão
- **Rate Limiting**: Máximo de 5 tentativas de login em 15 minutos
- **Validação de Senha**: Mínimo 8 caracteres, com letra maiúscula, minúscula e número
- **Sessões Seguras**: Tokens de sessão armazenados no banco de dados com expiração

### Funcionalidades
- Registro de usuários com validação em tempo real
- Login com email e senha
- Login social (Google, Facebook, Instagram)
- Verificação de email obrigatória
- Indicador de força de senha
- Mensagens de erro amigáveis
- Design responsivo

## Requisitos

### Servidor
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Apache com mod_rewrite (opcional)
- Extensões PHP necessárias:
  - PDO
  - pdo_mysql
  - mbstring
  - openssl
  - curl (para OAuth)

### Desenvolvimento
- Navegador moderno (Chrome, Firefox, Safari, Edge)
- Editor de código (VS Code, Sublime Text, etc.)

## Instalação

### 1. Clone ou faça upload dos arquivos

```bash
# Se usando Git
git clone <seu-repositorio>
cd test-portuga.github.io

# Ou faça upload manual via FTP
```

### 2. Configure o Banco de Dados

```bash
# Acesse o MySQL
mysql -u seu_usuario -p

# Importe o schema
mysql -u seu_usuario -p portuga_db < database/setup.sql
```

Ou use o phpMyAdmin:
1. Crie um banco de dados chamado `portuga_db`
2. Importe o arquivo `database/setup.sql`

### 3. Configure as Variáveis de Ambiente

```bash
# Copie o arquivo de exemplo
cp .env.example .env

# Edite o arquivo .env com suas credenciais
nano .env  # ou use seu editor preferido
```

### 4. Configure as Permissões

```bash
# Permissões recomendadas para InfinityFree
chmod 755 api/
chmod 755 config/
chmod 755 includes/
chmod 644 config/database.php
chmod 644 includes/*.php
chmod 644 api/auth/*.php
```

### 5. Gere Chaves de Segurança

```bash
# Use OpenSSL para gerar chaves aleatórias
openssl rand -hex 32  # Para ENCRYPTION_KEY
openssl rand -hex 32  # Para CSRF_SECRET
```

Adicione essas chaves no arquivo `.env`.

## Configuração

### Arquivo .env

Configure todas as variáveis no arquivo `.env`:

```env
# Database
DB_HOST=localhost
DB_NAME=portuga_db
DB_USER=seu_usuario
DB_PASS=sua_senha

# Security Keys (geradas com openssl)
ENCRYPTION_KEY=sua_chave_de_64_caracteres_aqui
CSRF_SECRET=outra_chave_de_64_caracteres_aqui

# OAuth - Configure após criar apps nas plataformas
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
FACEBOOK_APP_ID=...
FACEBOOK_APP_SECRET=...
INSTAGRAM_CLIENT_ID=...
INSTAGRAM_CLIENT_SECRET=...
```

### Configuração do PHP

Se seu hosting permitir, ajuste o `php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
display_errors = Off
log_errors = On
```

## Estrutura do Banco de Dados

### Tabela: users

Armazena informações dos usuários.

```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(512) NULL,  -- NULL para usuários OAuth
    oauth_provider ENUM('none', 'google', 'facebook', 'instagram'),
    oauth_id VARCHAR(255) NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE
);
```

### Tabela: login_attempts

Rastreia tentativas de login para rate limiting.

```sql
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    success BOOLEAN DEFAULT FALSE
);
```

### Tabela: sessions

Gerencia sessões ativas dos usuários.

```sql
CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Tabela: password_resets

Gerencia tokens de recuperação de senha.

```sql
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    reset_token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE
);
```

## Configuração OAuth

### Google OAuth

1. Acesse [Google Cloud Console](https://console.cloud.google.com/)
2. Crie um novo projeto ou selecione um existente
3. Vá para "APIs & Services" > "Credentials"
4. Clique em "Create Credentials" > "OAuth client ID"
5. Escolha "Web application"
6. Configure:
   - **Authorized JavaScript origins**: `https://seu-site.com`
   - **Authorized redirect URIs**: `https://seu-site.com/api/auth/oauth-callback.php?provider=google`
7. Copie Client ID e Client Secret para o `.env`

### Facebook OAuth

1. Acesse [Facebook Developers](https://developers.facebook.com/)
2. Crie um novo app ou use um existente
3. Adicione o produto "Facebook Login"
4. Configure:
   - **Valid OAuth Redirect URIs**: `https://seu-site.com/api/auth/oauth-callback.php?provider=facebook`
5. Copie App ID e App Secret para o `.env`

### Instagram OAuth

1. Acesse [Facebook Developers](https://developers.facebook.com/) (Instagram usa Facebook)
2. Crie um app com Instagram Basic Display
3. Configure:
   - **Valid OAuth Redirect URIs**: `https://seu-site.com/api/auth/oauth-callback.php?provider=instagram`
4. Copie Client ID e Client Secret para o `.env`

**Importante**: Atualize os Client IDs no arquivo `auth.js`:

```javascript
const clientIds = {
    google: 'seu_google_client_id',
    facebook: 'seu_facebook_app_id',
    instagram: 'seu_instagram_client_id'
};
```

## Uso da API

### Registro de Usuário

**Endpoint**: `POST /api/auth/register.php`

**Request**:
```json
{
    "full_name": "João Silva",
    "email": "joao@example.com",
    "password": "Senha123!",
    "confirm_password": "Senha123!",
    "terms_accepted": true,
    "csrf_token": "token_gerado"
}
```

**Response Success**:
```json
{
    "success": true,
    "message": "Registration successful! Please check your email to verify your account.",
    "user_id": 1,
    "verification_required": true
}
```

**Response Error**:
```json
{
    "success": false,
    "message": "An account with this email already exists"
}
```

### Login de Usuário

**Endpoint**: `POST /api/auth/login.php`

**Request**:
```json
{
    "email": "joao@example.com",
    "password": "Senha123!",
    "remember_me": true,
    "csrf_token": "token_gerado"
}
```

**Response Success**:
```json
{
    "success": true,
    "message": "Login successful!",
    "user": {
        "id": 1,
        "full_name": "João Silva",
        "email": "joao@example.com",
        "email_verified": true
    },
    "session_token": "token_da_sessao"
}
```

### Logout de Usuário

**Endpoint**: `POST /api/auth/logout.php`

**Response**:
```json
{
    "success": true,
    "message": "Logout successful"
}
```

### Verificação de Email

**Endpoint**: `GET /api/auth/verify-email.php?token=TOKEN`

Redireciona para `/login.html?verified=success` após verificação.

## Segurança

### Dupla Encriptação de Senhas

As senhas passam por dois níveis de encriptação:

```php
// Primeira camada: bcrypt
$bcryptHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Segunda camada: HMAC-SHA256
$hmacHash = hash_hmac('sha256', $bcryptHash . $email, ENCRYPTION_KEY);

// Formato final armazenado
$finalHash = $bcryptHash . ':' . $hmacHash;
```

### Rate Limiting

- **5 tentativas** de login por email/IP
- Bloqueio por **15 minutos** após exceder o limite
- Limpeza automática de tentativas antigas

### CSRF Protection

Todos os formulários incluem tokens CSRF:

```php
// Geração
$token = generateCSRFToken();

// Validação
if (!validateCSRFToken($token)) {
    throw new Exception('Invalid CSRF token');
}
```

### SQL Injection Protection

Todas as queries usam PDO com prepared statements:

```php
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
```

### Session Security

- Tokens únicos por sessão
- Expiração automática
- Renovação periódica do session ID
- HttpOnly e Secure cookies (em HTTPS)

## Troubleshooting

### Erro: "Database connection failed"

**Solução**:
1. Verifique as credenciais no `.env`
2. Confirme que o banco de dados existe
3. Verifique se o usuário tem permissões corretas

```sql
GRANT ALL PRIVILEGES ON portuga_db.* TO 'seu_usuario'@'localhost';
FLUSH PRIVILEGES;
```

### Erro: "Failed to fetch CSRF token"

**Solução**:
1. Verifique se o arquivo `api/auth/get-csrf-token.php` existe
2. Confirme que as sessões PHP estão funcionando
3. Verifique permissões do diretório de sessões

### OAuth não funciona

**Solução**:
1. Verifique se os Client IDs estão corretos no `.env` e `auth.js`
2. Confirme que as URLs de redirect estão configuradas corretamente
3. Verifique se o HTTPS está ativo (necessário para OAuth)
4. Verifique se a extensão cURL está habilitada no PHP

### Email de verificação não é enviado

**Solução**:
No momento, o sistema registra o link de verificação no log de erros do PHP. Para implementar envio real:

1. Configure as credenciais SMTP no `.env`
2. Implemente a função `sendVerificationEmail()` em `api/auth/register.php`
3. Use uma biblioteca como PHPMailer

### Sessão expira muito rápido

**Solução**:
Ajuste o tempo de expiração em `includes/session.php`:

```php
$expiresIn = $rememberMe ? 30 : 1; // dias
```

## Deployment no InfinityFree

### Passo 1: Criar Conta

1. Registre-se em [InfinityFree](https://infinityfree.net/)
2. Crie um novo site
3. Anote as credenciais MySQL fornecidas

### Passo 2: Upload dos Arquivos

Via FTP:
```
Host: ftpupload.net
Username: seu_username_infinityfree
Password: sua_senha
Port: 21
```

Upload todos os arquivos para `htdocs/`.

### Passo 3: Configurar Banco de Dados

1. Acesse o painel do InfinityFree
2. Vá para "MySQL Databases"
3. Crie um banco de dados
4. Use phpMyAdmin para importar `database/setup.sql`

### Passo 4: Configurar .env

Crie o arquivo `.env` com as credenciais do InfinityFree:

```env
DB_HOST=sql123.infinityfree.com
DB_NAME=if0_xxxxx_portuga
DB_USER=if0_xxxxx
DB_PASS=sua_senha_mysql
```

### Passo 5: Configurar Permissões

No InfinityFree, as permissões geralmente são:
- Diretórios: 755
- Arquivos PHP: 644

### Passo 6: Testar

1. Acesse `https://seu-site.infinityfreeapp.com/register.html`
2. Tente criar uma conta
3. Verifique os logs de erro se algo falhar

### Limitações do InfinityFree

- **Email**: Pode ter limitações no envio de emails (use serviços externos como SendGrid)
- **Cron Jobs**: Não disponível (limpeza automática pode não funcionar)
- **Performance**: Pode ser mais lenta que hosting pago
- **SSL**: Disponível gratuitamente

### Recomendações

1. **Backup Regular**: Faça backup do banco de dados regularmente
2. **Monitoramento**: Verifique os logs regularmente
3. **Teste**: Teste todas as funcionalidades após deployment
4. **SSL**: Ative SSL gratuito no painel do InfinityFree
5. **Domínio Personalizado**: Considere usar um domínio próprio

## Manutenção

### Limpeza de Dados Antigos

Execute periodicamente (ou configure como cron job):

```sql
-- Limpar tentativas de login antigas (>30 dias)
DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Limpar sessões expiradas
DELETE FROM sessions WHERE expires_at < NOW();

-- Limpar tokens de reset expirados
DELETE FROM password_resets WHERE expires_at < NOW();
```

### Monitoramento

Verifique regularmente:
- Logs de erro do PHP
- Tentativas de login falhadas
- Usuários registrados
- Sessões ativas

### Atualizações

1. Faça backup antes de atualizar
2. Teste em ambiente local primeiro
3. Atualize gradualmente
4. Monitore após atualização

## Suporte

Para problemas ou dúvidas:
1. Verifique a seção Troubleshooting
2. Revise os logs de erro
3. Consulte a documentação do PHP/MySQL
4. Crie uma issue no repositório

## Licença

Este projeto é de código aberto. Sinta-se livre para usar e modificar conforme necessário.

---

**Desenvolvido com ❤️ para Portuga Restaurante**
