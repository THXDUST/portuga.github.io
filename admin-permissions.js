// ============================================
// PERMISSIONS MANAGEMENT
// ============================================

// Debounce timeout for user search
let userSearchTimeout;

/**
 * Load permissions list
 */
async function loadPermissions() {
    const container = document.getElementById('permissions-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando permissões...</p>';
        
        const response = await fetch('/api/admin/permissions.php?action=by-resource');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar permissões');
        }
        
        const permissions = data.data;
        
        if (!permissions || Object.keys(permissions).length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhuma permissão encontrada.</p>';
            return;
        }
        
        let html = '';
        
        // Group permissions by resource
        for (const [resource, perms] of Object.entries(permissions)) {
            html += `
                <div class="menu-section" style="margin-bottom: 20px;">
                    <h3 style="color: #e8c13f; margin-bottom: 15px;">📁 ${resource}</h3>
                    <div style="display: grid; gap: 10px;">
            `;
            
            perms.forEach(perm => {
                html += `
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <div>
                            <strong style="color: #333;">${perm.name}</strong>
                            <p style="color: #666; margin: 5px 0 0 0; font-size: 0.9rem;">${perm.description || 'Sem descrição'}</p>
                            <small style="color: #999;">Ação: ${perm.action}</small>
                        </div>
                    </div>
                `;
            });
            
            html += `
                    </div>
                </div>
            `;
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading permissions:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar permissões: ${error.message}</p>`;
    }
}

/**
 * Show add permission modal
 */
function showAddPermissionModal() {
    const modal = document.getElementById('permission-modal');
    const modalTitle = document.getElementById('permission-modal-title');
    const form = document.getElementById('permission-form');
    
    if (modal && modalTitle && form) {
        modalTitle.textContent = 'Adicionar Permissão';
        form.reset();
        document.getElementById('permission-id').value = '';
        modal.style.display = 'block';
    }
}

/**
 * Close permission modal
 */
function closePermissionModal() {
    const modal = document.getElementById('permission-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Save permission
 */
async function savePermission(event) {
    event.preventDefault();
    
    const id = document.getElementById('permission-id').value;
    const name = document.getElementById('permission-name').value;
    const description = document.getElementById('permission-description').value;
    const resource = document.getElementById('permission-resource').value;
    const action = document.getElementById('permission-action').value;
    
    try {
        const url = id ? '/api/admin/permissions.php?action=update' : '/api/admin/permissions.php?action=create';
        const method = id ? 'PUT' : 'POST';
        
        const body = {
            name,
            description,
            resource,
            action
        };
        
        if (id) {
            body.id = parseInt(id);
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar permissão');
        }
        
        alert(data.message || 'Permissão salva com sucesso!');
        closePermissionModal();
        loadPermissions();
        
    } catch (error) {
        console.error('Error saving permission:', error);
        alert('Erro ao salvar permissão: ' + error.message);
    }
}

/**
 * Edit permission
 */
async function editPermission(permissionId) {
    try {
        const response = await fetch('/api/admin/permissions.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar permissão');
        }
        
        const permission = data.data.find(p => p.id === permissionId);
        
        if (!permission) {
            throw new Error('Permissão não encontrada');
        }
        
        const modal = document.getElementById('permission-modal');
        const modalTitle = document.getElementById('permission-modal-title');
        
        if (modal && modalTitle) {
            modalTitle.textContent = 'Editar Permissão';
            document.getElementById('permission-id').value = permission.id;
            document.getElementById('permission-name').value = permission.name;
            document.getElementById('permission-description').value = permission.description || '';
            document.getElementById('permission-resource').value = permission.resource;
            document.getElementById('permission-action').value = permission.action;
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error editing permission:', error);
        alert('Erro ao editar permissão: ' + error.message);
    }
}

/**
 * Delete permission
 */
async function deletePermission(permissionId) {
    if (!confirm('Tem certeza que deseja excluir esta permissão? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/permissions.php?action=delete&id=${permissionId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao excluir permissão');
        }
        
        alert(data.message || 'Permissão excluída com sucesso!');
        loadPermissions();
        
    } catch (error) {
        console.error('Error deleting permission:', error);
        alert('Erro ao excluir permissão: ' + error.message);
    }
}

// ============================================
// ROLES MANAGEMENT
// ============================================

/**
 * Load roles list
 */
async function loadRoles() {
    const container = document.getElementById('roles-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando cargos...</p>';
        
        const response = await fetch('/api/admin/roles.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargos');
        }
        
        const roles = data.data;
        
        if (!roles || roles.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum cargo encontrado.</p>';
            return;
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        roles.forEach(role => {
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">👥 ${role.name}</h3>
                            <p style="color: #666; margin: 0 0 10px 0;">${role.description || 'Sem descrição'}</p>
                            <small style="color: #999;">Usuários com este cargo: ${role.user_count || 0}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            <button class="btn btn-secondary" onclick="editRole(${role.id})">✏️ Editar</button>
                            <button class="btn" onclick="manageRolePermissions(${role.id})">🔐 Permissões</button>
                            <button class="btn btn-danger" onclick="deleteRole(${role.id})">🗑️</button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading roles:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar cargos: ${error.message}</p>`;
    }
}

