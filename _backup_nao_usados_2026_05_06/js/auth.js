/**
 * Sistema de Autenticação
 * Gerencia tokens, sessões e dados de usuário
 */

const Auth = (() => {
    // Constantes
    const TOKEN_KEY = 'token';
    const USER_KEY = 'user_data';
    const EXPIRY_KEY = 'token_expiry';
    const API_URL = window.location.origin + '/Cantina/api';
    
    /**
     * Fazer login
     */
    async function login(username, password) {
        try {
            const response = await fetch(API_URL + '/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            
            const data = await response.json();
            
            if (!data.sucesso) {
                return {
                    sucesso: false,
                    mensagem: data.mensagem || 'Falha ao fazer login'
                };
            }
            
            // Armazenar token e dados do usuário
            if (data.token) {
                localStorage.setItem(TOKEN_KEY, data.token);
                
                // Armazenar dados do usuário
                if (data.usuario) {
                    localStorage.setItem(USER_KEY, JSON.stringify(data.usuario));
                    localStorage.setItem('userType', data.usuario.tipo || data.usuario.papel);
                    localStorage.setItem('userId', data.usuario.id || data.usuario.id_usuario);
                    localStorage.setItem('userName', data.usuario.nome || data.usuario.username);
                }
            }
            
            return {
                sucesso: true,
                mensagem: 'Login realizado com sucesso',
                usuario: data.usuario
            };
            
        } catch (erro) {
            console.error('Erro ao fazer login:', erro);
            return {
                sucesso: false,
                mensagem: 'Erro ao conectar: ' + erro.message
            };
        }
    }
    
    /**
     * Fazer logout
     */
    function logout() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem(EXPIRY_KEY);
        localStorage.removeItem('userType');
        localStorage.removeItem('userId');
        localStorage.removeItem('userName');
        
        // Redirecionar para login
        window.location.href = '/Cantina/login.html';
    }
    
    /**
     * Verificar se usuário está autenticado
     */
    function estaAutenticado() {
        const token = localStorage.getItem(TOKEN_KEY);
        const usuario = localStorage.getItem(USER_KEY);
        
        if (!token || !usuario) {
            return false;
        }
        
        // Verificar validade do token (opcional)
        const expiry = localStorage.getItem(EXPIRY_KEY);
        if (expiry && Date.now() > parseInt(expiry)) {
            logout();
            return false;
        }
        
        return true;
    }
    
    /**
     * Obter token
     */
    function obterToken() {
        return localStorage.getItem(TOKEN_KEY);
    }
    
    /**
     * Obter dados do usuário
     */
    function obterUsuario() {
        const user = localStorage.getItem(USER_KEY);
        return user ? JSON.parse(user) : null;
    }
    
    /**
     * Obter tipo/papel do usuário
     */
    function obterTipoUsuario() {
        return localStorage.getItem('userType');
    }
    
    /**
     * Obter ID do usuário
     */
    function obterIdUsuario() {
        return localStorage.getItem('userId');
    }
    
    /**
     * Obter nome do usuário
     */
    function obterNomeUsuario() {
        return localStorage.getItem('userName');
    }
    
    /**
     * Verificar se é administrador
     */
    function ehAdmin() {
        const tipo = obterTipoUsuario();
        return tipo && (tipo.toLowerCase() === 'admin' || 
                       tipo.toLowerCase() === 'gestor' || 
                       tipo.toLowerCase() === 'administrador');
    }
    
    /**
     * Armazenar token manualmente
     */
    function armazenarToken(token, usuarioData = null) {
        localStorage.setItem(TOKEN_KEY, token);
        
        if (usuarioData) {
            localStorage.setItem(USER_KEY, JSON.stringify(usuarioData));
            localStorage.setItem('userType', usuarioData.tipo || usuarioData.papel);
            localStorage.setItem('userId', usuarioData.id || usuarioData.id_usuario);
            localStorage.setItem('userName', usuarioData.nome || usuarioData.username);
        }
    }
    
    /**
     * Renovar token
     */
    async function renovarToken() {
        try {
            const token = obterToken();
            if (!token) return false;
            
            const response = await fetch(API_URL + '/auth.php?action=renovar_token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                }
            });
            
            const data = await response.json();
            
            if (data.sucesso && data.token) {
                localStorage.setItem(TOKEN_KEY, data.token);
                return true;
            }
            
            return false;
            
        } catch (erro) {
            console.error('Erro ao renovar token:', erro);
            return false;
        }
    }
    
    /**
     * Proteger rota - redirecionar se não autenticado
     */
    function protegerRota(tiposPermitidos = null) {
        if (!estaAutenticado()) {
            window.location.href = '/Cantina/login.html';
            return false;
        }
        
        if (tiposPermitidos && Array.isArray(tiposPermitidos)) {
            const tipoUsuario = obterTipoUsuario();
            if (!tiposPermitidos.includes(tipoUsuario)) {
                alert('Acesso negado: você não tem permissão para acessar esta página');
                window.location.href = '/Cantina/';
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Fazer requisição com token
     */
    async function requisicaoAutenticada(url, opcoes = {}) {
        const token = obterToken();
        
        const headers = {
            ...opcoes.headers,
            'Authorization': token ? 'Bearer ' + token : ''
        };
        
        const response = await fetch(url, {
            ...opcoes,
            headers
        });
        
        // Se receber 401, token expirou
        if (response.status === 401) {
            logout();
            return {
                ok: false,
                status: 401,
                json: async () => ({ sucesso: false, mensagem: 'Token expirado' })
            };
        }
        
        return response;
    }
    
    /**
     * Exibir informações do usuário na UI
     */
    function atualizarUIUsuario() {
        const nome = obterNomeUsuario();
        const tipo = obterTipoUsuario();
        
        // Atualizar elementos com classe 'user-name' e 'user-type'
        document.querySelectorAll('.user-name').forEach(el => {
            el.textContent = nome || 'Usuário';
        });
        
        document.querySelectorAll('.user-type').forEach(el => {
            el.textContent = tipo || 'Indefinido';
        });
        
        // Ocultar/mostrar elementos baseado em permissões
        document.querySelectorAll('[data-require-admin]').forEach(el => {
            if (!ehAdmin()) {
                el.style.display = 'none';
            }
        });
    }
    
    /**
     * Validar credenciais
     */
    function validarCredenciais(username, password) {
        const erros = [];
        
        if (!username || username.trim() === '') {
            erros.push('Usuário é obrigatório');
        }
        
        if (!password || password === '') {
            erros.push('Senha é obrigatória');
        }
        
        if (password && password.length < 6) {
            erros.push('Senha deve ter no mínimo 6 caracteres');
        }
        
        return {
            valido: erros.length === 0,
            erros: erros
        };
    }
    
    // API Pública
    return {
        login,
        logout,
        estaAutenticado,
        obterToken,
        obterUsuario,
        obterTipoUsuario,
        obterIdUsuario,
        obterNomeUsuario,
        ehAdmin,
        armazenarToken,
        renovarToken,
        protegerRota,
        requisicaoAutenticada,
        atualizarUIUsuario,
        validarCredenciais
    };
})();

/**
 * Executar ao carregar a página
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        Auth.atualizarUIUsuario();
    });
} else {
    Auth.atualizarUIUsuario();
}
