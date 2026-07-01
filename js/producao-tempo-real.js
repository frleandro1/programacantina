let refreshIntervalId = null;

function formatarMoeda(valor) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(Number(valor) || 0);
}

function dataLocalISO() {
  const agora = new Date();
  const ano = agora.getFullYear();
  const mes = String(agora.getMonth() + 1).padStart(2, '0');
  const dia = String(agora.getDate()).padStart(2, '0');
  return `${ano}-${mes}-${dia}`;
}

function atualizarHorarioAtualizacao() {
  const el = document.getElementById('ultimaAtualizacao');
  if (!el) return;
  el.textContent = new Date().toLocaleTimeString('pt-BR');
}

async function carregarDadosHoje() {
  const hoje = dataLocalISO();

  const [resProducao, resProdutos] = await Promise.all([
    fetch(`api/relatorios.php?action=producao_resumo&inicio=${hoje}&fim=${hoje}`, { cache: 'no-store' }),
    fetch('api/produtos.php?action=listar', { cache: 'no-store' })
  ]);

  const dadosProducao = await resProducao.json();
  const dadosProdutos = await resProdutos.json();

  const producao = (dadosProducao.dados || []).map((item) => ({
    id: Number(item.id || 0),
    nome: item.nome || 'Sem nome',
    quantidade: Number(item.quantidade || 0),
    total: Number(item.total || 0)
  }));

  const estoque = (dadosProdutos.dados || dadosProdutos.produtos || []).map((item) => ({
    id: Number(item.id || 0),
    nome: item.nome || 'Sem nome',
    categoria: item.categoria || 'Outro',
    estoque: Number(item.estoque || 0)
  }));

  return { producao, estoque };
}

function combinarProducaoComEstoque(producao, estoque) {
  const mapaEstoque = new Map(estoque.map((p) => [p.id, p]));

  return producao.map((p) => {
    const itemEstoque = mapaEstoque.get(p.id);
    return {
      id: p.id,
      nome: p.nome,
      quantidade: p.quantidade,
      total: p.total,
      categoria: itemEstoque ? itemEstoque.categoria : 'Outro',
      estoqueAtual: itemEstoque ? itemEstoque.estoque : 0
    };
  }).sort((a, b) => b.quantidade - a.quantidade);
}

function classeRiscoEstoque(estoqueAtual) {
  if (estoqueAtual <= 0) return 'risco-alto';
  if (estoqueAtual <= 5) return 'risco-medio';
  return 'risco-ok';
}

function renderTelaTempoReal(listaCombinada) {
  const tbody = document.getElementById('producaoTempoRealBody');
  if (!tbody) return;

  if (listaCombinada.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Sem produção registrada hoje.</td></tr>';
    return;
  }

  tbody.innerHTML = listaCombinada.map((item, indice) => {
    const classeRisco = classeRiscoEstoque(item.estoqueAtual);
    return `
      <tr>
        <td>${indice + 1}</td>
        <td><strong>${item.nome}</strong></td>
        <td>${item.categoria}</td>
        <td>${item.quantidade}</td>
        <td>${formatarMoeda(item.total)}</td>
        <td>
          <span class="badge ${classeRisco}">${item.estoqueAtual}</span>
          <button class="btn-detalhes" onclick="abrirSegundaTela(${item.id})">Abrir tela</button>
        </td>
      </tr>
    `;
  }).join('');
}

function atualizarCardsTempoReal(listaCombinada) {
  const totalProduzido = listaCombinada.reduce((acc, item) => acc + item.quantidade, 0);
  const totalFaturado = listaCombinada.reduce((acc, item) => acc + item.total, 0);
  const produtosEmRisco = listaCombinada.filter((item) => item.estoqueAtual <= 5).length;

  const elProduzido = document.getElementById('cardTotalProduzido');
  const elFaturado = document.getElementById('cardTotalFaturado');
  const elRisco = document.getElementById('cardProdutosRisco');

  if (elProduzido) elProduzido.textContent = String(totalProduzido);
  if (elFaturado) elFaturado.textContent = formatarMoeda(totalFaturado);
  if (elRisco) elRisco.textContent = String(produtosEmRisco);
}

function abrirSegundaTela(produtoId) {
  const query = produtoId ? `?produtoId=${encodeURIComponent(produtoId)}` : '';
  window.location.href = `producao-estoque.html${query}`;
}

