# WhatsApp Bot - O Portuga

Bot simples para enviar mensagens do WhatsApp proativamente.

## 📋 Requisitos

- Node.js (versão 14 ou superior)
- npm ou yarn
- Conta do WhatsApp

## 🚀 Instalação

1. Instale as dependências:
```bash
npm install
```

## 📱 Como Usar

### 1. Execução Básica

Execute o exemplo de uso:
```bash
npm start
```

### 2. Uso Personalizado

```javascript
const WhatsAppBot = require('./bot-sender');

const bot = new WhatsAppBot();

// Escuta o evento 'ready' para enviar mensagem quando o bot estiver pronto
bot.client.on('ready', async () => {
    // Enviar mensagem para um número
    // Formato: código do país + DDD + número (ex: 5511999999999)
    try {
        await bot.sendMessage('5511999999999', 'Sua mensagem aqui!');
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
    }
});
```

### 3. Exemplos de Mensagens Formatadas

#### Mensagem Simples
```javascript
await bot.sendMessage('5511991234567', 'Olá! Esta é uma mensagem de teste.');
```

#### Mensagem com Formatação (Negrito e Itálico)
```javascript
const message = '*Texto em negrito*\n' +
                '_Texto em itálico_\n' +
                '~Texto riscado~\n' +
                '```Texto monoespaçado```';
await bot.sendMessage('5511991234567', message);
```

#### Mensagem de Pedido de Restaurante
```javascript
const orderMessage = '*🍕 Novo Pedido - Restaurante Portuga*\n\n' +
    '*Itens do Pedido:*\n' +
    '1. Bacalhau à Brás\n' +
    '   Quantidade: 1x\n' +
    '   Preço unitário: R$ 45,00\n' +
    '   Subtotal: R$ 45,00\n\n' +
    '2. Pizza Margherita\n' +
    '   Quantidade: 1x\n' +
    '   Preço unitário: R$ 38,00\n' +
    '   Subtotal: R$ 38,00\n\n' +
    '*Total do Pedido: R$ 83,00*\n\n' +
    '_Por favor, confirme o recebimento do pedido!_';

await bot.sendMessage('5511991234567', orderMessage);
```

#### Mensagem com Lista
```javascript
const listMessage = '*📋 Lista de Tarefas*\n\n' +
    '1. Preparar ingredientes\n' +
    '2. Cozinhar o prato\n' +
    '3. Embalar para entrega\n' +
    '4. Enviar ao cliente';
await bot.sendMessage('5511991234567', listMessage);
```

## 🔐 Autenticação

1. Na primeira execução, um QR Code será exibido no terminal
2. Abra o WhatsApp no seu celular
3. Vá em: **Configurações** → **Aparelhos conectados** → **Conectar um aparelho**
4. Escaneie o QR Code exibido no terminal
5. O bot ficará autenticado e pronto para enviar mensagens

A autenticação é salva localmente na pasta `.wwebjs_auth/` e não precisa ser feita novamente nas próximas execuções.

## 📞 Formato de Números de Telefone

### Números Brasileiros

O formato correto para números brasileiros é:
```
55 + DDD (2 dígitos) + Número (8 ou 9 dígitos)
```

**Exemplos válidos:**
- `5511991234567` - São Paulo (11) com 9 dígitos
- `5521987654321` - Rio de Janeiro (21) com 9 dígitos
- `5511912345678` - São Paulo (11) com 9 dígitos
- `55119123456` - São Paulo (11) com 8 dígitos (números fixos antigos)

**Formato incorreto (NÃO usar):**
- ❌ `11991234567` - Faltando código do país (55)
- ❌ `+55 11 99123-4567` - Com espaços e traços
- ❌ `(11) 99123-4567` - Com parênteses e traços

### Principais DDDs do Brasil

- **11** - São Paulo (capital e região metropolitana)
- **21** - Rio de Janeiro
- **31** - Belo Horizonte
- **41** - Curitiba
- **51** - Porto Alegre
- **61** - Brasília
- **71** - Salvador
- **81** - Recife
- **85** - Fortaleza

## 📝 API

### WhatsAppBot

#### constructor()
Cria uma nova instância do bot e inicializa a conexão.

#### sendMessage(phoneNumber, message)
Envia uma mensagem para um número de telefone.

**Parâmetros:**
- `phoneNumber` (string): Número com código do país (ex: '5511999999999')
- `message` (string): Mensagem a ser enviada

