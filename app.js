// Variáveis globais
let currentTab = 'overview';
let produtos = [];
let vendas = [];
let movimentacoes = [];
let usuarioAtual = null;
let permissoes = {};

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    carregarProdutos();
    carregarEstatisticas();
    carregarVendas();
    carregarMovimentacoes();
    
    // Event listeners das abas
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            mudarAba(this.dataset.tab);
        });
    });
});

// Funções de Navegação
function mudarAba(tab) {
    currentTab = tab;
    
    // Atualizar abas ativas
    document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
    
    // Mostrar/ocultar conteúdo
    document.querySelectorAll('[data-content]').forEach(content => {
        content.classList.add('hidden');
    });
    document.querySelector(`[data-content="${tab}"]`).classList.remove('hidden');
    
    // Recarregar dados se necessário
    if (tab === 'history') {
        carregarVendas();
    }
}

// Funções de API
async function fazerRequisicao(action, method = 'GET', data = null) {
    try {
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const url = `api.php?action=${action}`;
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erro na requisição:', error);
        mostrarAlerta('Erro ao processar requisição', 'error');
        return null;
    }
}

// Carregar Produtos
async function carregarProdutos() {
    const resultado = await fazerRequisicao('produtos');
    if (resultado) {
        produtos = resultado;
        atualizarSelectProdutos();
    }
}

function atualizarSelectProdutos() {
    const selects = document.querySelectorAll('select[name="produto"]');
    selects.forEach(select => {
        const valorAtual = select.value;
        select.innerHTML = '<option value="">Selecione um produto</option>';
        produtos.forEach(produto => {
            const option = document.createElement('option');
            option.value = produto.id;
            option.textContent = `${produto.nome} (${produto.categoria}) - R$ ${parseFloat(produto.preco_venda).toFixed(2)}`;
            select.appendChild(option);
        });
        select.value = valorAtual;
    });
}

// Carregar Estatísticas
async function carregarEstatisticas() {
    const resultado = await fazerRequisicao('estatisticas');
    if (resultado) {
        document.getElementById('total-vendas').textContent = resultado.total_vendas;
        document.getElementById('receita-total').textContent = `R$ ${resultado.receita_total.toFixed(2)}`;
        document.getElementById('estoque-total').textContent = resultado.estoque_total;
        document.getElementById('ticket-medio').textContent = `R$ ${resultado.ticket_medio.toFixed(2)}`;
    }
}

// Registrar Produção
async function registrarProducao() {
    const produtoId = document.getElementById('prod-produto').value;
    const quantidade = parseInt(document.getElementById('prod-quantidade').value);
    const responsavel = document.getElementById('prod-responsavel').value;
    
    if (!produtoId || !quantidade || !responsavel) {
        mostrarAlerta('Preencha todos os campos obrigatórios', 'error');
        return;
    }
    
    if (quantidade <= 0) {
        mostrarAlerta('Quantidade deve ser maior que zero', 'error');
        return;
    }
    
    const resultado = await fazerRequisicao('registrar_producao', 'POST', {
        produto_id: parseInt(produtoId),
        quantidade: quantidade,
        responsavel: responsavel
    });
    
    if (resultado && resultado.success) {
        mostrarAlerta(`Produção registrada com sucesso! Novo estoque: ${resultado.novo_estoque}`, 'success');
        document.getElementById('prod-produto').value = '';
        document.getElementById('prod-quantidade').value = '';
        document.getElementById('prod-responsavel').value = '';
        carregarEstatisticas();
        carregarMovimentacoes();
    }
}

// Carrinho de Vendas
let carrinho = [];

function adicionarAoCarrinho() {
    const produtoId = document.getElementById('venda-produto').value;
    const quantidade = parseInt(document.getElementById('venda-quantidade').value);
    
    if (!produtoId || !quantidade) {
        mostrarAlerta('Selecione um produto e quantidade', 'error');
        return;
    }
    
    if (quantidade <= 0) {
        mostrarAlerta('Quantidade deve ser maior que zero', 'error');
        return;
    }
    
    const produto = produtos.find(p => p.id == produtoId);
    if (!produto) {
        mostrarAlerta('Produto não encontrado', 'error');
        return;
    }
    
    if (quantidade > produto.estoque) {
        mostrarAlerta(`Estoque insuficiente. Disponível: ${produto.estoque}`, 'error');
        return;
    }
    
    // Verificar se produto já está no carrinho
    const itemExistente = carrinho.find(item => item.produto_id == produtoId);
    if (itemExistente) {
        if (itemExistente.quantidade + quantidade > produto.estoque) {
            mostrarAlerta('Estoque insuficiente para adicionar mais itens', 'error');
            return;
        }
        itemExistente.quantidade += quantidade;
    } else {
        carrinho.push({
            produto_id: parseInt(produtoId),
            produto_nome: produto.nome,
            quantidade: quantidade,
            preco: parseFloat(produto.preco_venda)
        });
    }
    
    document.getElementById('venda-produto').value = '';
    document.getElementById('venda-quantidade').value = '';
    atualizarCarrinho();
    mostrarAlerta(`${produto.nome} adicionado ao carrinho`, 'success');
}

