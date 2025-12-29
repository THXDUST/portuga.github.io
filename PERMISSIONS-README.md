# Sistema de Permissões - Documentação

## Visão Geral

Este documento descreve o sistema de permissões implementado no Restaurante Portuga para controlar o acesso às funcionalidades do painel administrativo.

## Estrutura do Sistema

### 1. Permissões (Permissions)

Permissões são ações específicas que um usuário pode realizar no sistema. Cada permissão tem:
- **Nome**: Identificador único (ex: `order_view`, `menu_create`)
- **Descrição**: Descrição legível da permissão
- **Recurso**: Tipo de recurso (ex: `orders`, `menu`, `users`)
- **Ação**: Tipo de ação (ex: `read`, `create`, `update`, `delete`)

#### Permissões Disponíveis:

##### Acesso Geral
- `admin_panel_access` - Acesso ao painel administrativo

##### Pedidos
- `order_view` - Visualizar pedidos
- `order_create` - Criar pedidos
- `order_update` - Atualizar pedidos
- `order_delete` - Deletar pedidos
- `orders_status` - Mudança de estado dos pedidos

##### Cardápio
- `menu_view` - Visualizar cardápio
- `menu_create` - Criar itens do cardápio
- `menu_update` - Atualizar cardápio
- `menu_delete` - Deletar itens do cardápio

##### Usuários
- `user_view` - Visualizar usuários
- `user_create` - Criar usuários
- `user_update` - Atualizar usuários
- `user_delete` - Deletar usuários
- `users_management` - Gerenciar usuários

##### Permissões e Cargos
- `permissions_management` - Gerenciar permissões
- `roles_management` - Gerenciar cargos/roles

##### Relatórios
- `report_view` - Visualizar relatórios
- `report_create` - Criar relatórios
- `reports_access` - Acesso aos relatórios
- `financial_stats` - Acesso às estatísticas financeiras

##### Configurações
- `settings_view` - Visualizar configurações
- `settings_update` - Atualizar configurações
- `settings_access` - Acesso às configurações do sistema

##### Currículos
- `resume_view` - Visualizar currículos
- `resume_update` - Atualizar status de currículos
- `resumes_access` - Acesso aos currículos

##### Ouvidoria
- `ouvidoria_view` - Visualizar ouvidoria
- `ouvidoria_update` - Responder ouvidoria
- `ouvidoria_access` - Acesso à ouvidoria

### 2. Cargos (Roles)

Cargos são conjuntos de permissões agrupadas logicamente. O sistema vem com cargos pré-definidos:

#### Admin
- Possui todas as permissões
- Acesso irrestrito ao sistema

#### Gerente
- Permissões de visualização e gerenciamento
- Acesso a relatórios financeiros
- Gerenciamento de cardápio
- Visualização de pedidos

#### Atendente
- Permissões básicas de operação
- Visualizar e atualizar pedidos
- Visualizar cardápio
- Acesso ao painel admin

#### Cozinha
- Acesso limitado aos pedidos
- Apenas visualização e mudança de status

#### Entregador
- Acesso aos pedidos para entrega
- Visualização limitada

### 3. Usuários

#### Usuários Hardcoded

O sistema possui usuários pré-configurados para facilitar os testes:

| Email | Senha | Cargo | User Type | ID |
|-------|-------|-------|-----------|-----|
| admin@test | admintest | Admin | admin | -3 |
| waiter@test | waitertest | Atendente | waiter | -2 |
| customer@test | customertest | Cliente | customer | -1 |

**Nota:** Usuários hardcoded têm IDs negativos e são identificados com um badge especial na interface.

#### Usuários do Banco de Dados

Usuários criados através do sistema de registro ou pelo painel admin são armazenados no banco de dados e podem ter múltiplos cargos atribuídos.

## APIs de Gerenciamento

### Permissões API (`/api/admin/permissions.php`)

#### Listar Permissões
```
GET /api/admin/permissions.php?action=list
GET /api/admin/permissions.php?action=by-resource (agrupado por recurso)
GET /api/admin/permissions.php?action=by-role&role_id=X
```

#### Criar Permissão
```
POST /api/admin/permissions.php?action=create
Body: {
  "name": "permission_name",
  "description": "Descrição",
  "resource": "resource_type",
  "action": "action_type"
}
```

#### Atualizar Permissão
```
PUT /api/admin/permissions.php?action=update
Body: {
  "id": 1,
  "name": "permission_name",
  "description": "Descrição",
  "resource": "resource_type",
  "action": "action_type"
}
```

#### Deletar Permissão
```
DELETE /api/admin/permissions.php?action=delete&id=X
```

### Cargos API (`/api/admin/roles.php`)

#### Listar Cargos
```
GET /api/admin/roles.php?action=list
GET /api/admin/roles.php?action=get&id=X
GET /api/admin/roles.php?action=user-roles&user_id=X
```

#### Criar Cargo
```
POST /api/admin/roles.php?action=create
Body: {
  "name": "Nome do Cargo",
  "description": "Descrição",
  "permission_ids": [1, 2, 3]
}
```

#### Atualizar Cargo
```
PUT /api/admin/roles.php?action=update
Body: {
  "id": 1,
  "name": "Nome do Cargo",
  "description": "Descrição"
}
```

#### Atribuir Cargo a Usuário
```
POST /api/admin/roles.php?action=assign-user
Body: {
  "user_id": 1,
  "role_id": 2
}
```

### Usuários API (`/api/admin/users.php`)

#### Listar Usuários
```
GET /api/admin/users.php?action=list
GET /api/admin/users.php?action=get&id=X
```

