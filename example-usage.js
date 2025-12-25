const WhatsAppBot = require('./bot-sender');

const bot = new WhatsAppBot();

// Aguarda o bot ficar pronto antes de enviar
setTimeout(async () => {
    // Enviar mensagem para um número
    await bot.sendMessage('5511999999999', 'Olá! Esta é uma mensagem automática do bot.');
}, 10000); // Aguarda 10 segundos para conexão
