const WhatsAppBot = require('./bot-sender');

const bot = new WhatsAppBot();

// Escuta o evento 'ready' para enviar mensagem quando o bot estiver pronto
bot.client.on('ready', async () => {
    // Enviar mensagem para um número
    try {
        await bot.sendMessage('5511999999999', 'Olá! Esta é uma mensagem automática do bot.');
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
    }
});
