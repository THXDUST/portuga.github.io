const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');

/**
 * Classe para gerenciar o bot do WhatsApp
 * Utiliza whatsapp-web.js para enviar mensagens proativas
 */
class WhatsAppBot {
    constructor() {
        this.client = new Client({
            authStrategy: new LocalAuth()
        });
        this.isReady = false;
        this.readyTimeout = null;
        
        this.initialize();
    }

    initialize() {
        // Evento disparado quando o QR Code é gerado para autenticação
        this.client.on('qr', (qr) => {
            console.log('📱 Escaneie o QR Code no seu WhatsApp:');
            qrcode.generate(qr, { small: true });
            console.log('💡 Vá em: Configurações → Aparelhos conectados → Conectar um aparelho');
        });

        // Evento disparado quando o bot está pronto para enviar mensagens
        this.client.on('ready', () => {
            this.isReady = true;
            console.log('✅ Bot pronto para enviar mensagens!');
            console.log('⏰ Timestamp:', new Date().toISOString());
        });

        // Evento disparado após autenticação bem-sucedida
        this.client.on('authenticated', () => {
            console.log('🔐 Autenticado com sucesso!');
        });

        // Evento para capturar erros de autenticação
        this.client.on('auth_failure', (msg) => {
            console.error('❌ Falha na autenticação:', msg);
        });

        // Evento para capturar desconexões
        this.client.on('disconnected', (reason) => {
            console.log('🔌 Bot desconectado:', reason);
            this.isReady = false;
        });

        console.log('🚀 Inicializando bot do WhatsApp...');
        this.client.initialize();

        // Timeout de segurança: Se o bot não ficar pronto em 2 minutos, avisa
        this.readyTimeout = setTimeout(() => {
            if (!this.isReady) {
                console.warn('⚠️  Bot ainda não está pronto após 2 minutos.');
                console.warn('   Verifique se você escaneou o QR Code corretamente.');
            }
        }, 120000); // 2 minutos
    }

    /**
     * Valida o formato do número de telefone brasileiro
     * @param {string} phoneNumber - Número no formato 5511999999999
     * @returns {boolean} - true se o formato é válido
     */
    validatePhoneNumber(phoneNumber) {
        // Remove caracteres não numéricos
        const cleanNumber = phoneNumber.replace(/\D/g, '');
        
        // Verifica se tem o formato brasileiro correto
        // 55 (país) + 2 dígitos (DDD) + 8 ou 9 dígitos (número)
        const brazilianFormat = /^55\d{2}9?\d{8}$/;
        
        if (!brazilianFormat.test(cleanNumber)) {
            console.error('❌ Formato de número inválido:', phoneNumber);
            console.error('   Formato esperado: 55 + DDD (2 dígitos) + número (8-9 dígitos)');
            console.error('   Exemplo: 5511991234567 (11 é o DDD de São Paulo)');
            return false;
        }
        
        return true;
    }

    /**
     * Envia uma mensagem para um número de telefone
     * @param {string} phoneNumber - Número com código do país (ex: '5511999999999')
     * @param {string} message - Mensagem a ser enviada
     * @returns {Promise<boolean>} - true se enviada com sucesso, false caso contrário
     */
    async sendMessage(phoneNumber, message) {
        // Verifica se o bot está pronto
        if (!this.isReady) {
            throw new Error('Bot ainda não está pronto. Aguarde o evento "ready".');
        }

        // Valida o formato do número
        if (!this.validatePhoneNumber(phoneNumber)) {
            throw new Error('Formato de número de telefone inválido.');
        }

        try {
            // Formato do WhatsApp: número com código do país + @c.us
            const chatId = phoneNumber.includes('@c.us') 
                ? phoneNumber 
                : `${phoneNumber}@c.us`;
            
            console.log(`📤 Enviando mensagem para ${phoneNumber}...`);
            await this.client.sendMessage(chatId, message);
            console.log(`✅ Mensagem enviada com sucesso para ${phoneNumber}`);
            console.log(`⏰ Timestamp: ${new Date().toISOString()}`);
            return true;
        } catch (error) {
            console.error('❌ Erro ao enviar mensagem:', error.message);
            console.error('   Verifique se o número está correto e tem WhatsApp ativo.');
            return false;
        }
    }

    /**
     * Aguarda o bot ficar pronto com timeout
     * @param {number} timeout - Tempo máximo de espera em milissegundos (padrão: 120000 = 2 minutos)
     * @returns {Promise<boolean>} - true se ficou pronto, false se deu timeout
     */
    async waitForReady(timeout = 120000) {
        if (this.isReady) {
            return true;
        }

        return new Promise((resolve) => {
            const checkInterval = setInterval(() => {
                if (this.isReady) {
                    clearInterval(checkInterval);
                    resolve(true);
                }
            }, 1000);

            setTimeout(() => {
                clearInterval(checkInterval);
                if (!this.isReady) {
                    console.warn('⚠️  Timeout: Bot não ficou pronto no tempo esperado.');
                    resolve(false);
                }
            }, timeout);
        });
    }
}

module.exports = WhatsAppBot;
