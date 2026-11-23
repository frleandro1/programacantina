let produtos = [];
let produtosFiltrados = [];
let vendas = [];
let carregandoVendas = false;
let atualizacaoIntervalo = null;
let exibirHistoricoVendas = false;
const ESTOQUE_SYNC_KEY = 'cantina:estoqueAtualizadoEm';
const syncChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('cantina-sync') : null;

function obterContextoUsuario() {
  const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
  const rawType = String(localStorage.getItem('userType') || usuario.tipo || 'vendedor');
  const userType = window.CantinaSistema ? window.CantinaSistema.normalizarTipoUsuario(rawType) : 'vendedor';
  const userId = Number(usuario.id_usuario || usuario.id || 0) || 0;
  return { userType, userId };
}

function formatarMoeda(valor) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(Number(valor) || 0);
}

function atualizarUiModoVendas() {
  const titulo = document.getElementById('tituloVendas');
  const btnModo = document.getElementById('btnModoVendas');

  if (titulo) {
    titulo.textContent = exibirHistoricoVendas ? 'Últimas Vendas (Histórico)' : 'Últimas Vendas (Hoje)';
  }

  if (btnModo) {
    btnModo.textContent = exibirHistoricoVendas ? 'Ver Hoje' : 'Ver Histórico';
  }
}

function alternarModoVendas() {
  exibirHistoricoVendas = !exibirHistoricoVendas;
  atualizarUiModoVendas();
  carregarVendas();
}

async function carregarDados() {
  await carregarProdutos();
}

async function carregarProdutos() {
  try {
    const res = await fetch('api/produtos.php?action=listar&t=' + Date.now(), { cache: 'no-store' });
    const data = await res.json();
    console.log('Resposta produtos:', data);
    
    if (data.sucesso) {
      produtos = data.dados || data.produtos || [];
      produtos = produtos.map(p => ({
        id: p.id,
        nome: p.nome,
        categoria: p.categoria || 'Outro',
        preco: p.preco,
        estoque: p.estoque
      }));
      produtosFiltrados = produtos;
      renderProdutos();
    }
  } catch (err) {
    console.error('Erro ao carregar produtos:', err);
  }
}

async function carregarVendas() {
  if (carregandoVendas) return;
  carregandoVendas = true;

  try {
    const { userType, userId } = obterContextoUsuario();
    const params = new URLSearchParams({
      action: 'listar',
      agrupar_venda: '1',
      somente_hoje: exibirHistoricoVendas ? '0' : '1',
      t: String(Date.now())
    });
    if (userType !== 'admin' && userId > 0) {
      params.set('usuario_id', String(userId));
    }

    const res = await fetch('api/vendas.php?' + params.toString(), { cache: 'no-store' });
    const data = await res.json();
    
    if (data.sucesso) {
      const lista = data.vendas || data.dados || [];
      vendas = lista.map(v => ({
        id_venda: v.id_venda || v.id || v.numero_pedido,
        numero_exibicao: v.numero_venda_turno || v.numero_venda_dia || v.id_venda || v.id || v.numero_pedido,
        data_hora: v.data_hora || v.data_venda || v.data || null,
        usuario: v.usuario || v.vendedor || 'N/A',
        valor_total: v.valor_total || v.total || 0
      })).slice(0, 10);
      renderVendas();
    }
  } catch (err) {
    console.error('Erro ao carregar vendas:', err);
  } finally {
    carregandoVendas = false;
  }
}

async function carregarEstatisticasHoje() {
  try {
    const hoje = new Date().toISOString().split('T')[0];
    const res = await fetch(`api/relatorios.php?action=vendas_resumo&inicio=${hoje}&fim=${hoje}&t=${Date.now()}`, { cache: 'no-store' });
    const data = await res.json();

    if (!data.sucesso) {
      throw new Error(data.mensagem || 'Falha ao carregar resumo de vendas');
    }

    const resumoHoje = (data.dados || []).find(item => item.dia === hoje) || {
      pedidos: 0,
      total: 0
    };

    document.getElementById('vendasHoje').textContent = Number(resumoHoje.pedidos || 0);
    document.getElementById('totalHoje').textContent = formatarMoeda(resumoHoje.total || 0);
  } catch (err) {
    console.error('Erro ao carregar estatísticas de hoje:', err);
  }
}

function iniciarAtualizacaoTempoReal() {
  if (atualizacaoIntervalo) {
    clearInterval(atualizacaoIntervalo);
  }

  atualizacaoIntervalo = setInterval(async () => {
    if (document.hidden) return;
    await carregarProdutos();
  }, 5000);
}

