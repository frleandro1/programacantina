/**
 * Funções Utilitárias Compartilhadas
 * Ferramentas comuns para o sistema
 */

const Utils = (() => {
    // Constantes
    const MOEDA_LOCALE = 'pt-BR';
    const MOEDA_CURRENCY = 'BRL';
    
    /**
     * Formatar valor como moeda (Real)
     */
    function formatarMoeda(valor) {
        if (valor === null || valor === undefined) valor = 0;
        
        return Number(valor).toLocaleString(MOEDA_LOCALE, {
            style: 'currency',
            currency: MOEDA_CURRENCY
        });
    }
    
    /**
     * Formatar número com 2 casas decimais
     */
    function formatarDecimal(valor, casas = 2) {
        if (valor === null || valor === undefined) valor = 0;
        return Number(valor).toFixed(casas);
    }
    
    /**
     * Formatar data para formato brasileiro (DD/MM/YYYY)
     */
    function formatarData(data, incluirHora = false) {
        if (!data) return '--/--/----';
        
        if (typeof data === 'string') {
            data = new Date(data);
        }
        
        if (!(data instanceof Date) || isNaN(data)) {
            return '--/--/----';
        }
        
        const dia = String(data.getDate()).padStart(2, '0');
        const mes = String(data.getMonth() + 1).padStart(2, '0');
        const ano = data.getFullYear();
        
        let resultado = `${dia}/${mes}/${ano}`;
        
        if (incluirHora) {
            const horas = String(data.getHours()).padStart(2, '0');
            const minutos = String(data.getMinutes()).padStart(2, '0');
            resultado += ` ${horas}:${minutos}`;
        }
        
        return resultado;
    }
    
    /**
     * Formatar data/hora completa
     */
    function formatarDataHora(data) {
        return formatarData(data, true);
    }
    
    /**
     * Obter data de hoje no formato YYYY-MM-DD
     */
    function obterDataAtual() {
        const hoje = new Date();
        const ano = hoje.getFullYear();
        const mes = String(hoje.getMonth() + 1).padStart(2, '0');
        const dia = String(hoje.getDate()).padStart(2, '0');
        
        return `${ano}-${mes}-${dia}`;
    }
    
    /**
     * Calcular diferença de datas em dias
     */
    function diferenBias(dataPrincipal, dataSecundaria = null) {
        const data1 = new Date(dataPrincipal);
        const data2 = dataSecundaria ? new Date(dataSecundaria) : new Date();
        
        const timestamp1 = data1.getTime();
        const timestamp2 = data2.getTime();
        
        const diferenca = Math.floor((timestamp1 - timestamp2) / (1000 * 60 * 60 * 24));
        
        return diferenca;
    }
    
    /**
     * Exibir notificação/toast
     */
    function notificacao(mensagem, tipo = 'info', duracao = 3000) {
        // Criar container se não existir
        let container = document.getElementById('notificacoes-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificacoes-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(container);
        }
        
        // Criar elemento de notificação
        const notif = document.createElement('div');
        const cores = {
            'sucesso': '#27ae60',
            'error': '#c0392b',
            'aviso': '#f39c12',
            'info': '#3498db'
        };
        
        const cor = cores[tipo] || cores['info'];
        
        notif.style.cssText = `
            background: ${cor};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
            min-width: 300px;
        `;
        
        notif.textContent = mensagem;
        container.appendChild(notif);
        
        // Remover após duração
        if (duracao > 0) {
            setTimeout(() => {
                notif.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => notif.remove(), 300);
            }, duracao);
        }
        
        return notif;
    }
    
    /**
     * Exibir mensagem de sucesso
     */
    function sucesso(mensagem, duracao = 3000) {
        return notificacao(mensagem, 'sucesso', duracao);
    }
    
    /**
     * Exibir mensagem de erro
     */
    function erro(mensagem, duracao = 4000) {
        return notificacao(mensagem, 'error', duracao);
    }
    
    /**
     * Exibir mensagem de aviso
     */
    function aviso(mensagem, duracao = 3500) {
        return notificacao(mensagem, 'aviso', duracao);
    }
    
    /**
     * Exibir mensagem de info
     */
    function info(mensagem, duracao = 3000) {
        return notificacao(mensagem, 'info', duracao);
    }
    
    /**
     * Mostrar modal de confirmação
     */
    async function confirmar(mensagem, titulo = 'Confirmação') {
        return new Promise((resolve) => {
            // Criar modal
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9998;
            `;
            
            const conteudo = document.createElement('div');
            conteudo.style.cssText = `
                background: white;
                padding: 30px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 400px;
            `;
            
            const h2 = document.createElement('h2');
            h2.textContent = titulo;
            h2.style.marginTop = '0';
            h2.style.marginBottom = '15px';
            h2.style.color = '#333';
            
            const p = document.createElement('p');
            p.textContent = mensagem;
            p.style.marginBottom = '25px';
            p.style.color = '#666';
            
            const botoes = document.createElement('div');
            botoes.style.display = 'flex';
            botoes.style.gap = '10px';
            botoes.style.justifyContent = 'center';
            
            const btnSim = document.createElement('button');
            btnSim.textContent = 'Sim';
            btnSim.style.cssText = `
                padding: 10px 24px;
                background: #27ae60;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
            `;
            
            const btnNao = document.createElement('button');
            btnNao.textContent = 'Não';
            btnNao.style.cssText = `
                padding: 10px 24px;
                background: #95a5a6;
                color: white;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-weight: 600;
            `;
            
            btnSim.onclick = () => {
                modal.remove();
                resolve(true);
            };
            
            btnNao.onclick = () => {
                modal.remove();
                resolve(false);
            };
            
            botoes.appendChild(btnSim);
            botoes.appendChild(btnNao);
            
            conteudo.appendChild(h2);
            conteudo.appendChild(p);
            conteudo.appendChild(botoes);
            
            modal.appendChild(conteudo);
            document.body.appendChild(modal);
        });
    }
    
    /**
     * Validar email
     */
    function validarEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }
    
    /**
     * Validar telefone (formato brasileiro)
     */
    function validarTelefone(telefone) {
        const regex = /^(?:\(\d{2}\)\s?)?\d{4,5}-?\d{4}$/;
        return regex.test(telefone.replace(/\s/g, ''));
    }
    
    /**
     * Sanitizar entrada (previne XSS)
     */
    function sanitizarHTML(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }
    
    /**
     * Gerar ID único
     */
    function gerarID(prefixo = '') {
        const timestamp = Date.now();
        const random = Math.random().toString(36).substr(2, 9);
        return prefixo ? `${prefixo}-${timestamp}-${random}` : `${timestamp}-${random}`;
    }
    
    /**
     * Fazer cópia para clipboard
     */
    async function copiarParaClipboard(texto) {
        try {
            await navigator.clipboard.writeText(texto);
            sucesso('Copiado para a área de transferência');
            return true;
        } catch (erro) {
            erro('Erro ao copiar: ' + erro.message);
            return false;
        }
    }
    
    /**
     * Fazer download de JSON
     */
    function downloadJSON(dados, nomeArquivo = 'dados.json') {
        const json = JSON.stringify(dados, null, 2);
        const blob = new Blob([json], { type: 'application/json' });
        downloadArquivo(blob, nomeArquivo);
    }
    
    /**
     * Fazer download de CSV
     */
    function downloadCSV(dados, headers, nomeArquivo = 'dados.csv') {
        const escapeCSV = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const linhaHeaders = headers.map(escapeCSV).join(',');
        const linhasDados = dados.map(linha => linha.map(escapeCSV).join(',')).join('\n');
        const csv = linhaHeaders + '\n' + linhasDados;
        
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        downloadArquivo(blob, nomeArquivo);
    }
    
    /**
     * Função auxiliar para download genérico
     */
    function downloadArquivo(blob, nomeArquivo) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = nomeArquivo;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
        sucesso('Arquivo baixado: ' + nomeArquivo);
    }
    
    /**
     * Aguardar (delay)
     */
    function aguardar(ms = 1000) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
    
    /**
     * Verificar conexão de internet
     */
    async function temConexao() {
        try {
            const response = await fetch('/Cantina/api/system-status.php', {
                method: 'HEAD',
                cache: 'no-store'
            });
            return response.ok;
        } catch {
            return false;
        }
    }
    
    /**
     * Adicionar CSS dinamicamente
     */
    function adicionarCSS(url) {
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild(link);
    }
    
    /**
     * Adicionar script dinamicamente
     */
    function adicionarScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = () => resolve(true);
            script.onerror = () => reject(false);
            document.head.appendChild(script);
        });
    }
    
    /**
     * Aplicar animações CSS
     */
    function adicionarAnimacao() {
        if (!document.getElementById('utils-animations')) {
            const style = document.createElement('style');
            style.id = 'utils-animations';
            style.innerHTML = `
                @keyframes slideIn {
                    from {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                    to {
                        transform: translateX(0);
                        opacity: 1;
                    }
                }
                
                @keyframes slideOut {
                    from {
                        transform: translateX(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateX(400px);
                        opacity: 0;
                    }
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
            `;
            document.head.appendChild(style);
        }
    }
    
    // Adicionar animações ao carregar
    adicionarAnimacao();
    
    // API Pública
    return {
        formatarMoeda,
        formatarDecimal,
        formatarData,
        formatarDataHora,
        obterDataAtual,
        diferenBias,
        notificacao,
        sucesso,
        erro,
        aviso,
        info,
        confirmar,
        validarEmail,
        validarTelefone,
        sanitizarHTML,
        gerarID,
        copiarParaClipboard,
        downloadJSON,
        downloadCSV,
        downloadArquivo,
        aguardar,
        temConexao,
        adicionarCSS,
        adicionarScript
    };
})();
