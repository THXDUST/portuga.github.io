const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');

class WhatsAppBot {
    constructor() {
        this.client = new Client({
            authStrategy: new LocalAuth()
        });
        
        this.initialize();
    }

    initialize() {
        this.client.on('qr', (qr) => {
            console.log('Escaneie o QR Code:');
            qrcode.generate(qr, { small: true });
        });

        this.client.on('ready', () => {
            console.log('Bot pronto para enviar mensagens!');
        });

        this.client.on('authenticated', () => {
            console.log('Autenticado com sucesso!');
        });

        this.client.initialize();
    }

    async sendMessage(phoneNumber, message) {
        try {
            // Formato: número com código do país (ex: 5511999999999)
            const chatId = phoneNumber.includes('@c.us') 
                ? phoneNumber 
                : `${phoneNumber}@c.us`;
            
            await this.client.sendMessage(chatId, message);
            console.log(`✅ Mensagem enviada para ${phoneNumber}`);
            return true;
        } catch (error) {
            console.error('❌ Erro ao enviar mensagem:', error);
            return false;
        }
    }
}

module.exports = WhatsAppBot;