function registrarSincronizacaoEstoque() {
  const atualizarTudo = async () => {
    await carregarProdutos();
  };

  window.addEventListener('storage', async (event) => {
    if (event.key !== ESTOQUE_SYNC_KEY) return;
    await atualizarTudo();
  });

  if (syncChannel) {
    syncChannel.addEventListener('message', async (event) => {
      if (!event || !event.data || event.data.type !== 'estoque-atualizado') return;
      await atualizarTudo();
    });
  }

  window.addEventListener('focus', async () => {
    await atualizarTudo();
  });
}

function normalizarCategoria(valor) {
  return String(valor || '')
    .trim()
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
}

function filtrarProdutos() {
  const search = document.getElementById('searchProduto').value.toLowerCase();
  const categoria = document.getElementById('filterCategoria').value;
  const categoriaNormalizada = normalizarCategoria(categoria);
  const ehTodos = categoriaNormalizada === '' || categoriaNormalizada === 'todos' || categoriaNormalizada === 'todas';
  
  produtosFiltrados = produtos.filter(p => {
    const matchSearch = p.nome.toLowerCase().includes(search);
    const categoriaProduto = normalizarCategoria(p.categoria);
    const matchCategoria = ehTodos || categoriaProduto === categoriaNormalizada;
    return matchSearch && matchCategoria;
  });
  
  renderProdutos();
}

function renderProdutos() {
  const tbody = document.querySelector('#tableProdutos tbody');
  
  if (produtosFiltrados.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-state"><div class="empty-state-icon"></div><p>Nenhum produto encontrado</p></td></tr>';
    return;
  }
  
  tbody.innerHTML = produtosFiltrados.map(p => `
    <tr>
      <td><strong>${p.nome}</strong></td>
      <td>${p.categoria}</td>
      <td>R$ ${parseFloat(p.preco).toFixed(2)}</td>
      <td><strong>${p.estoque}</strong></td>
      <td>
        <span class="badge ${p.estoque > 0 ? 'badge-success' : 'badge-danger'}">
          ${p.estoque > 0 ? 'Disponível' : 'Sem Estoque'}
        </span>
      </td>
      <td>
        <button class="btn-icon" onclick="editarProduto(${p.id})" title="Editar">✏️ Editar</button>
        <button class="btn-icon" onclick="excluirProduto(${p.id})" title="Excluir" style="color: #dc3545;">🗑️ Excluir</button>
      </td>
    </tr>
  `).join('');
}

function renderVendas() {
  const tbody = document.querySelector('#tableVendas tbody');
  
  if (vendas.length === 0) {
    const mensagem = exibirHistoricoVendas
      ? 'Nenhuma venda registrada no histórico'
      : 'Nenhuma venda registrada hoje';
    tbody.innerHTML = `<tr><td colspan="2" class="empty-state"><div class="empty-state-icon"></div><p>${mensagem}</p></td></tr>`;
    return;
  }
  
  tbody.innerHTML = vendas.map(v => `
    <tr>
      <td><strong>#${v.numero_exibicao}</strong> - ${v.usuario || 'N/A'}</td>
      <td>
        <div class="venda-valor-wrap">
          <strong style="color: var(--brand);">R$ ${parseFloat(v.valor_total).toFixed(2)}</strong>
          <button class="btn-icon btn-lupa-detalhes" onclick="verDetalhes(${v.id_venda})" title="Ver detalhes do cupom" aria-label="Ver detalhes do cupom">🔍</button>
        </div>
      </td>
    </tr>
  `).join('');
}

function montarCupomTexto(venda, idFallback) {
  const numero = venda.numero_exibicao || venda.numero_venda_turno || venda.numero_venda_dia || venda.id_venda || idFallback;
  const dataVenda = venda.data_hora ? new Date(venda.data_hora).toLocaleString('pt-BR') : 'N/A';
  const vendedor = venda.vendedor || 'N/A';
  const itens = Array.isArray(venda.itens) ? venda.itens : [];
  const sep = '-'.repeat(42);

  const linhasItens = itens.length
    ? itens.map((item) => {
        const nome = String(item.produto || 'Produto').trim();
        const qtd = Number(item.quantidade || 0);
        const preco = Number(item.preco_unitario || 0).toFixed(2);
        const subtotal = Number(item.subtotal || 0).toFixed(2);
        return `${nome}\n  ${qtd} x R$ ${preco} = R$ ${subtotal}`;
      }).join('\n')
    : 'Sem itens';

  return [
    'CUPOM DE VENDA',
    sep,
    `Venda: #${numero}`,
    `Ministério: ${vendedor}`,
    `Data: ${dataVenda}`,
    sep,
    'ITENS',
    linhasItens,
    sep,
    `TOTAL: ${formatarMoeda(venda.valor_total || 0)}`,
    sep
  ].join('\n');
}

