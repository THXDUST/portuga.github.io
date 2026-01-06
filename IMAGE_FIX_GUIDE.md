# Guia de Correção: Exibição de Imagens no Cardápio

## 🔍 Problema Identificado

As imagens dos itens do cardápio não estavam sendo exibidas, mesmo após o upload bem-sucedido.

## 🐛 Causas Raiz Encontradas

### 1. Tipo de Coluna Incorreto no Banco de Dados
- **Problema**: Coluna `image_data` era tipo `BYTEA` (binary)
- **Efeito**: PostgreSQL fazia encoding duplo dos dados Base64
- **Solução**: Alterado para tipo `TEXT` (as migrations fazem isso automaticamente)

### 2. Colunas de Imagem Não Incluídas nas Queries SQL
- **Problema**: Queries SQL não retornavam `image_data` e `image_mime_type`
- **Efeito**: Frontend não conseguia detectar se item tinha imagem
- **Solução**: Adicionadas colunas nas queries de `items` e `full-menu`

## ✅ Correções Aplicadas

### 1. Database Migrations
Criadas/atualizadas migrations em `database/migrations/`:
- `001_add_menu_item_image_columns.sql` - Cria colunas com tipo TEXT
- `002_fix_image_data_column_type.sql` - Converte BYTEA existente para TEXT

### 2. API Backend (`api/admin/menu.php`)
Atualizadas 2 queries SQL:
```sql
-- Query 'items' (linha ~237)
SELECT i.id, i.group_id, i.name, i.description, i.price, i.image_url,
       i.ingredients, i.is_available, i.display_order, i.created_at,
       i.image_data, i.image_mime_type,  -- ✅ ADICIONADO
       g.name as group_name
FROM menu_items i
INNER JOIN menu_groups g ON i.group_id = g.id

-- Query 'full-menu' (linha ~292)
SELECT id, group_id, name, description, price, image_url, 
       ingredients, is_available, display_order,
       image_data, image_mime_type  -- ✅ ADICIONADO
FROM menu_items
WHERE is_available = TRUE
```

## 🚀 Como Aplicar as Correções

### Método 1: Usar a Interface Web (Recomendado)
1. Acesse: `http://seu-dominio/run_migrations.html`
2. Se você é admin, apenas clique em "Executar Migrações"
3. Se não está logado, insira o `MIGRATIONS_TOKEN` (se configurado)
4. Aguarde a confirmação de sucesso

### Método 2: Via API Diretamente
```bash
# Se tiver um MIGRATIONS_TOKEN configurado
curl -X POST http://seu-dominio/api/admin/run_migrations.php \
  -H "X-Migrations-Token: SEU_TOKEN_AQUI" \
  -H "Content-Type: application/json"

# Se estiver autenticado como admin (com cookie de sessão)
curl -X POST http://seu-dominio/api/admin/run_migrations.php \
  -H "Content-Type: application/json" \
  --cookie "PHPSESSID=seu_session_id"
```

### Método 3: Via psql (Manualmente)
```bash
# Conectar ao banco
psql -h localhost -U postgres -d portuga_db

# Executar as migrations manualmente
\i database/migrations/001_add_menu_item_image_columns.sql
\i database/migrations/002_fix_image_data_column_type.sql
```

## 🔄 Fluxo Completo de Imagens (Após Correção)

### Upload de Imagem
1. **Frontend (`admin.js`)**: 
   - Usuário seleciona imagem no modal "Adicionar Item"
   - `compressImage()` comprime para ≤1200px, qualidade 80%
   - `saveItem()` envia via FormData com multipart/form-data

2. **Backend (`api/admin/menu.php`)**: 
   - `processImageUpload()` recebe arquivo
   - Valida tipo (JPEG/PNG/WebP) e tamanho (<5MB)
   - Redimensiona se necessário (max 1024px)
   - Converte para JPEG e comprime (80% quality)
   - **Codifica em Base64** e armazena como TEXT
   - Salva `image_data` (Base64) e `image_mime_type` (MIME type)

### Exibição de Imagem
1. **Frontend (`admin.js`)**:
   - `loadMenuManagement()` busca items via API
   - `renderMenuItem()` verifica se `item.image_data` existe
   - Se existir, cria tag `<img src="/api/dish-image.php?id={id}">`

2. **Backend (`api/dish-image.php`)**:
   - Recebe `id` do item
   - Busca `image_data` e `image_mime_type` do banco
   - **Decodifica Base64** para binário
   - Envia headers HTTP corretos (Content-Type, Cache-Control)
   - Retorna dados binários da imagem

## 📊 Estrutura da Tabela (Após Migrations)

```sql
ALTER TABLE menu_items ADD COLUMN image_data TEXT;
ALTER TABLE menu_items ADD COLUMN image_mime_type VARCHAR(100);

-- image_data: Base64-encoded string (não binário)
-- image_mime_type: 'image/jpeg', 'image/png', etc.
```

## 🧪 Como Testar

1. **Aplicar as migrations** (via run_migrations.html)
2. **Fazer login no admin** (http://seu-dominio/admin.html)
3. **Ir para aba "Cardápio"**
4. **Clicar em "Adicionar Item"**
5. **Preencher dados e fazer upload de uma imagem**
6. **Salvar o item**
7. **Verificar se a imagem aparece** na lista do cardápio

## 🔍 Troubleshooting

### Imagem não aparece após upload
1. Verifique console do navegador (F12) para erros
2. Verifique se migrations foram aplicadas: 
   ```sql
   SELECT version FROM schema_migrations ORDER BY version;
   ```
3. Verifique se `image_data` foi salvo:
   ```sql
   SELECT id, name, 
          CASE WHEN image_data IS NULL THEN 'NULL' ELSE 'EXISTS' END as image_status
   FROM menu_items;
   ```

### Erro 404 ao acessar imagem
- Verifique se `api/dish-image.php` existe
- Verifique permissões do arquivo (deve ser legível pelo servidor web)

### Erro de encoding/decoding
- Verifique se coluna é tipo TEXT (não BYTEA)
- Verifique logs PHP para erros de base64_decode

## 📝 Arquivos Modificados

```
✅ database/migrations/001_add_menu_item_image_columns.sql (atualizado)
✅ database/migrations/002_fix_image_data_column_type.sql (novo)
✅ api/admin/menu.php (queries SQL atualizadas)
```

## 🔐 Segurança

- Imagens são validadas no backend (tipo e tamanho)
- Imagens são processadas e recodificadas (previne exploits)
- Tamanho máximo: 5MB
- Tipos permitidos: JPEG, PNG, WebP
- Compressão automática para economia de espaço

## 📚 Documentação Relacionada

- Processo de upload: Ver função `processImageUpload()` em `api/admin/menu.php`
- Compressão frontend: Ver função `compressImage()` em `admin.js`
- Serving de imagens: Ver `api/dish-image.php`
