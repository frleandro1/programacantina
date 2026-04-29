/**
 * Persistence.js - Funções para Persistência de Dados
 * 
 * Integra o frontend com a API REST para salvar e recuperar dados
 */

// ============================================================================
// FUNÇÕES PARA PRODUTOS
// ============================================================================

/**
 * Carregar lista de produtos
 */
async function carregarProdutos() {
    try {
        const response = await fetch('data-api.php?action=produtos_listar&status=ativo');
        const data = await response.json();
        
        if (data.success) {
            produtos = data.produtos;
            atualizarSelectProdutos();
            atualizarTabelaProdutos();
        } else {
            console.error('Erro ao carregar produtos:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

/**
 * Atualizar select de produtos
 */
function atualizarSelectProdutos() {
    const selects = document.querySelectorAll('select[name="produto"]');
    
    selects.forEach(select => {
        const valorAtual = select.value;
        select.innerHTML = '<option value="">Selecione um produto</option>';
        
        produtos.forEach(produto => {
            const option = document.createElement('option');
            option.value = produto.id;
            option.textContent = `${produto.nome} (R$ ${parseFloat(produto.preco_venda).toFixed(2)}) - Estoque: ${produto.estoque}`;
            select.appendChild(option);
        });
        
        select.value = valorAtual;
    });
}

/**
 * Atualizar tabela de produtos
 */
function atualizarTabelaProdutos() {
    const tbody = document.getElementById('estoque-tbody');
    
    if (!tbody) return;
    
    if (produtos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum produto cadastrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = produtos.map(produto => `
        <tr>
            <td><strong>${produto.nome}</strong></td>
            <td>${produto.categoria}</td>
            <td>R$ ${parseFloat(produto.preco_venda).toFixed(2)}</td>
            <td>
                <span class="badge ${produto.estoque < 10 ? 'badge-warning' : 'badge-success'}">
                    ${produto.estoque} un.
                </span>
            </td>
            <td>
                <span class="badge ${produto.status === 'ativo' ? 'badge-success' : 'badge-danger'}">
                    ${produto.status === 'ativo' ? 'Ativo' : 'Inativo'}
                </span>
            </td>
        </tr>
    `).join('');
}

// ============================================================================
// FUNÇÕES PARA VENDAS
// ============================================================================

/**
 * Adicionar produto ao carrinho
 */
async function adicionarAoCarrinho() {
    const produtoSelect = document.getElementById('venda-produto');
    const quantidadeInput = document.getElementById('venda-quantidade');
    
    const produto_id = produtoSelect.value;
    const quantidade = parseInt(quantidadeInput.value) || 0;
    
    if (!produto_id || quantidade <= 0) {
        alert('Selecione um produto e quantidade válida');
        return;
    }
    
    // Buscar dados do produto
    const produto = produtos.find(p => p.id == produto_id);
    
    if (!produto) {
        alert('Produto não encontrado');
        return;
    }
    
    if (quantidade > produto.estoque) {
        alert(`Quantidade indisponível. Estoque: ${produto.estoque}`);
        return;
    }
    
    // Verificar se produto já está no carrinho
    const itemExistente = carrinho.find(item => item.produto_id == produto_id);
    
    if (itemExistente) {
        itemExistente.quantidade += quantidade;
        itemExistente.subtotal = itemExistente.quantidade * itemExistente.preco_unitario;
    } else {
        carrinho.push({
            produto_id: produto_id,
            produto_nome: produto.nome,
            quantidade: quantidade,
            preco_unitario: parseFloat(produto.preco_venda),
            subtotal: quantidade * parseFloat(produto.preco_venda)
        });
    }
    
    // Limpar inputs
    produtoSelect.value = '';
    quantidadeInput.value = '';
    
    // Atualizar carrinho
    atualizarCarrinho();
}

/**
 * Remover item do carrinho
 */
function removerDoCarrinho(index) {
    carrinho.splice(index, 1);
    atualizarCarrinho();
}

/**
 * Atualizar exibição do carrinho
 */
function atualizarCarrinho() {
    const tbody = document.getElementById('carrinho-tbody');
    const totalElement = document.getElementById('carrinho-total');
    const btnFinalizar = document.getElementById('finalizar-venda-btn');
    
    if (!tbody) return;
    
    let total = 0;
    
    if (carrinho.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Carrinho vazio</td></tr>';
        totalElement.textContent = 'R$ 0,00';
        btnFinalizar.disabled = true;
    } else {
        tbody.innerHTML = carrinho.map((item, index) => {
            total += item.subtotal;
            return `
                <tr>
                    <td>${item.produto_nome}</td>
                    <td>${item.quantidade}</td>
                    <td>R$ ${item.preco_unitario.toFixed(2)}</td>
                    <td>R$ ${item.subtotal.toFixed(2)}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="removerDoCarrinho(${index})">
                            ✕ Remover
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
        
        totalElement.textContent = `R$ ${total.toFixed(2)}`;
        btnFinalizar.disabled = false;
    }
}

/**
 * Finalizar venda
 */
async function finalizarVenda() {
    const atendenteInput = document.getElementById('venda-atendente');
    const atendente = atendenteInput.value.trim();
    
    if (!atendente) {
        alert('Digite o nome do atendente');
        return;
    }
    
    if (carrinho.length === 0) {
        alert('Carrinho vazio');
        return;
    }
    
    // Calcular total
    const total = carrinho.reduce((sum, item) => sum + item.subtotal, 0);
    
    try {
        const response = await fetch('data-api.php?action=venda_criar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                atendente: atendente,
                total: total,
                itens: carrinho
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Venda #${data.id} registrada com sucesso!`);
            
            // Limpar carrinho
            carrinho = [];
            atendenteInput.value = '';
            atualizarCarrinho();
            
            // Recarregar dados
            carregarProdutos();
            carregarVendas();
            carregarEstatisticas();
        } else {
            alert('Erro ao registrar venda: ' + data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        alert('Erro ao registrar venda');
    }
}

// ============================================================================
// FUNÇÕES PARA PRODUÇÃO
// ============================================================================

/**
 * Registrar produção
 */
async function registrarProducao() {
    const produtoSelect = document.getElementById('prod-produto');
    const quantidadeInput = document.getElementById('prod-quantidade');
    const responsavelInput = document.getElementById('prod-responsavel');
    
    const produto_id = produtoSelect.value;
    const quantidade = parseInt(quantidadeInput.value) || 0;
    const responsavel = responsavelInput.value.trim();
    
    if (!produto_id || quantidade <= 0 || !responsavel) {
        alert('Preencha todos os campos');
        return;
    }
    
    try {
        const response = await fetch('data-api.php?action=movimentacao_registrar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                produto_id: produto_id,
                tipo: 'entrada',
                quantidade: quantidade,
                responsavel: responsavel,
                motivo: 'Produção registrada'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Produção registrada com sucesso!');
            
            // Limpar inputs
            produtoSelect.value = '';
            quantidadeInput.value = '';
            responsavelInput.value = '';
            
            // Recarregar dados
            carregarProdutos();
            carregarMovimentacoes();
            carregarEstatisticas();
        } else {
            alert('Erro ao registrar produção: ' + data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        alert('Erro ao registrar produção');
    }
}

// ============================================================================
// FUNÇÕES PARA VENDAS (VISUALIZAÇÃO)
// ============================================================================

/**
 * Carregar vendas
 */
async function carregarVendas() {
    try {
        const response = await fetch('data-api.php?action=vendas_listar&limite=50');
        const data = await response.json();
        
        if (data.success) {
            vendas = data.vendas;
            atualizarTabelaVendas();
        } else {
            console.error('Erro ao carregar vendas:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

/**
 * Atualizar tabela de vendas
 */
function atualizarTabelaVendas() {
    const tbody = document.getElementById('vendas-tbody');
    
    if (!tbody) return;
    
    if (vendas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma venda registrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = vendas.map(venda => {
        const data = new Date(venda.data_venda);
        const dataFormatada = data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
        
        return `
            <tr>
                <td>#${venda.id}</td>
                <td>${dataFormatada}</td>
                <td>${venda.atendente}</td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="verDetalhesVenda(${venda.id})">
                        Ver detalhes
                    </button>
                </td>
                <td><strong>R$ ${parseFloat(venda.total).toFixed(2)}</strong></td>
                <td>
                    <button class="btn btn-sm btn-info" onclick="imprimirVenda(${venda.id})">
                        🖨️ Imprimir
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Ver detalhes da venda
 */
async function verDetalhesVenda(vendaId) {
    try {
        const response = await fetch(`data-api.php?action=venda_obter&id=${vendaId}`);
        const data = await response.json();
        
        if (data.success) {
            const venda = data.venda;
            let detalhes = `Venda #${venda.id}\n`;
            detalhes += `Data: ${new Date(venda.data_venda).toLocaleString('pt-BR')}\n`;
            detalhes += `Atendente: ${venda.atendente}\n`;
            detalhes += `\nItens:\n`;
            
            venda.itens.forEach(item => {
                detalhes += `- ${item.produto_nome}: ${item.quantidade}x R$ ${parseFloat(item.preco_unitario).toFixed(2)} = R$ ${parseFloat(item.subtotal).toFixed(2)}\n`;
            });
            
            detalhes += `\nTotal: R$ ${parseFloat(venda.total).toFixed(2)}`;
            
            alert(detalhes);
        } else {
            alert('Erro ao carregar detalhes da venda');
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

/**
 * Imprimir venda
 */
function imprimirVenda(vendaId) {
    alert('Funcionalidade de impressão em desenvolvimento');
}

// ============================================================================
// FUNÇÕES PARA MOVIMENTAÇÕES
// ============================================================================

/**
 * Carregar movimentações
 */
async function carregarMovimentacoes() {
    try {
        const response = await fetch('data-api.php?action=movimentacoes_listar&limite=50');
        const data = await response.json();
        
        if (data.success) {
            movimentacoes = data.movimentacoes;
            atualizarTabelaMovimentacoes();
        } else {
            console.error('Erro ao carregar movimentações:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

/**
 * Atualizar tabela de movimentações
 */
function atualizarTabelaMovimentacoes() {
    const tbody = document.getElementById('movimentacoes-tbody');
    
    if (!tbody) return;
    
    if (movimentacoes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhuma movimentação registrada</td></tr>';
        return;
    }
    
    tbody.innerHTML = movimentacoes.map(mov => {
        const data = new Date(mov.data_movimentacao);
        const dataFormatada = data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
        
        return `
            <tr>
                <td>${mov.produto_nome}</td>
                <td>
                    <span class="badge ${mov.tipo === 'entrada' ? 'badge-success' : 'badge-danger'}">
                        ${mov.tipo === 'entrada' ? '➕ Entrada' : '➖ Saída'}
                    </span>
                </td>
                <td>${mov.quantidade}</td>
                <td>${dataFormatada}</td>
                <td>${mov.responsavel || '-'}</td>
            </tr>
        `;
    }).join('');
}

// ============================================================================
// FUNÇÕES PARA ESTATÍSTICAS
// ============================================================================

/**
 * Carregar estatísticas
 */
async function carregarEstatisticas() {
    try {
        const response = await fetch('data-api.php?action=estatisticas');
        const data = await response.json();
        
        if (data.success) {
            const stats = data.estatisticas;
            
            // Atualizar elementos
            const elementos = {
                'total-vendas': stats.total_vendas,
                'receita-total': `R$ ${parseFloat(stats.receita_total).toFixed(2)}`,
                'estoque-total': stats.estoque_total,
                'ticket-medio': `R$ ${parseFloat(stats.ticket_medio).toFixed(2)}`
            };
            
            for (let [id, valor] of Object.entries(elementos)) {
                const elemento = document.getElementById(id);
                if (elemento) {
                    elemento.textContent = valor;
                }
            }
        } else {
            console.error('Erro ao carregar estatísticas:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================

/**
 * Inicializar persistência
 */
function inicializarPersistencia() {
    // Carregar dados iniciais
    carregarProdutos();
    carregarVendas();
    carregarMovimentacoes();
    carregarEstatisticas();
    
    // Atualizar dados a cada 30 segundos
    setInterval(() => {
        carregarEstatisticas();
        carregarProdutos();
    }, 30000);
}

// Inicializar quando o documento estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarPersistencia);
} else {
    inicializarPersistencia();
}
