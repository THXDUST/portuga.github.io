# 🔍 Diagnóstico e Correção: Imagens Caindo no Fallback

## ✅ Correções Aplicadas

### 1. Validação Aprimorada em `dish-image.php`

**Problema Identificado:**
- Validação fraca de `image_data` poderia permitir strings vazias ou base64 inválido
- `base64_decode()` em modo não-estrito poderia aceitar dados corrompidos
- Falta de verificação de tamanho após decodificação

**Solução Implementada:**
```php
// ANTES
if ($item['image_data']) {
    $imageData = base64_decode($item['image_data']);
    if ($imageData === false) {
        $imageData = $item['image_data'];  // Perigoso!
    }
    // ...
}

// DEPOIS  
if (!empty($item['image_data']) && trim($item['image_data']) !== '') {
    $imageData = base64_decode($item['image_data'], true); // Modo estrito
    
    if ($imageData !== false && strlen($imageData) > 0) {
        // Servir imagem válida
    } else {
        serveDefaultImage(); // Fallback para dados inválidos
    }
}
```

**Melhorias:**
- ✅ Usa `empty()` e `trim()` para detectar strings vazias/espaços
- ✅ `base64_decode($data, true)` em modo estrito rejeita base64 inválido
- ✅ Verifica `strlen($imageData) > 0` antes de servir
- ✅ Remove fallback perigoso que serviria base64 como imagem
- ✅ Validação adicional para `image_url`

## 🔧 Ferramentas de Diagnóstico Criadas

### 1. `/api/test-image-diagnostics.php`
**Endpoint JSON completo de diagnóstico**

**Como usar:**
```bash
# Testar item específico (padrão: ID=1)
curl https://restauranteportugaservertest.onrender.com/api/test-image-diagnostics.php

# Testar outro ID
curl https://restauranteportugaservertest.onrender.com/api/test-image-diagnostics.php?test_id=5
```

**O que verifica:**
- ✅ Conexão com banco de dados
- ✅ Existência da tabela `menu_items`
- ✅ Existência e tipo das colunas `image_data` e `image_mime_type`
- ✅ Contagem de itens totais vs itens com imagens
- ✅ Lista dos primeiros 20 itens com status de imagem
- ✅ Teste específico de um item (decodifica e valida base64)
- ✅ Detecção de formato de imagem (JPEG/PNG via magic bytes)
- ✅ Recomendações automáticas baseadas nos problemas encontrados

**Exemplo de resposta:**
```json
{
    "timestamp": "2026-01-06 20:45:00",
    "database": {
        "connected": true,
        "error": null
    },
    "schema": {
        "menu_items_exists": true,
        "image_data_column_exists": true,
        "image_data_column_type": "text",
        "image_mime_type_column_exists": true
    },
    "data": {
        "total_items": 5,
        "items_with_images": 3,
        "items_list": [...]
    },
    "test_item": {
        "id": 1,
        "name": "Bacalhau à Portuguesa",
        "has_image_data": true,
        "image_data_size": 45230,
        "image_decode_success": true,
        "image_decoded_size": 33845,
        "image_format": "JPEG",
        "test_url": "/api/dish-image.php?id=1",
        "expected_result": "Should display the image"
    },
    "recommendations": [
        "Everything looks good! If images still not showing, check browser console for errors."
    ]
}
```

### 2. `/debug_images.php`
**Interface HTML amigável para diagnóstico**

**Como usar:**
```
https://restauranteportugaservertest.onrender.com/debug_images.php
```

**O que mostra:**
- 📊 Lista completa de todos os itens
- 🖼️ Status de imagem para cada item
- 🔍 Análise específica do Item ID=1
- ⚠️ Alertas sobre problemas detectados
- 💡 Sugestões de correção

## 🎯 Cenários Possíveis e Soluções

### Cenário 1: Colunas de Imagem Não Existem
**Sintomas:**
- Diagnóstico mostra `"image_data_column_exists": false`
- Erro ao fazer upload de imagens

**Solução:**
```bash
# Via interface web (recomendado)
# Acesse: https://restauranteportugaservertest.onrender.com/run_migrations.html
# Clique em "Executar Migrações"

# Via psql (alternativo)
psql -h <host> -U <user> -d <database> -f database/migrations/001_add_menu_item_image_columns.sql
```

### Cenário 2: Tipo de Coluna Incorreto (BYTEA em vez de TEXT)
**Sintomas:**
- Diagnóstico mostra `"image_data_column_type": "bytea"`
- Imagens aparecem corrompidas ou não carregam

**Solução:**
```bash
# Executar migration específica
psql -h <host> -U <user> -d <database> -f database/migrations/002_fix_image_data_column_type.sql
```

### Cenário 3: Item ID=1 Não Existe
**Sintomas:**
- Diagnóstico mostra `"total_items": 0` ou item não encontrado
- `/api/dish-image.php?id=1` retorna default.png

**Solução:**
1. Acesse o painel admin: `/admin.html`
2. Vá para a aba "Cardápio"
3. Crie grupos e itens
4. Faça upload de imagens

### Cenário 4: Imagem Nunca Foi Uploaded
**Sintomas:**
- Item existe mas diagnóstico mostra `"has_image_data": false`
- `"items_with_images": 0`

