const WhatsAppBot = require('./bot-sender');

const bot = new WhatsAppBot();

// Escuta o evento 'ready' para enviar mensagem quando o bot estiver pronto
bot.client.on('ready', async () => {
    // Criar mensagem de pedido de teste no formato usado pelo site
    const testMessage = '*🍕 Novo Pedido - Restaurante Portuga*\n\n' +
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
        '_Pedido de teste via Bot WhatsApp. Por favor, confirme o recebimento!_';
    
    // Enviar mensagem para o número de teste (11 991925341)
    // Formato: 55 (código do país) + 11 (DDD) + 991925341 (número)
    try {
        await bot.sendMessage('5511991925341', testMessage);
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
    }
});