function removerDoCarrinho(index) {
    carrinho.splice(index, 1);
    atualizarCarrinho();
}

function atualizarCarrinho() {
    const tbody = document.getElementById('carrinho-tbody');
    tbody.innerHTML = '';
    
    let total = 0;
    carrinho.forEach((item, index) => {
        const subtotal = item.quantidade * item.preco;
        total += subtotal;
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.produto_nome}</td>
            <td>${item.quantidade}</td>
            <td>R$ ${item.preco.toFixed(2)}</td>
            <td>R$ ${subtotal.toFixed(2)}</td>
            <td>
                <button class="btn btn-small btn-outline" onclick="removerDoCarrinho(${index})">
                    Remover
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    document.getElementById('carrinho-total').textContent = `R$ ${total.toFixed(2)}`;
    document.getElementById('finalizar-venda-btn').disabled = carrinho.length === 0;
}

async function finalizarVenda() {
    const atendente = document.getElementById('venda-atendente').value;
    
    if (!atendente) {
        mostrarAlerta('Preencha o nome do atendente', 'error');
        return;
    }
    
    if (carrinho.length === 0) {
        mostrarAlerta('Adicione produtos ao carrinho', 'error');
        return;
    }
    
    const resultado = await fazerRequisicao('registrar_venda', 'POST', {
        atendente: atendente,
        itens: carrinho
    });
    
    if (resultado && resultado.success) {
        const totalItens = carrinho.reduce((sum, item) => sum + item.quantidade, 0);
        mostrarAlerta(`Venda registrada com sucesso! Total: R$ ${resultado.total.toFixed(2)}`, 'success');
        carrinho = [];
        document.getElementById('venda-atendente').value = '';
        atualizarCarrinho();
        carregarEstatisticas();
        carregarVendas();
    }
}

// Carregar Vendas
async function carregarVendas() {
    const resultado = await fazerRequisicao('vendas');
    if (resultado) {
        vendas = resultado;
        atualizarTabelaVendas();
    }
}

function atualizarTabelaVendas() {
    const tbody = document.getElementById('vendas-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (vendas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center">Nenhuma venda registrada</td></tr>';
        return;
    }
    
    vendas.forEach((venda, index) => {
        const data = new Date(venda.data_venda).toLocaleString('pt-BR');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#${venda.id}</td>
            <td>${data}</td>
            <td>${venda.atendente}</td>
            <td>${venda.itens.reduce((sum, item) => sum + item.quantidade, 0)}</td>
            <td>R$ ${parseFloat(venda.total).toFixed(2)}</td>
            <td>
                <button class="btn btn-small btn-outline" onclick="expandirVenda(${index})">
                    Ver Detalhes
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function expandirVenda(index) {
    const venda = vendas[index];
    let detalhes = '<h4>Itens da Venda:</h4><ul>';
    
    venda.itens.forEach(item => {
        const subtotal = item.quantidade * item.preco_unitario;
        detalhes += `<li>${item.produto_nome}: ${item.quantidade} un × R$ ${parseFloat(item.preco_unitario).toFixed(2)} = R$ ${subtotal.toFixed(2)}</li>`;
    });
    
    detalhes += '</ul>';
    alert(detalhes);
}

// Carregar Movimentações
async function carregarMovimentacoes() {
    const resultado = await fazerRequisicao('movimentacoes');
    if (resultado) {
        movimentacoes = resultado;
        atualizarTabelaMovimentacoes();
    }
}

function atualizarTabelaMovimentacoes() {
    const tbody = document.getElementById('movimentacoes-tbody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (movimentacoes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Nenhuma movimentação registrada</td></tr>';
        return;
    }
    
    movimentacoes.forEach(mov => {
        const data = new Date(mov.data_movimentacao).toLocaleString('pt-BR');
        const tipo = mov.tipo === 'entrada' ? 'Entrada' : 'Saída';
        const badge = mov.tipo === 'entrada' ? 'badge-success' : 'badge-warning';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${mov.produto_nome}</td>
            <td><span class="badge ${badge}">${tipo}</span></td>
            <td>${mov.quantidade}</td>
            <td>${data}</td>
            <td>${mov.responsavel || '-'}</td>
        `;
        tbody.appendChild(tr);
    });
}

// Funções Utilitárias
function mostrarAlerta(mensagem, tipo = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${tipo}`;
    alertDiv.innerHTML = `
        <span>${mensagem}</span>
        <button style="background: none; border: none; cursor: pointer; font-size: 1.2rem;" onclick="this.parentElement.remove()">×</button>
    `;
    
    const container = document.querySelector('.container');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Atualizar dados a cada 30 segundos
setInterval(() => {
    carregarEstatisticas();
    carregarMovimentacoes();
}, 30000);
