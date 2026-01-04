// Ouvidoria JavaScript

document.addEventListener('DOMContentLoaded', async function() {
    const form = document.getElementById('ouvidoria-form');
    
    if (form) {
        form.addEventListener('submit', handleSubmit);
    }
    
    // Load user chats if logged in
    await loadMyChats();
});

async function handleSubmit(e) {
    e.preventDefault();
    
    // Check if user is logged in before allowing submission
    if (typeof isUserLoggedIn !== 'function' || !isUserLoggedIn()) {
        alert('Você precisa estar logado para enviar uma mensagem. Por favor, faça login primeiro.');
        window.location.href = '/login.html?redirect=' + encodeURIComponent(window.location.pathname);
        return;
    }
    
    const formData = {
        full_name: document.getElementById('full-name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        subject: document.getElementById('subject').value,
        message: document.getElementById('message').value
    };
    
    // Validate required fields
    if (!formData.full_name || !formData.email || !formData.subject || !formData.message) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return;
    }
    
    try {
        const response = await fetch('/api/ouvidoria.php?action=submit', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Mensagem enviada com sucesso!\n\nSeu número de protocolo: ${data.data.protocol_number}\n\nGuarde este número para consultar o andamento.`);
            document.getElementById('ouvidoria-form').reset();
            
            // Reload user chats if logged in
            await loadMyChats();
        } else {
            alert('Erro ao enviar mensagem: ' + data.message);
        }
    } catch (error) {
        console.error('Error submitting ouvidoria:', error);
        alert('Erro ao enviar mensagem. Por favor, tente novamente.');
    }
}

/**
 * Load user's own chats (if logged in)
 */
async function loadMyChats() {
    const section = document.getElementById('my-chats-section');
    const container = document.getElementById('my-chats-list');
    
    if (!section || !container) return;
    
    // Check if user is logged in
    if (typeof isUserLoggedIn !== 'function' || !isUserLoggedIn()) {
        section.style.display = 'none';
        return;
    }
    
    try {
        section.style.display = 'block';
        container.innerHTML = '<p style="color: #666; text-align: center;">Carregando suas conversas...</p>';
        
        const response = await fetch('/api/ouvidoria.php?action=my-chats');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar conversas');
        }
        
        const chats = data.data;
        
        if (!chats || chats.length === 0) {
            container.innerHTML = '<p style="color: #666; text-align: center;">Você ainda não possui conversas na ouvidoria.</p>';
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        chats.forEach(chat => {
            const statusMap = {
                'pendente': { color: '#ffc107', label: 'Pendente' },
                'em_atendimento': { color: '#17a2b8', label: 'Em Atendimento' },
                'resolvido': { color: '#28a745', label: 'Resolvido' }
            };
            
            const status = statusMap[chat.status] || statusMap['pendente'];
            const createdDate = new Date(chat.created_at).toLocaleString('pt-BR');
            
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; cursor: pointer; transition: box-shadow 0.3s;" 
                     onclick="viewChatDetails('${chat.protocol_number}')">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">
                                📝 ${chat.protocol_number}
                                <span style="background: ${status.color}; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; margin-left: 10px;">${status.label}</span>
                            </h3>
                            <p style="color: #666; margin: 0 0 5px 0;"><strong>Assunto:</strong> ${chat.subject}</p>
                            <p style="color: #999; margin: 0; font-size: 0.9rem;">Criado em: ${createdDate}</p>
                            ${chat.has_response ? '<p style="color: #28a745; margin: 5px 0 0 0; font-size: 0.9rem;">✅ Respondido</p>' : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading my chats:', error);
        container.innerHTML = '<p style="color: #dc3545; text-align: center;">Erro ao carregar conversas.</p>';
    }
}

/**
 * View chat details by protocol number
 */
function viewChatDetails(protocolNumber) {
    // Auto-fill the protocol number and scroll to the consult section
    const protocolInput = document.getElementById('protocol-number');
    if (protocolInput) {
        protocolInput.value = protocolNumber;
        protocolInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Auto-trigger the consult
        setTimeout(() => {
            consultProtocol();
        }, 500);
    }
}

async function consultProtocol() {
    const protocolNumber = document.getElementById('protocol-number').value.trim();
    const resultDiv = document.getElementById('protocol-result');
    
    if (!protocolNumber) {
        alert('Por favor, informe o número do protocolo.');
        return;
    }
    
    try {
        resultDiv.innerHTML = '<p style="color: #666; text-align: center;">Consultando...</p>';
        
        const response = await fetch(`/api/ouvidoria.php?action=by-protocol&protocol=${encodeURIComponent(protocolNumber)}`);
        const data = await response.json();
        
        if (!data.success) {
            resultDiv.innerHTML = `
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; text-align: center;">
                    ❌ Protocolo não encontrado
                </div>
            `;
            return;
        }
        
        const message = data.data;
        
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
                    <strong>Protocolo:</strong> ${message.protocol_number}
                </div>
                <div style="margin-bottom: 15px;">
                    <strong>Status:</strong>
                    <span style="padding: 5px 12px; background: ${statusInfo.color}; color: ${statusInfo.textColor}; border-radius: 20px; font-size: 0.85rem; margin-left: 10px;">
                        ${statusInfo.label}
                    </span>
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
        
    } catch (error) {
        console.error('Error consulting protocol:', error);
        resultDiv.innerHTML = `
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; text-align: center;">
                ❌ Erro ao consultar protocolo
            </div>
        `;
    }
}
