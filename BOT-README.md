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

## 🔐 Autenticação

1. Na primeira execução, um QR Code será exibido no terminal
2. Abra o WhatsApp no seu celular
3. Vá em: **Configurações** → **Aparelhos conectados** → **Conectar um aparelho**
4. Escaneie o QR Code exibido no terminal
5. O bot ficará autenticado e pronto para enviar mensagens

A autenticação é salva localmente na pasta `.wwebjs_auth/` e não precisa ser feita novamente nas próximas execuções.

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

## 🔧 Eventos do Bot

O bot emite os seguintes eventos durante sua execução:

- **qr**: Quando o QR Code é gerado (para autenticação)
- **authenticated**: Quando a autenticação é bem-sucedida
- **ready**: Quando o bot está pronto para enviar mensagens

## ⚠️ Observações

- O número de telefone deve incluir o código do país (ex: 55 para Brasil)
- O formato do número é: código do país + DDD + número (sem espaços ou caracteres especiais)
- **Sempre use o evento 'ready' para garantir que o bot está conectado antes de enviar mensagens**
- O método `sendMessage` lançará um erro se o bot não estiver pronto
- A pasta `.wwebjs_auth/` contém dados de sessão e não deve ser commitada no Git

## 🛠️ Tecnologias

- [whatsapp-web.js](https://github.com/pedroslopez/whatsapp-web.js) - Biblioteca para interação com WhatsApp Web
- [qrcode-terminal](https://github.com/gtanner/qrcode-terminal) - Geração de QR Code no terminal
