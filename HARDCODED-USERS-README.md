# Sistema de Usuários Fixos (Hardcoded Users)

## Visão Geral

Este sistema implementa três usuários fixos (hardcoded) para autenticação no sistema do Portuga Restaurante, permitindo acesso rápido e sem necessidade de banco de dados para fins de demonstração e teste.

## Usuários Implementados

### 1. Cliente (Customer)
- **Email:** `customer@test`
- **Senha:** `customertest`
- **Tipo:** Cliente
- **Acesso:** Página principal do restaurante
- **Redirecionamento:** `/index.html`

### 2. Garçom (Waiter)
- **Email:** `waiter@test`
- **Senha:** `waitertest`
- **Tipo:** Garçom/Atendente
- **Acesso:** Sistema de pedidos
- **Redirecionamento:** `/pedidos.html`

### 3. Administrador (Admin)
- **Email:** `admin@test`
- **Senha:** `admintest`
- **Tipo:** Administrador
- **Acesso:** Painel administrativo completo
- **Redirecionamento:** `/admin.html`

## Características Técnicas

### Arquitetura

O sistema de usuários fixos foi implementado com as seguintes características:

1. **Separação de Lógica**: Código isolado em `config/hardcoded-users.php`
2. **Prioridade de Autenticação**: Usuários fixos são verificados antes da consulta ao banco de dados
3. **Sessões Independentes**: Sessões de usuários fixos não são armazenadas no banco de dados
4. **IDs Negativos**: Usuários fixos usam IDs negativos (-1, -2, -3) para distinção dos usuários do banco

### Fluxo de Autenticação

```
1. Usuário submete credenciais via login.html
2. API login.php recebe as credenciais
3. Sistema verifica primeiro os usuários fixos
4. Se encontrado: autentica e redireciona conforme o tipo
5. Se não encontrado: procede com autenticação via banco de dados
```

### Segurança

- **Comparação Direta**: Para usuários fixos, usa comparação direta de strings (apropriado apenas para ambiente de teste)
- **Sessões Seguras**: Tokens de sessão gerados com `random_bytes(32)`
- **Expiração**: Sessões expiram em 1 dia (padrão) ou 30 dias (com "lembrar-me")
- **Isolamento**: Usuários fixos não afetam o sistema de rate limiting do banco de dados

### Arquivos Modificados

1. **`config/hardcoded-users.php`** (NOVO)
   - Configuração dos usuários fixos
   - Funções de autenticação
   - Mapeamento de redirecionamentos

2. **`api/auth/login.php`** (MODIFICADO)
   - Adicionada verificação de usuários fixos
   - Integração com sistema de sessões
   - Retorno de informações de redirecionamento

3. **`includes/session.php`** (MODIFICADO)
   - Nova função `createSessionForHardcodedUser()`
   - Modificação de `isLoggedIn()` para suportar sessões de usuários fixos
   - Tratamento especial para sessões sem banco de dados

4. **`auth.js`** (MODIFICADO)
   - Suporte a redirecionamento dinâmico baseado em `redirect_url`
   - Armazenamento de informações de tipo de usuário no localStorage

## Como Usar

### 1. Login via Interface Web

1. Acesse a página de login: `https://seu-site.com/login.html`
2. Digite uma das credenciais listadas acima
3. Clique em "Entrar"
4. Será redirecionado automaticamente para a página apropriada

### 2. Login via API

```bash
curl -X POST https://seu-site.com/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@test",
    "password": "admintest",
    "remember_me": false,
    "csrf_token": "token_gerado"
  }'
```

**Resposta de Sucesso:**
```json
{
  "success": true,
  "message": "Login successful!",
  "user": {
    "id": -3,
    "full_name": "Administrador Teste",
    "email": "admin@test",
    "email_verified": true,
    "user_type": "admin",
    "role": "Admin"
  },
  "session_token": "abc123...",
  "redirect_url": "/admin.html"
}
```

## Testes

### Executar Testes Automatizados

```bash
# Testar configuração de usuários fixos
php test-hardcoded-users.php

# Testar API de login
php test-login-api.php
```

### Casos de Teste Implementados

1. ✅ Autenticação de cliente (customer@test)
2. ✅ Autenticação de garçom (waiter@test)
3. ✅ Autenticação de admin (admin@test)
4. ✅ Rejeição de senha incorreta
5. ✅ Rejeição de usuário não existente
6. ✅ Verificação de emails de usuários fixos
7. ✅ Redirecionamento baseado em tipo de usuário
8. ✅ Criação de sessão para usuários fixos

