# Roteiro de Testes Completo - Sistema Portuga

Este documento contém o roteiro completo de testes end-to-end para todas as funcionalidades do sistema de gerenciamento do Restaurante Portuga.

## Índice
- [Área Pública](#área-pública)
- [Painel Administrativo](#painel-administrativo)

---

## Área Pública

### 1. Navegação no Site

#### Teste 1.1: Acesso à Página Inicial
**Passos:**
1. Abrir o navegador
2. Acessar `http://[seu-dominio]/index.html`

**Resultado Esperado:**
- Página inicial carrega com sucesso
- Logo do restaurante visível
- Menu de navegação presente (Home, Cardápio, Ouvidoria, Trabalhe Conosco)
- Botão de login visível

**Screenshot Sugerido:** Captura da página inicial completa

---

#### Teste 1.2: Navegação entre Páginas
**Passos:**
1. Na página inicial, clicar em "Cardápio"
2. Clicar em "Ouvidoria"
3. Clicar em "Trabalhe Conosco"
4. Clicar em "Home"

**Resultado Esperado:**
- Cada página carrega corretamente sem erros
- Menu de navegação permanece consistente
- Transições suaves entre páginas

**Screenshot Sugerido:** Captura de cada página do site

---

### 2. Visualização de Horários e Informações Dinâmicas

#### Teste 2.1: Visualização de Horários de Funcionamento
**Passos:**
1. Acessar página inicial
2. Localizar seção de horários de funcionamento

**Resultado Esperado:**
- Horários exibidos claramente
- Dias da semana listados
- Informações atualizadas conforme configuração do admin

**Screenshot Sugerido:** Seção de horários em destaque

---

#### Teste 2.2: Status do Restaurante (Aberto/Fechado)
**Passos:**
1. Verificar indicador de status na página inicial
2. Observar se há mensagem indicando se está aberto ou fechado

**Resultado Esperado:**
- Status exibido corretamente baseado no horário atual
- Cor diferenciada para status (verde=aberto, vermelho=fechado)
- Mensagem clara para o usuário

**Screenshot Sugerido:** Indicador de status do restaurante

---

### 3. Cadastro de Usuário

#### Teste 3.1: Cadastro com Email/Senha
**Passos:**
1. Clicar em "Login" no menu
2. Clicar em "Cadastre-se" ou "Registrar"
3. Preencher:
   - Nome completo: "João Silva"
   - Email: "joao.silva@example.com"
   - Senha: "Senha@123"
   - Confirmar senha: "Senha@123"
   - Telefone: "(11) 98765-4321"
4. Clicar em "Cadastrar"

**Resultado Esperado:**
- Formulário aceita todos os campos
- Validação de formato de email funciona
- Validação de senha forte funciona
- Mensagem de sucesso exibida
- Email de verificação enviado (se configurado)
- Redirecionamento para login ou perfil

**Screenshot Sugerido:** Formulário de cadastro preenchido e mensagem de sucesso

---

#### Teste 3.2: Validações de Cadastro
**Passos:**
1. Tentar cadastrar com email inválido: "emailinvalido"
2. Tentar cadastrar com senha fraca: "123"
3. Tentar cadastrar com senhas não coincidentes

**Resultado Esperado:**
- Mensagens de erro apropriadas para cada validação
- Formulário não é submetido com dados inválidos
- Campos problemáticos destacados

**Screenshot Sugerido:** Mensagens de validação de erro

---

#### Teste 3.3: Cadastro com Google OAuth
**Passos:**
1. Na página de cadastro, clicar em "Entrar com Google"
2. Autorizar acesso na janela do Google
3. Retornar ao site

**Resultado Esperado:**
- Janela de autenticação do Google abre
- Após autorização, usuário é automaticamente cadastrado
- Usuário é direcionado para perfil ou página inicial logado

**Screenshot Sugerido:** Botão Google OAuth e resultado após login

---

### 4. Login de Usuário

#### Teste 4.1: Login com Email/Senha
**Passos:**
1. Acessar página de login
2. Inserir email: "joao.silva@example.com"
3. Inserir senha: "Senha@123"
4. Clicar em "Entrar"

**Resultado Esperado:**
- Login realizado com sucesso
- Redirecionamento para página inicial ou perfil
- Menu exibe nome do usuário
- Opção de logout disponível

**Screenshot Sugerido:** Página após login bem-sucedido

---

#### Teste 4.2: Login com Credenciais Inválidas
**Passos:**
1. Tentar login com email não cadastrado
2. Tentar login com senha incorreta

**Resultado Esperado:**
- Mensagem de erro: "Email ou senha incorretos"
- Usuário permanece na tela de login
- Não há informação sobre qual campo está incorreto (segurança)

**Screenshot Sugerido:** Mensagem de erro de login

---

#### Teste 4.3: Login com OAuth (Google/Facebook/Instagram)
**Passos:**
1. Clicar em "Entrar com Google"
2. Selecionar conta Google
3. Autorizar acesso

**Resultado Esperado:**
- Login realizado com sucesso via OAuth
- Usuário direcionado para área logada
- Perfil preenchido com dados do OAuth

**Screenshot Sugerido:** Opções de OAuth e resultado

---

### 5. Visualização do Cardápio

#### Teste 5.1: Visualização de Grupos do Cardápio
**Passos:**
1. Acessar página "Cardápio" (`menu.html`)
2. Observar lista de grupos (ex: Pizzas, Bebidas, Sobremesas)

**Resultado Esperado:**
- Todos os grupos ativos são exibidos
- Grupos organizados por ordem de exibição
- Descrição de cada grupo visível

**Screenshot Sugerido:** Página de cardápio com grupos

---

#### Teste 5.2: Visualização de Subgrupos
**Passos:**
1. Na página de cardápio, identificar um grupo com subgrupos (ex: Pizzas → Pizzas Salgadas, Pizzas Doces)
2. Observar hierarquia

**Resultado Esperado:**
- Subgrupos aparecem sob seu grupo pai
- Indicação visual de hierarquia (indentação, ícones)
- Subgrupos podem ser expandidos/colapsados

**Screenshot Sugerido:** Grupo expandido mostrando subgrupos

---

#### Teste 5.3: Visualização de Itens do Cardápio
**Passos:**
1. Selecionar um grupo ou subgrupo
2. Visualizar itens disponíveis

**Resultado Esperado:**
- Todos os itens do grupo/subgrupo são exibidos
- Para cada item:
  - Nome visível
  - Descrição visível
  - Preço formatado (R$ XX,XX)
  - Imagem (se disponível)
  - Indicação de disponibilidade
- Apenas itens ativos são mostrados

**Screenshot Sugerido:** Lista de itens de um grupo

---

#### Teste 5.4: Filtro e Busca no Cardápio
**Passos:**
1. Utilizar campo de busca (se disponível)
2. Digitar "pizza"
3. Filtrar por categoria

**Resultado Esperado:**
- Itens filtrados aparecem corretamente
- Busca encontra itens por nome e descrição
- Filtros podem ser combinados

**Screenshot Sugerido:** Resultado de busca/filtro

---

### 6. Fazer Pedido

#### Teste 6.1: Pedido para Delivery
**Passos:**
1. Estando logado, no cardápio, clicar em "Adicionar ao Carrinho" em alguns itens
2. Clicar no ícone do carrinho
3. Revisar itens
4. Clicar em "Finalizar Pedido"
5. Selecionar "Delivery"
6. Preencher endereço de entrega
7. Adicionar observações (opcional)
8. Confirmar pedido

**Resultado Esperado:**
- Itens são adicionados ao carrinho corretamente
- Carrinho mostra quantidade e subtotal
- Formulário de delivery solicita endereço completo
- Cálculo de taxa de entrega (se aplicável)
- Pedido é registrado no sistema
- Mensagem de confirmação com número do pedido
- Redirecionamento para página de pedidos ou confirmação

**Screenshot Sugerido:** Carrinho, formulário de delivery e confirmação

---

#### Teste 6.2: Pedido para Retirada
**Passos:**
1. Adicionar itens ao carrinho
2. Clicar em "Finalizar Pedido"
3. Selecionar "Retirada no Local"
4. Adicionar observações
5. Confirmar pedido

**Resultado Esperado:**
- Opção de retirada disponível
- Não solicita endereço
- Pode solicitar horário preferencial para retirada
- Pedido registrado corretamente
- Confirmação com número do pedido

**Screenshot Sugerido:** Seleção de retirada e confirmação

---

#### Teste 6.3: Pedido para Mesa
**Passos:**
1. Adicionar itens ao carrinho
2. Selecionar "Pedido para Mesa"
3. Informar número da mesa
4. Confirmar

**Resultado Esperado:**
- Opção de mesa disponível
- Campo para número da mesa
- Pedido associado à mesa correta
- Confirmação do pedido

**Screenshot Sugerido:** Seleção de mesa e confirmação

---

#### Teste 6.4: Modificar Quantidades no Carrinho
**Passos:**
1. Com itens no carrinho, aumentar quantidade de um item
2. Diminuir quantidade de outro item
3. Remover um item completamente

**Resultado Esperado:**
- Botões +/- funcionam corretamente
- Subtotal atualiza em tempo real
- Total geral recalculado automaticamente
- Item removido some da lista

**Screenshot Sugerido:** Carrinho com modificações

---

### 7. Enviar Mensagem na Ouvidoria

#### Teste 7.1: Enviar Mensagem Autenticado
**Passos:**
1. Fazer login no sistema
2. Acessar página "Ouvidoria"
3. Preencher formulário:
   - Assunto: "Sugestão de Melhoria"
   - Mensagem: "Adorei o atendimento! Sugiro adicionar mais opções vegetarianas."
   - Tipo: "Sugestão"
4. Clicar em "Enviar"

**Resultado Esperado:**
- Formulário pré-preenchido com dados do usuário (nome, email)
- Campos não editáveis (se autenticado)
- Mensagem enviada com sucesso
- Confirmação exibida
- Mensagem registrada no banco associada ao usuário

**Screenshot Sugerido:** Formulário de ouvidoria e mensagem de sucesso

---

#### Teste 7.2: Tentativa de Envio sem Login
**Passos:**
1. Sem estar logado, acessar ouvidoria
2. Tentar preencher formulário

**Resultado Esperado:**
- Sistema solicita login antes de permitir envio
- Ou permite envio com preenchimento manual de dados

**Screenshot Sugerido:** Mensagem de requisição de login

---

### 8. Enviar Currículo com Anexo

#### Teste 8.1: Envio de Currículo Completo
**Passos:**
1. Acessar "Trabalhe Conosco"
2. Preencher formulário:
   - Nome: "Maria Santos"
   - Email: "maria.santos@example.com"
   - Telefone: "(11) 91234-5678"
   - Cargo de interesse: "Cozinheiro(a)"
   - Experiência: "5 anos em cozinha italiana"
3. Anexar arquivo PDF do currículo
4. Clicar em "Enviar Currículo"

**Resultado Esperado:**
- Upload de arquivo funciona (aceita PDF, DOC, DOCX)
- Validação de tamanho de arquivo (máx 5MB)
- Mensagem de sucesso após envio
- Currículo armazenado e acessível no admin
- Arquivo salvo corretamente no servidor

**Screenshot Sugerido:** Formulário preenchido e mensagem de sucesso

---

#### Teste 8.2: Validações de Envio de Currículo
**Passos:**
1. Tentar enviar sem anexar arquivo
2. Tentar anexar arquivo muito grande (>5MB)
3. Tentar anexar arquivo de formato não permitido (.exe)

**Resultado Esperado:**
- Mensagem de erro para cada validação
- "Arquivo obrigatório"
- "Arquivo muito grande"
- "Formato não permitido"

**Screenshot Sugerido:** Mensagens de validação

---

### 9. Deixar Avaliação

#### Teste 9.1: Enviar Avaliação com Login
**Passos:**
1. Fazer login
2. Acessar página de avaliações (`avaliar.html`)
3. Preencher:
   - Classificação: 5 estrelas
   - Comentário: "Excelente comida e atendimento impecável!"
   - Permitir exibição pública: ✓
4. Clicar em "Enviar Avaliação"

**Resultado Esperado:**
- Avaliação enviada com sucesso
- Mensagem de confirmação
- Avaliação aparece na lista (após moderação, se aplicável)
- Estrelas corretamente registradas
- Nome do usuário associado à avaliação

**Screenshot Sugerido:** Formulário de avaliação e confirmação

---

#### Teste 9.2: Avaliação sem Login
**Passos:**
1. Sem login, tentar acessar página de avaliações
2. Verificar comportamento

**Resultado Esperado:**
- Sistema redireciona para login
- Ou permite avaliação anônima com campos adicionais

**Screenshot Sugerido:** Comportamento do sistema

---

---

## Painel Administrativo

### 10. Login Admin

#### Teste 10.1: Login com Credenciais Admin
**Passos:**
1. Acessar `admin.html`
2. Inserir:
   - Usuário: admin
   - Senha: portuga123
3. Clicar em "Entrar"

**Resultado Esperado:**
- Login realizado com sucesso
- Painel administrativo carrega
- Dashboard é exibido
- Menu lateral com todas as opções disponíveis

**Screenshot Sugerido:** Tela de login admin e dashboard inicial

---

#### Teste 10.2: Login Admin com Credenciais Inválidas
**Passos:**
1. Tentar login com senha incorreta

**Resultado Esperado:**
- Mensagem de erro
- Acesso negado
- Permanece na tela de login

**Screenshot Sugerido:** Mensagem de erro

---

### 11. Dashboard

#### Teste 11.1: Visualização de Estatísticas
**Passos:**
1. Após login, observar dashboard
2. Verificar cards de estatísticas

**Resultado Esperado:**
- Total de pedidos do dia/semana/mês
- Total de faturamento
- Pedidos pendentes
- Número de usuários cadastrados
- Estatísticas atualizadas em tempo real

**Screenshot Sugerido:** Dashboard completo

---

#### Teste 11.2: Visualização de Gráficos
**Passos:**
1. No dashboard, localizar gráficos
2. Verificar gráfico de faturamento
3. Verificar gráfico de pedidos por período

**Resultado Esperado:**
- Gráficos carregam corretamente
- Dados representados visualmente
- Legendas claras
- Gráficos interativos (hover mostra detalhes)

**Screenshot Sugerido:** Gráficos do dashboard

---

### 12. Kanban de Pedidos

#### Teste 12.1: Visualização do Kanban
**Passos:**
1. Clicar em "Pedidos" no menu
2. Observar colunas do Kanban

**Resultado Esperado:**
- Três colunas visíveis: "Recebido", "Em Andamento", "Finalizado"
- Pedidos aparecem como cards em suas respectivas colunas
- Cada card mostra:
  - Número do pedido
  - Itens
  - Valor total
  - Tipo (delivery/retirada/mesa)
  - Horário

**Screenshot Sugerido:** Kanban completo com pedidos

---

#### Teste 12.2: Arrastar e Soltar Pedidos
**Passos:**
1. Selecionar um card de pedido na coluna "Recebido"
2. Arrastar para coluna "Em Andamento"
3. Arrastar para "Finalizado"

**Resultado Esperado:**
- Drag and drop funciona suavemente
- Card move entre colunas
- Status do pedido atualiza no banco de dados
- Atualização visual imediata
- Não há duplicação de cards

**Screenshot Sugerido:** Antes e depois do arraste

---

#### Teste 12.3: Filtros do Kanban
**Passos:**
1. Utilizar filtro por tipo (Delivery, Retirada, Mesa)
2. Utilizar filtro por número de mesa

**Resultado Esperado:**
- Filtros funcionam corretamente
- Pedidos filtrados aparecem
- Demais pedidos ocultados
- Possível limpar filtros

**Screenshot Sugerido:** Kanban com filtro aplicado

---

### 13. Gerenciamento de Cardápio

#### Teste 13.1: Criar Grupo Principal
**Passos:**
1. Clicar em "Cardápio" no menu admin
2. Clicar em "Adicionar Grupo"
3. Preencher:
   - Nome: "Entradas"
   - Descrição: "Deliciosas entradas para começar"
   - Grupo Pai: (nenhum)
4. Clicar em "Salvar"

**Resultado Esperado:**
- Modal de criação abre
- Grupo salvo com sucesso
- Mensagem de confirmação
- Grupo aparece na listagem
- Grupo destacado com cor principal

**Screenshot Sugerido:** Modal de criação e grupo criado

---

#### Teste 13.2: Criar Subgrupo
**Passos:**
1. Clicar em "Adicionar Grupo"
2. Preencher:
   - Nome: "Entradas Quentes"
   - Descrição: "Entradas servidas quentes"
   - Grupo Pai: Selecionar "Entradas"
3. Salvar

**Resultado Esperado:**
- Subgrupo criado
- Aparece indentado sob "Entradas"
- Indicador visual de hierarquia (seta, indentação)
- Borda colorida indicando subgrupo

**Screenshot Sugerido:** Hierarquia de grupos

---

#### Teste 13.3: Editar Grupo
**Passos:**
1. Localizar grupo "Entradas"
2. Clicar em botão "✏️ Editar"
3. Alterar descrição
4. Salvar

**Resultado Esperado:**
- Modal de edição abre com dados preenchidos
- Alterações salvas
- Atualização refletida imediatamente

**Screenshot Sugerido:** Modal de edição

---

#### Teste 13.4: Deletar Grupo
**Passos:**
1. Criar grupo de teste
2. Clicar em "🗑️ Excluir"
3. Confirmar exclusão

**Resultado Esperado:**
- Confirmação solicitada
- Aviso se grupo possui itens ou subgrupos
- Grupo removido após confirmação
- Grupo some da listagem

**Screenshot Sugerido:** Confirmação de exclusão

---

#### Teste 13.5: Criar Item (Prato)
**Passos:**
1. Clicar em "Adicionar Item"
2. Preencher:
   - Grupo: "Entradas Quentes"
   - Nome: "Bolinho de Bacalhau"
   - Descrição: "6 unidades de bolinhos crocantes"
   - Preço: 35.00
   - Ingredientes: "Bacalhau, batata, cebola, salsa"
   - URL da Imagem: (opcional ou upload)
   - Disponível: ✓
   - Delivery Habilitado: ✓
3. Salvar

**Resultado Esperado:**
- Modal de item abre
- Select de grupos mostra hierarquia
- Item salvo com sucesso
- Item aparece no grupo selecionado
- Preço formatado corretamente
- Badge de disponibilidade visível

**Screenshot Sugerido:** Modal de criação de item e item criado

---

#### Teste 13.6: Upload de Imagem de Item
**Passos:**
1. Ao criar/editar item
2. Fazer upload de imagem
3. Salvar

**Resultado Esperado:**
- Upload funciona
- Imagem é armazenada no servidor
- URL da imagem salva no banco
- Preview da imagem exibido
- Validação de formato (JPG, PNG)
- Validação de tamanho

**Screenshot Sugerido:** Upload de imagem

---

#### Teste 13.7: Editar Item
**Passos:**
1. Localizar item criado
2. Clicar em "✏️" no item
3. Alterar preço e descrição
4. Salvar

**Resultado Esperado:**
- Modal abre com dados do item
- Alterações salvas
- Atualização imediata na listagem

**Screenshot Sugerido:** Edição de item

---

#### Teste 13.8: Ativar/Desativar Item
**Passos:**
1. Editar um item
2. Desmarcar "Disponível para Venda"
3. Salvar
4. Verificar na área pública

**Resultado Esperado:**
- Item marcado como indisponível no admin
- Badge muda para "❌ Indisponível"
- Item não aparece no cardápio público
- Pode ser reativado facilmente

**Screenshot Sugerido:** Item desativado

---

#### Teste 13.9: Deletar Item
**Passos:**
1. Clicar em "🗑️" em um item
2. Confirmar exclusão

**Resultado Esperado:**
- Confirmação solicitada
- Item removido
- Some da listagem
- Não afeta grupo

**Screenshot Sugerido:** Confirmação de exclusão de item

---

### 14. Avaliações

#### Teste 14.1: Visualizar Avaliações
**Passos:**
1. Clicar em "Avaliações" no menu admin
2. Visualizar lista de avaliações

**Resultado Esperado:**
- Todas as avaliações listadas
- Para cada avaliação:
  - Nome do usuário
  - Estrelas
  - Comentário
  - Data
  - Status (pendente/aprovada/rejeitada)

**Screenshot Sugerido:** Lista de avaliações

---

#### Teste 14.2: Responder Avaliação
**Passos:**
1. Selecionar uma avaliação
2. Clicar em "Responder"
3. Escrever resposta
4. Salvar

**Resultado Esperado:**
- Campo de resposta aparece
- Resposta salva
- Resposta exibida abaixo da avaliação original
- Resposta visível na área pública

**Screenshot Sugerido:** Resposta a avaliação

---

#### Teste 14.3: Moderar Avaliações
**Passos:**
1. Selecionar avaliação pendente
2. Aprovar ou rejeitar

**Resultado Esperado:**
- Botões de aprovar/rejeitar disponíveis
- Após aprovação, avaliação aparece no site público
- Após rejeição, avaliação não aparece
- Status atualizado

**Screenshot Sugerido:** Moderação de avaliações

---

### 15. Notas/Comunicados

#### Teste 15.1: Criar Nota
**Passos:**
1. Clicar em "Notas" no menu
2. Clicar em "Adicionar Nota"
3. Preencher:
   - Título: "Horário Especial de Fim de Ano"
   - Mensagem: "Estaremos abertos em horário especial..."
   - Prioridade: Alta
   - Ativo: ✓
4. Salvar

**Resultado Esperado:**
- Nota criada com sucesso
- Nota aparece na listagem
- Nota exibida no site público (se configurado)
- Badge de prioridade visível

**Screenshot Sugerido:** Criação e visualização de nota

---

#### Teste 15.2: Editar Nota
**Passos:**
1. Selecionar nota criada
2. Clicar em "Editar"
3. Modificar conteúdo
4. Salvar

**Resultado Esperado:**
- Edição salva
- Atualização refletida

**Screenshot Sugerido:** Edição de nota

---

#### Teste 15.3: Deletar Nota
**Passos:**
1. Selecionar nota
2. Clicar em "Deletar"
3. Confirmar

**Resultado Esperado:**
- Confirmação solicitada
- Nota removida
- Some da listagem e do site público

**Screenshot Sugerido:** Exclusão de nota

---

### 16. Relatórios

#### Teste 16.1: Relatório de Faturamento
**Passos:**
1. Clicar em "Relatórios"
2. Selecionar "Faturamento"
3. Definir período (ex: último mês)
4. Gerar relatório

**Resultado Esperado:**
- Relatório gerado com sucesso
- Mostra faturamento total
- Breakdown por tipo de pedido
- Gráficos visuais
- Opção de exportar (PDF/Excel)

**Screenshot Sugerido:** Relatório de faturamento

---

#### Teste 16.2: Relatório de Itens Mais Vendidos
**Passos:**
1. Selecionar "Itens Mais Vendidos"
2. Definir período
3. Gerar

**Resultado Esperado:**
- Lista de itens ordenados por quantidade vendida
- Mostra quantidade e receita de cada item
- Gráfico de barras ou pizza
- Top 10 ou Top 20

**Screenshot Sugerido:** Itens mais vendidos

---

#### Teste 16.3: Relatório de Fluxo de Clientes
**Passos:**
1. Selecionar "Fluxo de Clientes"
2. Definir período
3. Gerar

**Resultado Esperado:**
- Gráfico de pedidos por horário
- Dias com mais movimento
- Insights sobre períodos de pico

**Screenshot Sugerido:** Relatório de fluxo

---

### 17. Currículos

#### Teste 17.1: Visualizar Currículos
**Passos:**
1. Clicar em "Currículos" no menu
2. Visualizar lista

**Resultado Esperado:**
- Lista de currículos recebidos
- Para cada currículo:
  - Nome do candidato
  - Email
  - Telefone
  - Cargo de interesse
  - Data de envio
  - Status
- Link para download do arquivo

**Screenshot Sugerido:** Lista de currículos

---

#### Teste 17.2: Alterar Status de Currículo
**Passos:**
1. Selecionar um currículo
2. Alterar status para "Em análise", "Aprovado" ou "Rejeitado"
3. Adicionar observações (opcional)

**Resultado Esperado:**
- Status atualizado
- Observações salvas
- Histórico de alterações mantido

**Screenshot Sugerido:** Alteração de status

---

#### Teste 17.3: Download de Currículo
**Passos:**
1. Clicar no link de download de um currículo

**Resultado Esperado:**
- Arquivo baixado corretamente
- Formato original preservado

**Screenshot Sugerido:** Download iniciado

---

### 18. Ouvidoria (Admin)

#### Teste 18.1: Visualizar Mensagens da Ouvidoria
**Passos:**
1. Clicar em "Ouvidoria" no menu admin
2. Visualizar mensagens

**Resultado Esperado:**
- Lista de mensagens
- Para cada mensagem:
  - Nome do remetente
  - Email
  - Assunto
  - Tipo (reclamação/sugestão/elogio)
  - Data
  - Status (lida/não lida)

**Screenshot Sugerido:** Lista de mensagens

---

#### Teste 18.2: Responder Mensagem
**Passos:**
1. Selecionar uma mensagem
2. Clicar em "Responder"
3. Escrever resposta
4. Enviar

**Resultado Esperado:**
- Campo de resposta disponível
- Email enviado ao remetente (se configurado)
- Resposta registrada no sistema
- Status da mensagem atualizado

**Screenshot Sugerido:** Resposta enviada

---

### 19. Cargos

#### Teste 19.1: Criar Cargo
**Passos:**
1. Clicar em "Cargos" no menu
2. Clicar em "Adicionar Cargo"
3. Preencher:
   - Nome: "Gerente"
   - Descrição: "Gerente do restaurante com acesso total"
4. Salvar

**Resultado Esperado:**
- Cargo criado
- Aparece na listagem

**Screenshot Sugerido:** Criação de cargo

---

#### Teste 19.2: Atribuir Permissões ao Cargo
**Passos:**
1. Selecionar cargo "Gerente"
2. Clicar em "Gerenciar Permissões"
3. Marcar permissões:
   - ✓ Visualizar pedidos
   - ✓ Gerenciar cardápio
   - ✓ Visualizar relatórios
   - ✓ Gerenciar usuários
4. Salvar

**Resultado Esperado:**
- Permissões atribuídas
- Checkbox marcadas persistem
- Usuários com esse cargo herdam permissões

**Screenshot Sugerido:** Tela de permissões

---

#### Teste 19.3: Editar Cargo
**Passos:**
1. Editar nome/descrição de um cargo
2. Salvar

**Resultado Esperado:**
- Alterações salvas
- Usuários com esse cargo não são afetados negativamente

**Screenshot Sugerido:** Edição de cargo

---

### 20. Usuários

#### Teste 20.1: Visualizar Usuários
**Passos:**
1. Clicar em "Usuários"
2. Visualizar lista

**Resultado Esperado:**
- Lista de todos os usuários cadastrados
- Para cada usuário:
  - Nome
  - Email
  - Cargo(s)
  - Status (ativo/inativo)
  - Data de cadastro

**Screenshot Sugerido:** Lista de usuários

---

#### Teste 20.2: Criar Usuário Admin
**Passos:**
1. Clicar em "Adicionar Usuário"
2. Preencher dados
3. Atribuir cargo "Gerente"
4. Salvar

**Resultado Esperado:**
- Usuário criado
- Cargo atribuído
- Usuário pode fazer login

**Screenshot Sugerido:** Criação de usuário

---

#### Teste 20.3: Editar Usuário
**Passos:**
1. Selecionar usuário
2. Clicar em "Editar"
3. Alterar cargo
4. Salvar

**Resultado Esperado:**
- Alterações salvas
- Novo cargo aplicado
- Permissões do usuário atualizadas

**Screenshot Sugerido:** Edição de usuário

---

#### Teste 20.4: Gerenciar Cargos de Usuário
**Passos:**
1. Selecionar usuário
2. Adicionar múltiplos cargos
3. Remover um cargo

**Resultado Esperado:**
- Usuário pode ter múltiplos cargos
- Permissões são acumulativas
- Remoção de cargo reflete imediatamente

**Screenshot Sugerido:** Gerenciamento de cargos

---

#### Teste 20.5: Ativar/Desativar Usuário
**Passos:**
1. Selecionar usuário ativo
2. Clicar em "Desativar"
3. Tentar fazer login com esse usuário
4. Reativar usuário

**Resultado Esperado:**
- Usuário desativado não pode fazer login
- Mensagem de conta desativada
- Reativação permite login novamente
- Dados do usuário preservados

**Screenshot Sugerido:** Desativação e reativação

---

### 21. Configurações

#### Teste 21.1: Configurar Horários de Funcionamento
**Passos:**
1. Clicar em "Configurações"
2. Seção "Horários"
3. Configurar horários para cada dia da semana:
   - Segunda a Sexta: 11:00 - 23:00
   - Sábado: 12:00 - 00:00
   - Domingo: 12:00 - 22:00
4. Salvar

**Resultado Esperado:**
- Horários salvos
- Horários refletidos no site público
- Status "aberto/fechado" atualiza automaticamente

**Screenshot Sugerido:** Configuração de horários

---

#### Teste 21.2: Configurar Status do Restaurante
**Passos:**
1. Alternar status:
   - Aberto
   - Fechado temporariamente
   - Fechado para férias
2. Adicionar mensagem personalizada
3. Salvar

**Resultado Esperado:**
- Status atualizado imediatamente
- Mensagem exibida no site público
- Pedidos bloqueados quando fechado

**Screenshot Sugerido:** Configuração de status

---

#### Teste 21.3: Configurar Delivery
**Passos:**
1. Seção "Delivery"
2. Configurar:
   - Taxa de entrega: R$ 8,00
   - Raio de entrega: 5 km
   - Tempo estimado: 45-60 min
   - Pedido mínimo: R$ 30,00
3. Salvar

**Resultado Esperado:**
- Configurações salvas
- Taxa aplicada em pedidos de delivery
- Validação de endereço por raio
- Pedidos abaixo do mínimo bloqueados

**Screenshot Sugerido:** Configuração de delivery

---

#### Teste 21.4: Configurações Gerais
**Passos:**
1. Configurar informações gerais:
   - Nome do restaurante
   - Endereço
   - Telefone
   - Email
   - Redes sociais
2. Salvar

**Resultado Esperado:**
- Informações atualizadas
- Refletidas no site público
- Utilizadas em emails automáticos

**Screenshot Sugerido:** Configurações gerais

---

## Notas Finais

- **Antes de cada teste:** Verificar se há dados suficientes no sistema (usuários, pedidos, itens de menu)
- **Após cada teste:** Marcar como ✅ se passou ou ❌ se falhou
- **Documentar bugs:** Para cada falha, anotar detalhes e passos para reproduzir
- **Screenshots:** Sempre que possível, capturar tela para documentação
- **Ambiente:** Especificar ambiente de teste (desenvolvimento, staging, produção)

---

**Data do Roteiro:** 2026-01-04
**Versão:** 1.0
**Status:** Documento criado, testes pendentes de execução