function verDetalhes(id) {
  carregarDetalhesVenda(id);
}

async function carregarDetalhesVenda(id) {
  try {
    const res = await fetch(`api/vendas.php?action=detalhes&id=${encodeURIComponent(id)}&t=${Date.now()}`, { cache: 'no-store' });
    const data = await res.json();

    if (!data.sucesso || !data.dados) {
      throw new Error(data.mensagem || 'Não foi possível carregar os detalhes da venda');
    }

    const venda = data.dados;
    const cupomCompleto = montarCupomTexto(venda, id);
    alert(cupomCompleto);
  } catch (err) {
    alert('Erro ao carregar detalhes: ' + (err.message || err));
  }
}

function abrirModalProduto(id = null) {
  const modal = document.getElementById('modalProduto');
  const title = document.getElementById('modalTitle');
  
  if (id) {
    const prod = produtos.find(p => p.id === id);
    if (prod) {
      title.textContent = 'Editar Produto';
      document.getElementById('produtoId').value = prod.id;
      document.getElementById('produtoNome').value = prod.nome;
      document.getElementById('produtoCategoria').value = prod.categoria;
      document.getElementById('produtoPreco').value = prod.preco;
      document.getElementById('produtoEstoque').value = prod.estoque;
    }
  } else {
    title.textContent = 'Novo Produto';
    document.getElementById('formProduto').reset();
    document.getElementById('produtoId').value = '';
  }
  
  modal.style.display = 'flex';
}

function fecharModalProduto() {
  document.getElementById('modalProduto').style.display = 'none';
  document.getElementById('formProduto').reset();
}

async function salvarProduto(e) {
  e.preventDefault();
  
  const id = document.getElementById('produtoId').value;
  const dados = {
    nome: document.getElementById('produtoNome').value,
    categoria: document.getElementById('produtoCategoria').value || 'Outro',
    preco: parseFloat(document.getElementById('produtoPreco').value),
    estoque: parseInt(document.getElementById('produtoEstoque').value)
  };
  
  const action = id ? 'atualizar' : 'criar';
  if (id) dados.id = parseInt(id);
  
  try {
    const metodo = id ? 'PUT' : 'POST';
    const res = await fetch(`api/produtos.php?action=${action}`, {
      method: metodo,
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(dados)
    });
    
    const data = await res.json();
    
    if (data.sucesso) {
      const btn = e.target.querySelector('button[type="submit"]');
      btn.textContent = ' Salvo!';
      btn.style.background = '#28a745';
      
      setTimeout(() => {
        fecharModalProduto();
        carregarProdutos();
      }, 800);
    } else {
      alert('Erro: ' + (data.mensagem || 'Erro desconhecido'));
    }
  } catch (err) {
    alert('Erro de conexão: ' + err.message);
  }
}

function editarProduto(id) {
  abrirModalProduto(id);
}

async function excluirProduto(id) {
  const prod = produtos.find(p => p.id === id);
  if (!confirm(`Deseja realmente excluir "${prod.nome}"?`)) return;
  
  try {
    const res = await fetch('api/produtos.php?action=deletar', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    
    const data = await res.json();
    
    if (data.sucesso) {
      carregarProdutos();
    } else {
      alert('Erro: ' + (data.mensagem || 'Erro desconhecido'));
    }
  } catch (err) {
    alert('Erro de conexão: ' + err.message);
  }
}

function logout() {
  localStorage.removeItem('token');
  localStorage.removeItem('usuario');
  localStorage.removeItem('username');
  localStorage.removeItem('userType');
  localStorage.removeItem('sessaoCaixaId');
  localStorage.removeItem('aberturaCaixaEm');
  window.location.href = 'login.html';
}

document.addEventListener('DOMContentLoaded', () => {
  const usuario = localStorage.getItem('usuario');
  if (!usuario) {
    window.location.href = 'login.html';
    return;
  }
  
  carregarDados();
  iniciarAtualizacaoTempoReal();
  registrarSincronizacaoEstoque();

  window.addEventListener('beforeunload', () => {
    if (atualizacaoIntervalo) {
      clearInterval(atualizacaoIntervalo);
    }
  });
  
  window.onclick = (e) => {
    const modal = document.getElementById('modalProduto');
    if (e.target === modal) {
      fecharModalProduto();
    }
  };
});