## Integração com Sistema Existente

### Compatibilidade

- ✅ Não interfere com autenticação de banco de dados
- ✅ Mantém sistema de OAuth funcionando
- ✅ Preserva rate limiting para usuários do banco
- ✅ Compatible com sistema de permissões existente

### Prioridade de Autenticação

```
1. Usuários Fixos (Hardcoded)
   ↓ (se não encontrado)
2. Usuários do Banco de Dados
   ↓ (se não encontrado)
3. OAuth (Google, Facebook, Instagram)
```

## Configuração Avançada

### Adicionar Novos Usuários Fixos

Edite o arquivo `config/hardcoded-users.php`:

```php
function getHardcodedUsers() {
    return [
        // ... usuários existentes ...
        
        // Novo usuário
        [
            'email' => 'novousuario@test',
            'password' => 'senhasegura',
            'full_name' => 'Novo Usuário',
            'user_type' => 'custom',
            'role' => 'Role Personalizada',
            'email_verified' => true,
            'is_active' => true,
            'id' => -4  // Use ID negativo único
        ]
    ];
}
```

### Modificar Redirecionamentos

Edite a função `getRedirectUrlForUserType()` em `config/hardcoded-users.php`:

```php
function getRedirectUrlForUserType($userType) {
    switch ($userType) {
        case 'admin':
            return '/admin.html';
        case 'waiter':
            return '/pedidos.html';
        case 'custom':
            return '/custom-page.html';  // Novo redirecionamento
        case 'customer':
        default:
            return '/index.html';
    }
}
```

## Considerações de Segurança

### Para Produção

⚠️ **IMPORTANTE**: Este sistema é projetado para **demonstração e teste**.

Para uso em produção:

1. **Remova ou Desabilite Usuários Fixos**
   ```php
   // Em config/hardcoded-users.php
   function getHardcodedUsers() {
       // Retornar array vazio em produção
       if (getenv('APP_ENV') === 'production') {
           return [];
       }
       return [/* usuários de teste */];
   }
   ```

2. **Use Hash de Senhas**
   ```php
   // Mesmo para usuários fixos, use hashing
   'password_hash' => password_hash('customertest', PASSWORD_BCRYPT)
   ```

3. **Implemente Senhas Fortes**
   - Mínimo 12 caracteres
   - Combinação de letras, números e símbolos
   - Não use palavras de dicionário

4. **Configure Rate Limiting**
   - Aplique rate limiting também para usuários fixos
   - Limite tentativas por IP

### Para Desenvolvimento

✅ **Recomendado**:
- Mantenha usuários fixos apenas em ambiente de desenvolvimento
- Use variáveis de ambiente para controlar disponibilidade
- Documente claramente quais são os usuários de teste

## Solução de Problemas

### Login não funciona

1. Verifique se o arquivo `config/hardcoded-users.php` existe
2. Confirme que as credenciais estão corretas (case-sensitive)
3. Verifique logs de erro do PHP
4. Teste com o script `test-hardcoded-users.php`

### Redirecionamento incorreto

1. Verifique a função `getRedirectUrlForUserType()`
2. Confirme que as páginas de destino existem
3. Verifique o console do navegador para erros JavaScript

### Sessão não persiste

1. Verifique se sessões PHP estão habilitadas
2. Confirme permissões do diretório de sessões
3. Teste a função `createSessionForHardcodedUser()`

## Manutenção

### Logs e Monitoramento

```php
// Em desenvolvimento, você pode adicionar logs:
error_log("Hardcoded user authenticated: " . $email);
```

### Limpeza de Sessões

Sessões de usuários fixos são armazenadas apenas em `$_SESSION` e não persistem no banco de dados, portanto são automaticamente limpas quando o navegador é fechado.

## Contribuindo

Para melhorar este sistema:

1. Mantenha a separação de lógica em `config/hardcoded-users.php`
2. Documente quaisquer mudanças
3. Adicione testes para novos recursos
4. Preserve compatibilidade com sistema de banco de dados

## Licença

Este código faz parte do sistema Portuga Restaurante e está sujeito às mesmas condições de licença do projeto principal.

---

**Desenvolvido com ❤️ para Portuga Restaurante**