**Solução:**
1. Acesse `/admin.html` → Cardápio
2. Clique em "Editar" no item
3. Selecione uma imagem no campo "Upload de Imagem"
4. Clique em "Salvar"
5. Verifique se o upload foi bem-sucedido (mensagem de sucesso)

### Cenário 5: Base64 Corrompido
**Sintomas:**
- Diagnóstico mostra `"image_decode_success": false`
- Ou `"image_data_valid_base64": false`

**Solução:**
1. Deletar o item e recriar com nova imagem
2. Ou editar o item e fazer novo upload da imagem

## 📋 Passo a Passo Completo de Diagnóstico

### Passo 1: Execute o Diagnóstico
```bash
curl https://restauranteportugaservertest.onrender.com/api/test-image-diagnostics.php?test_id=1
```

Ou acesse no navegador:
```
https://restauranteportugaservertest.onrender.com/debug_images.php
```

### Passo 2: Analise os Resultados

**Se `"menu_items_exists": false`:**
→ Execute `database/setup.sql`

**Se `"image_data_column_exists": false`:**
→ Execute migration `001_add_menu_item_image_columns.sql`

**Se `"image_data_column_type": "bytea"`:**
→ Execute migration `002_fix_image_data_column_type.sql`

**Se `"total_items": 0`:**
→ Crie itens via admin panel

**Se `"items_with_images": 0` mas `"total_items" > 0`:**
→ Faça upload de imagens via admin panel

**Se item específico tem `"has_image_data": true` mas ainda cai no fallback:**
→ Verifique `"image_decode_success"` e `"image_format"`
→ Se false, a imagem está corrompida - faça novo upload

### Passo 3: Teste o Endpoint Diretamente
```bash
# Ver se retorna imagem ou default.png
curl -I https://restauranteportugaservertest.onrender.com/api/dish-image.php?id=1

# Content-Type: image/jpeg = Sucesso!
# Content-Type: image/png = Fallback (default.png)
```

### Passo 4: Verifique no Frontend
```javascript
// Abrir console do navegador (F12)
// Ir para aba "Cardápio"
// Verificar se há erros de CORS ou 404
```

## 🚀 Fluxo Completo de Upload e Exibição

### Upload (Admin Panel)
1. **Frontend (`admin.js`)**:
   - `saveItem()` comprime imagem via `compressImage()`
   - Envia via FormData para `/api/admin/menu.php?action=create-item`

2. **Backend (`menu.php`)**:
   - `processImageUpload()` valida e processa imagem
   - Redimensiona para max 1024px
   - Converte para JPEG 80% quality
   - **Codifica em Base64**
   - Salva em `menu_items.image_data` (TEXT)

### Exibição (Frontend)
1. **Admin Panel (`admin.js`)**:
   - `loadMenuManagement()` busca `/api/admin/menu.php?action=items`
   - Verifica `if (item.image_data || item.image_url)`
   - Renderiza `<img src="/api/dish-image.php?id=${item.id}">`

2. **Cardápio Público (`menu.html`)**:
   - Busca `/api/admin/menu.php?action=full-menu`
   - Renderiza `<img src="/api/dish-image.php?id=${item.id}">`

3. **Servidor de Imagem (`dish-image.php`)**:
   - Recebe `id` do item
   - Query: `SELECT image_data, image_mime_type FROM menu_items WHERE id = ?`
   - **Valida** e decodifica Base64
   - Retorna imagem ou default.png

## 📝 Checklist de Verificação

- [ ] Migrations executadas? (`/run_migrations.html`)
- [ ] Tabela `menu_items` existe?
- [ ] Colunas `image_data` (TEXT) e `image_mime_type` existem?
- [ ] Existem itens no banco? (`SELECT COUNT(*) FROM menu_items`)
- [ ] Item ID=1 existe? (`SELECT * FROM menu_items WHERE id = 1`)
- [ ] Item tem `image_data` não-nulo? 
- [ ] Base64 é válido?
- [ ] Teste endpoint: `/api/dish-image.php?id=1` retorna imagem?
- [ ] Frontend mostra imagem corretamente?

## 🐛 Debug Mode

Para ativar logs detalhados no servidor:
```bash
# Definir variável de ambiente
export MENU_DEBUG_MODE=true

# Ou no código menu.php:
define('MENU_DEBUG_MODE', true);

# Logs serão salvos em: api/admin/debug_upload.log
```

## 📞 Suporte

Se após todos os diagnósticos o problema persistir:
1. Execute `/api/test-image-diagnostics.php` e copie o JSON completo
2. Verifique `/api/admin/debug_upload.log` (se MENU_DEBUG_MODE=true)
3. Compartilhe os resultados para análise

## ✨ Melhorias Futuras Sugeridas

1. **Validação no Frontend**: Validar formato/tamanho antes do upload
2. **Thumbnail Cache**: Gerar thumbnails menores para listagens
3. **CDN Integration**: Servir imagens via CDN para melhor performance
4. **Batch Upload**: Permitir upload múltiplo de imagens
5. **Image Optimization**: Usar WebP quando suportado