**Retorna:**
- `Promise<boolean>`: true se a mensagem foi enviada com sucesso, false caso contrário

**Exemplo:**
```javascript
await bot.sendMessage('5511999999999', 'Olá! Esta é uma mensagem automática.');
```

#### validatePhoneNumber(phoneNumber)
Valida o formato do número de telefone brasileiro.

**Parâmetros:**
- `phoneNumber` (string): Número a ser validado

**Retorna:**
- `boolean`: true se o formato é válido, false caso contrário

**Exemplo:**
```javascript
const isValid = bot.validatePhoneNumber('5511991234567'); // true
const isInvalid = bot.validatePhoneNumber('11991234567'); // false
```

#### waitForReady(timeout)
Aguarda o bot ficar pronto com timeout configurável.

**Parâmetros:**
- `timeout` (number, opcional): Tempo máximo de espera em milissegundos (padrão: 120000 = 2 minutos)

**Retorna:**
- `Promise<boolean>`: true se ficou pronto, false se deu timeout

**Exemplo:**
```javascript
const isReady = await bot.waitForReady(60000); // Aguarda 1 minuto
if (isReady) {
    await bot.sendMessage('5511991234567', 'Bot está pronto!');
}
```

## 🔧 Eventos do Bot

O bot emite os seguintes eventos durante sua execução:

- **qr**: Quando o QR Code é gerado (para autenticação)
- **authenticated**: Quando a autenticação é bem-sucedida
- **ready**: Quando o bot está pronto para enviar mensagens
- **auth_failure**: Quando há falha na autenticação
- **disconnected**: Quando o bot é desconectado

## 🔍 Resolução de Problemas

### Problema: QR Code não aparece no terminal

**Solução:**
1. Verifique se as dependências estão instaladas: `npm install`
2. Tente limpar a pasta de autenticação: `rm -rf .wwebjs_auth/`
3. Execute novamente: `npm start`

### Problema: Bot não fica pronto após escanear o QR Code

**Solução:**
1. Aguarde até 2 minutos - a conexão pode demorar
2. Verifique sua conexão com a internet
3. Certifique-se de que seu WhatsApp está ativo no celular
4. Tente desconectar outros aparelhos conectados ao WhatsApp Web

### Problema: Erro ao enviar mensagem - "Bot ainda não está pronto"

**Solução:**
1. Sempre use o evento `ready` antes de enviar mensagens:
```javascript
bot.client.on('ready', async () => {
    // Enviar mensagens aqui
});
```
2. Ou use o método `waitForReady()`:
```javascript
await bot.waitForReady();
await bot.sendMessage('5511991234567', 'Mensagem');
```

### Problema: Erro "Formato de número de telefone inválido"

**Solução:**
1. Verifique se o número inclui o código do país (55 para Brasil)
2. O formato correto é: `55` + `DDD` + `número`
3. Exemplo: `5511991234567` para o número (11) 99123-4567
4. Não use espaços, parênteses ou traços

### Problema: Mensagem não chega ao destinatário

**Solução:**
1. Verifique se o número está correto e ativo no WhatsApp
2. Confirme se o bot enviou com sucesso (veja os logs)
3. Verifique se o número não bloqueou mensagens do seu WhatsApp
4. Teste primeiro com seu próprio número de telefone

### Problema: Bot desconecta frequentemente

**Solução:**
1. Mantenha o WhatsApp ativo no celular
2. Não desconecte outros aparelhos enquanto o bot estiver rodando
3. Verifique sua conexão com a internet
4. Evite usar múltiplas instâncias do bot simultaneamente

## ⚠️ Observações

- O número de telefone deve incluir o código do país (ex: 55 para Brasil)
- O formato do número é: código do país + DDD + número (sem espaços ou caracteres especiais)
- **Sempre use o evento 'ready' para garantir que o bot está conectado antes de enviar mensagens**
- O método `sendMessage` lançará um erro se o bot não estiver pronto
- A pasta `.wwebjs_auth/` contém dados de sessão e não deve ser commitada no Git
- O bot funciona através do WhatsApp Web, então requer que o celular esteja conectado à internet
- Respeite as políticas de uso do WhatsApp para evitar bloqueios

## 🛠️ Tecnologias

- [whatsapp-web.js](https://github.com/pedroslopez/whatsapp-web.js) - Biblioteca para interação com WhatsApp Web
- [qrcode-terminal](https://github.com/gtanner/qrcode-terminal) - Geração de QR Code no terminal