function renderTelaSegunda(producao, estoque) {
  const params = new URLSearchParams(window.location.search);
  const produtoIdSelecionado = Number(params.get('produtoId') || 0);

  const tbodyProducao = document.getElementById('producaoDiaBody');
  const tbodyEstoque = document.getElementById('estoqueAtualBody');

  if (tbodyProducao) {
    const lista = [...producao].sort((a, b) => b.quantidade - a.quantidade);
    if (lista.length === 0) {
      tbodyProducao.innerHTML = '<tr><td colspan="4" class="empty">Nenhuma produção hoje.</td></tr>';
    } else {
      tbodyProducao.innerHTML = lista.map((item) => {
        const destaque = produtoIdSelecionado > 0 && item.id === produtoIdSelecionado ? 'destaque' : '';
        return `
          <tr class="${destaque}">
            <td>${item.nome}</td>
            <td>${item.quantidade}</td>
            <td>${formatarMoeda(item.total)}</td>
            <td><button class="btn-mini" onclick="filtrarPorProduto(${item.id})">Filtrar no estoque</button></td>
          </tr>
        `;
      }).join('');
    }
  }

  if (tbodyEstoque) {
    const lista = [...estoque].sort((a, b) => a.nome.localeCompare(b.nome));
    if (lista.length === 0) {
      tbodyEstoque.innerHTML = '<tr><td colspan="4" class="empty">Sem produtos cadastrados.</td></tr>';
    } else {
      tbodyEstoque.innerHTML = lista.map((item) => {
        const destaque = produtoIdSelecionado > 0 && item.id === produtoIdSelecionado ? 'destaque' : '';
        const classe = classeRiscoEstoque(item.estoque);
        return `
          <tr class="${destaque}">
            <td>${item.nome}</td>
            <td>${item.categoria}</td>
            <td><span class="badge ${classe}">${item.estoque}</span></td>
            <td>${item.estoque <= 5 ? 'Repor' : 'Ok'}</td>
          </tr>
        `;
      }).join('');
    }
  }

  const totalItensProduzidos = producao.reduce((acc, item) => acc + item.quantidade, 0);
  const totalEstoque = estoque.reduce((acc, item) => acc + item.estoque, 0);
  const elProd = document.getElementById('resumoTotalProduzido');
  const elEstoque = document.getElementById('resumoTotalEstoque');

  if (elProd) elProd.textContent = String(totalItensProduzidos);
  if (elEstoque) elEstoque.textContent = String(totalEstoque);
}

function filtrarPorProduto(produtoId) {
  const params = new URLSearchParams(window.location.search);
  params.set('produtoId', String(produtoId));
  window.location.search = params.toString();
}

async function atualizarPagina() {
  try {
    const { producao, estoque } = await carregarDadosHoje();

    if (document.getElementById('producaoTempoRealBody')) {
      const lista = combinarProducaoComEstoque(producao, estoque);
      renderTelaTempoReal(lista);
      atualizarCardsTempoReal(lista);
    }

    if (document.getElementById('producaoDiaBody') || document.getElementById('estoqueAtualBody')) {
      renderTelaSegunda(producao, estoque);
    }

    atualizarHorarioAtualizacao();
  } catch (error) {
    console.error('Falha ao carregar produção em tempo real:', error);
    const blocos = document.querySelectorAll('.error-msg');
    blocos.forEach((el) => {
      el.style.display = 'block';
      el.textContent = 'Erro ao carregar dados em tempo real.';
    });
  }
}

function iniciarAtualizacaoTempoReal() {
  if (refreshIntervalId) {
    clearInterval(refreshIntervalId);
  }

  refreshIntervalId = setInterval(() => {
    if (document.hidden) return;
    atualizarPagina();
  }, 5000);
}

function garantirAutenticacao() {
  const usuario = localStorage.getItem('usuario');
  if (!usuario) {
    window.location.href = 'login.html';
    return false;
  }
  return true;
}

function logout() {
  localStorage.clear();
  window.location.href = 'login.html';
}

document.addEventListener('DOMContentLoaded', () => {
  if (!garantirAutenticacao()) return;

  const btnAbrirSegundaTela = document.getElementById('abrirSegundaTela');
  if (btnAbrirSegundaTela) {
    btnAbrirSegundaTela.addEventListener('click', () => abrirSegundaTela());
  }

  const btnVoltar = document.getElementById('btnVoltarTela');
  if (btnVoltar) {
    btnVoltar.addEventListener('click', () => {
      window.location.href = 'producao-tempo-real.html';
    });
  }

  atualizarPagina();
  iniciarAtualizacaoTempoReal();

  window.addEventListener('beforeunload', () => {
    if (refreshIntervalId) {
      clearInterval(refreshIntervalId);
    }
  });

  const sair = document.getElementById('linkSair');
  if (sair) {
    sair.addEventListener('click', (ev) => {
      ev.preventDefault();
      logout();
    });
  }
});