/**
 * Show add role modal
 */
async function showAddRoleModal() {
    const modal = document.getElementById('role-modal');
    const modalTitle = document.getElementById('role-modal-title');
    const form = document.getElementById('role-form');
    
    if (modal && modalTitle && form) {
        modalTitle.textContent = 'Adicionar Cargo';
        form.reset();
        document.getElementById('role-id').value = '';
        
        // Load permissions for selection
        await loadPermissionsForRole();
        
        modal.style.display = 'block';
    }
}

/**
 * Close role modal
 */
function closeRoleModal() {
    const modal = document.getElementById('role-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Load permissions list for role modal
 */
async function loadPermissionsForRole(selectedPermissions = []) {
    const container = document.getElementById('role-permissions-list');
    if (!container) return;
    
    try {
        const response = await fetch('/api/admin/permissions.php?action=by-resource');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar permissões');
        }
        
        const permissions = data.data;
        let html = '';
        
        for (const [resource, perms] of Object.entries(permissions)) {
            html += `<div style="margin-bottom: 15px;">`;
            html += `<strong style="color: #333; display: block; margin-bottom: 10px;">📁 ${resource}</strong>`;
            
            perms.forEach(perm => {
                const isChecked = selectedPermissions.includes(perm.id);
                html += `
                    <label style="display: flex; align-items: center; margin-bottom: 8px; cursor: pointer;">
                        <input type="checkbox" name="permission-checkbox" value="${perm.id}" 
                               ${isChecked ? 'checked' : ''}
                               style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: #555;">${perm.name} <small style="color: #999;">(${perm.action})</small></span>
                    </label>
                `;
            });
            
            html += `</div>`;
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading permissions:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar permissões</p>`;
    }
}

/**
 * Save role
 */
async function saveRole(event) {
    event.preventDefault();
    
    const id = document.getElementById('role-id').value;
    const name = document.getElementById('role-name').value;
    const description = document.getElementById('role-description').value;
    
    // Get selected permissions
    const checkboxes = document.querySelectorAll('input[name="permission-checkbox"]:checked');
    const permissionIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const url = id ? '/api/admin/roles.php?action=update' : '/api/admin/roles.php?action=create';
        const method = id ? 'PUT' : 'POST';
        
        const body = {
            name,
            description,
            permission_ids: permissionIds
        };
        
        if (id) {
            body.id = parseInt(id);
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar cargo');
        }
        
        alert(data.message || 'Cargo salvo com sucesso!');
        closeRoleModal();
        loadRoles();
        
    } catch (error) {
        console.error('Error saving role:', error);
        alert('Erro ao salvar cargo: ' + error.message);
    }
}

/**
 * Edit role
 */
async function editRole(roleId) {
    try {
        const response = await fetch(`/api/admin/roles.php?action=get&id=${roleId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargo');
        }
        
        const role = data.data;
        const selectedPermissions = role.permissions.map(p => p.id);
        
        const modal = document.getElementById('role-modal');
        const modalTitle = document.getElementById('role-modal-title');
        
        if (modal && modalTitle) {
            modalTitle.textContent = 'Editar Cargo';
            document.getElementById('role-id').value = role.id;
            document.getElementById('role-name').value = role.name;
            document.getElementById('role-description').value = role.description || '';
            
            await loadPermissionsForRole(selectedPermissions);
            
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error editing role:', error);
        alert('Erro ao editar cargo: ' + error.message);
    }
}

/**
 * Manage role permissions (alias for editRole)
 */
function manageRolePermissions(roleId) {
    editRole(roleId);
}

/**
 * Delete role
 */
async function deleteRole(roleId) {
    if (!confirm('Tem certeza que deseja excluir este cargo? Usuários com este cargo perderão suas atribuições.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/roles.php?action=delete&id=${roleId}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao excluir cargo');
        }
        
        alert(data.message || 'Cargo excluído com sucesso!');
        loadRoles();
        
    } catch (error) {
        console.error('Error deleting role:', error);
        alert('Erro ao excluir cargo: ' + error.message);
    }
}

// ============================================
// USERS MANAGEMENT
// ============================================

/**
 * Load users list
 */
async function loadUsers() {
    const container = document.getElementById('users-list');
    if (!container) return;
    
    try {
        container.innerHTML = '<p style="color: #666;">Carregando usuários...</p>';
        
        // Get filters and pagination
        const roleFilter = document.getElementById('user-role-filter')?.value || '';
        const statusFilter = document.getElementById('user-status-filter')?.value || '';
        const searchQuery = document.getElementById('user-search')?.value || '';
        const page = window.currentUserPage || 1;
        const perPage = 50;
        
        // Build query parameters
        const params = new URLSearchParams({
            action: 'list',
            page: page,
            per_page: perPage
        });
        
        if (searchQuery) params.append('search', searchQuery);
        if (roleFilter) params.append('role', roleFilter);
        if (statusFilter) params.append('status', statusFilter);
        
        const response = await fetch(`/api/admin/users.php?${params.toString()}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar usuários');
        }
        
        const users = data.data.users;
        const pagination = data.data.pagination;
        
        if (!users || users.length === 0) {
            container.innerHTML = '<p style="color: #666;">Nenhum usuário encontrado.</p>';
            return;
        }
        
        // Populate role filter if on first page and no search
        if (page === 1 && !searchQuery) {
            populateRoleFilter(users);
        }
        
        let html = '<div style="display: grid; gap: 15px;">';
        
        users.forEach(user => {
            const isActive = user.is_active;
            const statusBadge = isActive 
                ? '<span style="background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">Ativo</span>'
                : '<span style="background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem;">Inativo</span>';
            
            const isHardcoded = user.id < 0;
            const hardcodedBadge = isHardcoded
                ? '<span style="background: #ffc107; color: #333; padding: 3px 8px; border-radius: 4px; font-size: 0.85rem; margin-left: 5px;">Hardcoded</span>'
                : '';
            
            html += `
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3 style="color: #333; margin: 0 0 10px 0;">
                                ${user.full_name} 
                                ${statusBadge}
                                ${hardcodedBadge}
                            </h3>
                            <p style="color: #666; margin: 0 0 5px 0;">📧 ${user.email}</p>
                            <p style="color: #666; margin: 0 0 5px 0;">🏷️ Cargos: ${user.roles || 'Nenhum'}</p>
                            <small style="color: #999;">Cadastrado em: ${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</small>
                        </div>
                        <div style="display: flex; gap: 5px;">
                            ${!isHardcoded ? `
                                <button class="btn btn-secondary" onclick="editUser(${user.id})">✏️ Editar</button>
                                <button class="btn" onclick="manageUserRoles(${user.id})">👥 Cargos</button>
                                <button class="btn ${isActive ? 'btn-danger' : ''}" onclick="toggleUserStatus(${user.id}, ${isActive})">
                                    ${isActive ? '🚫 Desativar' : '✅ Ativar'}
                                </button>
                            ` : '<em style="color: #999;">Usuário do sistema</em>'}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        // Add pagination controls
        if (pagination.total_pages > 1) {
            html += '<div style="margin-top: 20px; text-align: center;">';
            html += `<p style="color: #666; margin-bottom: 10px;">Página ${pagination.page} de ${pagination.total_pages} (Total: ${pagination.total} usuários)</p>`;
            html += '<div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">';
            
            // Previous button
            if (pagination.page > 1) {
                html += `<button class="btn btn-secondary" onclick="loadUsersPage(${pagination.page - 1})">← Anterior</button>`;
            }
            
            // Page numbers (show up to 5 pages around current)
            const startPage = Math.max(1, pagination.page - 2);
            const endPage = Math.min(pagination.total_pages, pagination.page + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const isCurrentPage = i === pagination.page;
                html += `<button class="btn ${isCurrentPage ? '' : 'btn-secondary'}" 
                         onclick="loadUsersPage(${i})" 
                         ${isCurrentPage ? 'disabled' : ''}>
                         ${i}
                         </button>`;
            }
            
            // Next button
            if (pagination.page < pagination.total_pages) {
                html += `<button class="btn btn-secondary" onclick="loadUsersPage(${pagination.page + 1})">Próxima →</button>`;
            }
            
            html += '</div></div>';
        }
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading users:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar usuários: ${error.message}</p>`;
    }
}

/**
 * Load specific page of users
 */
function loadUsersPage(page) {
    window.currentUserPage = page;
    loadUsers();
}

/**
 * Populate role filter dropdown
 */
function populateRoleFilter(users) {
    const select = document.getElementById('user-role-filter');
    if (!select || select.options.length > 1) return; // Already populated
    
    const rolesSet = new Set();
    users.forEach(user => {
        if (user.roles) {
            user.roles.split(', ').forEach(role => rolesSet.add(role));
        }
    });
    
    Array.from(rolesSet).sort().forEach(role => {
        const option = document.createElement('option');
        option.value = role;
        option.textContent = role;
        select.appendChild(option);
    });
}

/**
 * Debounce user search
 */
function debounceUserSearch() {
    clearTimeout(userSearchTimeout);
    userSearchTimeout = setTimeout(() => {
        window.currentUserPage = 1; // Reset to first page on search
        loadUsers();
    }, 300);
}

/**
 * Show add user modal
 */
async function showAddUserModal() {
    const modal = document.getElementById('user-modal');
    const modalTitle = document.getElementById('user-modal-title');
    const form = document.getElementById('user-form');
    
    if (modal && modalTitle && form) {
        modalTitle.textContent = 'Adicionar Usuário';
        form.reset();
        document.getElementById('user-id').value = '';
        document.getElementById('user-password').required = true; // Password required for new users
        
        // Load roles for selection
        await loadRolesForUser();
        
        modal.style.display = 'block';
    }
}

/**
 * Close user modal
 */
function closeUserModal() {
    const modal = document.getElementById('user-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Load roles list for user modal
 */
async function loadRolesForUser(selectedRoles = []) {
    const container = document.getElementById('user-roles-list');
    if (!container) return;
    
    try {
        const response = await fetch('/api/admin/roles.php?action=list');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar cargos');
        }
        
        const roles = data.data;
        let html = '';
        
        roles.forEach(role => {
            const isChecked = selectedRoles.includes(role.id);
            html += `
                <label style="display: flex; align-items: center; margin-bottom: 10px; cursor: pointer;">
                    <input type="checkbox" name="role-checkbox" value="${role.id}" 
                           ${isChecked ? 'checked' : ''}
                           style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                    <div>
                        <strong style="color: #333;">${role.name}</strong>
                        <p style="color: #666; margin: 2px 0 0 0; font-size: 0.85rem;">${role.description || ''}</p>
                    </div>
                </label>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (error) {
        console.error('Error loading roles:', error);
        container.innerHTML = `<p style="color: #dc3545;">Erro ao carregar cargos</p>`;
    }
}

/**
 * Save user
 */
async function saveUser(event) {
    event.preventDefault();
    
    const id = document.getElementById('user-id').value;
    const fullName = document.getElementById('user-full-name').value;
    const email = document.getElementById('user-email').value;
    const password = document.getElementById('user-password').value;
    const isActive = document.getElementById('user-active').checked;
    
    // Get selected roles
    const checkboxes = document.querySelectorAll('input[name="role-checkbox"]:checked');
    const roleIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    try {
        const url = id ? '/api/admin/users.php?action=update' : '/api/admin/users.php?action=create';
        const method = id ? 'PUT' : 'POST';
        
        const body = {
            full_name: fullName,
            email,
            is_active: isActive,
            role_ids: roleIds
        };
        
        if (id) {
            body.id = parseInt(id);
            if (password) {
                body.password = password; // Only include if provided
            }
        } else {
            // Password required for new users
            if (!password) {
                alert('Senha é obrigatória para novos usuários');
                return;
            }
            body.password = password;
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao salvar usuário');
        }
        
        alert(data.message || 'Usuário salvo com sucesso!');
        closeUserModal();
        loadUsers();
        
    } catch (error) {
        console.error('Error saving user:', error);
        alert('Erro ao salvar usuário: ' + error.message);
    }
}

/**
 * Edit user
 */
async function editUser(userId) {
    try {
        const response = await fetch(`/api/admin/users.php?action=get&id=${userId}`);
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || 'Erro ao carregar usuário');
        }
        
        const user = data.data;
        const selectedRoles = user.roles.map(r => r.id);
        
        const modal = document.getElementById('user-modal');
        const modalTitle = document.getElementById('user-modal-title');
        const passwordField = document.getElementById('user-password');
        
        if (modal && modalTitle) {
            modalTitle.textContent = 'Editar Usuário';
            document.getElementById('user-id').value = user.id;
            document.getElementById('user-full-name').value = user.full_name;
            document.getElementById('user-email').value = user.email;
            document.getElementById('user-active').checked = user.is_active;
            
            // Password not required when editing
            if (passwordField) {
                passwordField.value = '';
                passwordField.required = false;
            }
            
            await loadRolesForUser(selectedRoles);
            
            modal.style.display = 'block';
        }
        
    } catch (error) {
        console.error('Error editing user:', error);
        alert('Erro ao editar usuário: ' + error.message);
    }
}

/**
 * Manage user roles (alias for editUser)
 */
function manageUserRoles(userId) {
    editUser(userId);
}

/**
 * Toggle user status (activate/deactivate)
 */
async function toggleUserStatus(userId, currentStatus) {
    const action = currentStatus ? 'desativar' : 'ativar';
    
    if (!confirm(`Tem certeza que deseja ${action} este usuário?`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/admin/users.php?action=update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: userId,
                is_active: !currentStatus
            })
        });
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.error || `Erro ao ${action} usuário`);
        }
        
        alert(data.message || `Usuário ${action === 'ativar' ? 'ativado' : 'desativado'} com sucesso!`);
        loadUsers();
        
    } catch (error) {
        console.error('Error toggling user status:', error);
        alert(`Erro ao ${action} usuário: ` + error.message);
    }
}
