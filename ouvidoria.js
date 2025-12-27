// Ouvidoria JavaScript

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('ouvidoria-form');
    
    if (form) {
        form.addEventListener('submit', handleSubmit);
    }
});

function handleSubmit(e) {
    e.preventDefault();
    
    const formData = {
        full_name: document.getElementById('full-name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        subject: document.getElementById('subject').value,
        message: document.getElementById('message').value,
        image: document.getElementById('image').files[0]
    };
    
    // Validate required fields
    if (!formData.full_name || !formData.email || !formData.subject || !formData.message) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }
    
    // Generate protocol number
    const protocolNumber = generateProtocolNumber();
    
    // Save to localStorage (in production, this would send to API)
    saveOuvidoriaMessage(formData, protocolNumber);
    
    // Show success message
    alert(`Mensagem enviada com sucesso!\n\nSeu número de protocolo: ${protocolNumber}\n\nGuarde este número para consultar o andamento.`);
    
    // Reset form
    document.getElementById('ouvidoria-form').reset();
}

function generateProtocolNumber() {
    const date = new Date();
    const dateStr = date.toISOString().split('T')[0].replace(/-/g, '');
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `OUV-${dateStr}-${random}`;
}

function saveOuvidoriaMessage(data, protocolNumber) {
    const messages = getOuvidoriaMessages();
    
    const message = {
        protocol: protocolNumber,
        full_name: data.full_name,
        email: data.email,
        phone: data.phone,
        subject: data.subject,
        message: data.message,
        status: 'pendente',
        created_at: new Date().toISOString(),
        response: null
    };
    
    messages.push(message);
    localStorage.setItem('ouvidoria_messages', JSON.stringify(messages));
}

function getOuvidoriaMessages() {
    const messages = localStorage.getItem('ouvidoria_messages');
    return messages ? JSON.parse(messages) : [];
}

function consultProtocol() {
    const protocolNumber = document.getElementById('protocol-number').value.trim();
    const resultDiv = document.getElementById('protocol-result');
    
    if (!protocolNumber) {
        alert('Por favor, informe o número do protocolo.');
        return;
    }
    
    const messages = getOuvidoriaMessages();
    const message = messages.find(m => m.protocol === protocolNumber);
    
    if (!message) {
        resultDiv.innerHTML = `
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; text-align: center;">
                ❌ Protocolo não encontrado
            </div>
        `;
        return;
    }
    
    const statusLabels = {
        'pendente': { label: 'Pendente', color: '#fff3cd', textColor: '#856404' },
        'em_atendimento': { label: 'Em Atendimento', color: '#d1ecf1', textColor: '#0c5460' },
        'resolvido': { label: 'Resolvido', color: '#d4edda', textColor: '#155724' }
    };
    
    const statusInfo = statusLabels[message.status] || statusLabels['pendente'];
    const createdDate = new Date(message.created_at).toLocaleString('pt-BR');
    
    let html = `
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); text-align: left;">
            <div style="margin-bottom: 15px;">
                <strong>Protocolo:</strong> ${message.protocol}
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Status:</strong>
                <span style="padding: 5px 12px; background: ${statusInfo.color}; color: ${statusInfo.textColor}; border-radius: 20px; font-size: 0.85rem; margin-left: 10px;">
                    ${statusInfo.label}
                </span>
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Assunto:</strong> ${message.subject}
            </div>
            <div style="margin-bottom: 15px;">
                <strong>Data:</strong> ${createdDate}
            </div>
    `;
    
    if (message.response) {
        html += `
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px;">
                <strong style="color: #e8c13f;">Resposta:</strong>
                <p style="margin-top: 10px; color: #333;">${message.response}</p>
            </div>
        `;
    } else {
        html += `
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 15px; text-align: center;">
                <p style="color: #666;">Aguardando resposta...</p>
            </div>
        `;
    }
    
    html += '</div>';
    resultDiv.innerHTML = html;
}
