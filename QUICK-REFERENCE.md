# 🔐 Quick Reference: Hardcoded Users

## Login Credentials

### 🛍️ Cliente (Customer)
```
Email:    customer@test
Senha:    customertest
Acesso:   Página principal (/index.html)
```

### 🍽️ Garçom (Waiter)
```
Email:    waiter@test
Senha:    waitertest
Acesso:   Sistema de pedidos (/pedidos.html)
```

### 👨‍💼 Administrador (Admin)
```
Email:    admin@test
Senha:    admintest
Acesso:   Painel administrativo (/admin.html)
```

## Como Usar

1. Acesse a página de login: `/login.html`
2. Digite o email e senha de um dos usuários acima
3. Clique em "Entrar"
4. Você será redirecionado automaticamente para a página apropriada

## Notas Importantes

- ⚠️ Estes usuários são apenas para **desenvolvimento e teste**
- 🔒 As senhas estão hasheadas com bcrypt
- 🚀 Autenticação é instantânea (não usa banco de dados)
- 🔄 Compatível com autenticação normal de usuários

## Troubleshooting

### Login não funciona?

1. **Verifique os hashes:**
   ```bash
   php test-hardcoded-login.php
   ```

2. **Teste a API diretamente:**
   ```bash
   php test-hardcoded-api.php
   ```

3. **Desabilite CSRF temporariamente** (apenas para testes):
   - Os usuários hardcoded agora fazem bypass do CSRF automaticamente

4. **Verifique os logs do servidor:**
   ```bash
   tail -f /var/log/php_errors.log
   ```

### Senhas Corretas

- **Admin:** admintest
- **Waiter:** waitertest  
- **Customer:** customertest

**IMPORTANTE:** Senhas são case-sensitive!

## Documentação Completa

- **HARDCODED-USERS-README.md** - Documentação técnica detalhada
- **IMPLEMENTATION-SUMMARY.md** - Resumo da implementação