#### Criar Usuário
```
POST /api/admin/users.php?action=create
Body: {
  "full_name": "Nome Completo",
  "email": "email@example.com",
  "password": "senha123",
  "role_ids": [1, 2]
}
```

#### Atualizar Usuário
```
PUT /api/admin/users.php?action=update
Body: {
  "id": 1,
  "full_name": "Nome Completo",
  "email": "email@example.com",
  "is_active": true
}
```

#### Alterar Senha
```
PUT /api/admin/users.php?action=change-password
Body: {
  "id": 1,
  "password": "nova_senha"
}
```

### Informações do Usuário (`/api/auth/get-user-info.php`)

Retorna informações do usuário atual incluindo permissões:

```
GET /api/auth/get-user-info.php

Response: {
  "success": true,
  "data": {
    "id": 1,
    "full_name": "Nome do Usuário",
    "email": "email@example.com",
    "isLoggedIn": true,
    "isHardcoded": false,
    "permissions": [...],
    "permissionMap": {...},
    "roles": [...],
    "hasAdminAccess": true
  }
}
```

## Frontend - Verificação de Permissões

### Funções JavaScript

#### `hasPermission(permissionName)`
Verifica se o usuário atual tem uma permissão específica.

```javascript
if (hasPermission('order_create')) {
    // Mostrar botão de criar pedido
}
```

#### `hasAdminAccess()`
Verifica se o usuário tem acesso ao painel admin.

```javascript
if (hasAdminAccess()) {
    // Mostrar link do admin
}
```

#### `fetchUserInfo()`
Busca informações atualizadas do usuário do servidor.

```javascript
const userInfo = await fetchUserInfo();
console.log(userInfo.permissions);
```

### Filtros de Interface

O sistema automaticamente:
- Oculta o botão "Login" quando o usuário está logado
- Mostra o nome do usuário e botão "Sair"
- Oculta o link "Admin" para usuários sem permissão
- Filtra abas do admin baseado nas permissões do usuário
- Mostra mensagem "Acesso Negado" em tabs restritos

## Banco de Dados

### Estrutura de Tabelas

#### `permissions`
```sql
CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    resource VARCHAR(50) NOT NULL,
    action VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### `roles`
```sql
CREATE TABLE roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `role_permissions`
```sql
CREATE TABLE role_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id)
);
```

#### `user_roles`
```sql
CREATE TABLE user_roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_role (user_id, role_id)
);
```

## Migrações

Para adicionar o sistema de permissões a um banco de dados existente:

```bash
# Execute a migração
mysql -u username -p database_name < database/migrations/add_table_number_and_permissions.sql
```

## Sistema de Pedidos com Mesa

### Funcionalidade de Mesa

#### Parâmetro ?mesa=X
Pedidos podem ser feitos sem login usando o parâmetro de URL:
```
https://exemplo.com/carrinho.html?mesa=5
```

Comportamento:
- Não exige login
- Desabilita opção de entrega
- Mostra banner "Pedido para Mesa X"
- Armazena número da mesa no pedido

#### Campo de Mesa para Usuários Logados
Usuários logados fazendo pedidos locais (não delivery) podem:
- Informar número da mesa manualmente
- Campo numérico (1-100)
- Validação obrigatória para pedidos no local

### Estrutura de Dados do Pedido

```javascript
{
  id: 123456789,
  date: "2024-01-01T12:00:00.000Z",
  items: [...],
  total: 50.00,
  status: "pendente",
  delivery: {
    forDelivery: false,
    tableNumber: 5,
    userId: 1,
    deliveryAddress: null,
    deliveryDistance: null,
    deliveryFee: 0,
    pickupTime: "13:00",
    paymentMethod: null
  }
}
```

### Visualização no Admin

O painel Kanban mostra:
- Badge visual para tipo de pedido (Mesa 🪑, Entrega 🚚, Retirada 📦)
- Filtro por tipo de pedido
- Filtro por número de mesa
- Borda colorida por tipo de pedido
- Informação do usuário (se disponível)

## Segurança

### Boas Práticas

1. **Validação no Backend**: Sempre valide permissões no servidor
2. **Tokens de Sessão**: Use tokens seguros para autenticação
3. **HTTPS**: Sempre use HTTPS em produção
4. **Rate Limiting**: Implemente rate limiting nas APIs
5. **Logs de Auditoria**: Registre todas as alterações de permissões
6. **Senhas Seguras**: Use hash bcrypt para senhas

### Logs de Auditoria

Todas as ações administrativas são registradas na tabela `admin_logs`:

```sql
SELECT * FROM admin_logs 
WHERE action = 'permission_update' 
ORDER BY created_at DESC;
```

## Troubleshooting

### Usuário Não Consegue Acessar o Admin

1. Verificar se tem permissão `admin_panel_access`
2. Verificar se está logado corretamente
3. Limpar cache do navegador e localStorage
4. Verificar console do navegador para erros

### Permissões Não Aparecem

1. Executar migração do banco de dados
2. Verificar se seeds foram executados
3. Verificar API `/api/auth/get-user-info.php`
4. Limpar localStorage e fazer login novamente

### Tabs Ocultas no Admin

1. Verificar permissões do usuário
2. Verificar role_permissions no banco
3. Verificar user_roles no banco
4. Console: `localStorage.getItem('userInfo')`

## Suporte

Para dúvidas ou problemas:
- Verificar console do navegador (F12)
- Verificar logs do servidor PHP
- Verificar tabela `admin_logs` no banco
- Contactar administrador do sistema
