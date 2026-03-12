(function () {
  const moeda = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  let chartV = null;
  let chartP = null;
  let cacheResumoVendas = [];
  let cacheResumoProducao = [];
  let cacheDetalhes = [];
  let cacheCaixa = [];

  async function fetchJson(url) {
    const response = await fetch(url, { cache: 'no-store' });
    return response.json();
  }

  function getVal(id) {
    const el = document.getElementById(id);
    return el ? el.value : '';
  }

  function toCsv(rows, headers) {
    const esc = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const head = headers.map(esc).join(',');
    const body = rows.map((row) => row.map(esc).join(',')).join('\n');
    return head + '\n' + body;
  }

  function downloadCsv(filename, csv) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  function renderChartVendas(dados) {
    if (!window.Chart) return;

    const canvas = document.getElementById('chartVendas');
    if (!canvas) return;

    const labels = dados.map((d) => d.dia);
    const pedidos = dados.map((d) => Number(d.pedidos || 0));
    const totais = dados.map((d) => Number(d.total || 0));

    if (chartV) chartV.destroy();

    chartV = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Pedidos', data: pedidos, backgroundColor: '#ffd4c4', borderColor: '#ff7a45' },
          { label: 'Total (R$)', data: totais, type: 'line', yAxisID: 'y1', borderColor: '#ff7a45' }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: { beginAtZero: true },
          y1: { beginAtZero: true, position: 'right' }
        }
      }
    });
  }

  function renderChartProducao(dados) {
    if (!window.Chart) return;

    const canvas = document.getElementById('chartProducao');
    if (!canvas) return;

    const top = dados.slice(0, 10);
    const labels = top.map((d) => d.nome);
    const quantidades = top.map((d) => Number(d.quantidade || 0));

    if (chartP) chartP.destroy();

    chartP = new Chart(canvas, {
      type: 'bar',
      data: {
        labels,
        datasets: [{ label: 'Quantidade', data: quantidades, backgroundColor: '#ff9f7a' }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: { x: { beginAtZero: true } }
      }
    });
  }

  async function carregarProdutos() {
    const data = await fetchJson('api/produtos.php?action=listar');
    if (!data.sucesso) return [];
    return data.dados || [];
  }

  async function carregarUsuarios() {
    const data = await fetchJson('api/relatorios.php?action=usuarios_listar');
    if (!data.sucesso) return [];
    return data.dados || [];
  }

  function popularSelect(id, items, valueKey, labelKey) {
    const select = document.getElementById(id);
    if (!select) return;

    select.innerHTML = '<option value="">Todos</option>' +
      items.map((item) => `<option value="${item[valueKey]}">${item[labelKey]}</option>`).join('');
  }

  async function initFiltros() {
    try {
      const [produtos, usuarios] = await Promise.all([carregarProdutos(), carregarUsuarios()]);
      popularSelect('filtroProducao', produtos, 'id', 'nome');
      popularSelect('filtroProdutoDetalhes', produtos, 'id', 'nome');
      popularSelect('filtroAtendente', usuarios, 'id', 'nome');
      popularSelect('filtroUsuarioCaixa', usuarios, 'id', 'nome');
    } catch (_) {
      // Não bloqueia a tela se filtros auxiliares falharem.
    }
  }

  async function loadResumoVendas() {
    const inicio = getVal('inicioVendas');
    const fim = getVal('fimVendas');

    const queryDatas = inicio && fim ? `&inicio=${inicio}&fim=${fim}` : '';
    const url = `api/relatorios.php?action=vendas_resumo${queryDatas}`;
    const data = await fetchJson(url);

    if (!data.sucesso) {
      alert(data.mensagem || 'Falha no resumo de vendas');
      return;
    }

    cacheResumoVendas = data.dados || [];

    const tbody = document.getElementById('tbResumoV');
    if (tbody) {
      tbody.innerHTML = cacheResumoVendas
        .map((r) => `<tr><td>${r.dia}</td><td>${r.pedidos}</td><td>${moeda(r.total)}</td></tr>`)
        .join('');
    }

    renderChartVendas(cacheResumoVendas);
  }

  async function loadResumoProducao() {
    const inicio = getVal('inicioProducao');
    const fim = getVal('fimProducao');
    const produtoId = getVal('filtroProducao');

    const params = [];
    if (inicio && fim) {
      params.push(`inicio=${inicio}`);
      params.push(`fim=${fim}`);
    }
    if (produtoId) params.push(`produto_id=${produtoId}`);

    const url = 'api/relatorios.php?action=producao_resumo' + (params.length ? '&' + params.join('&') : '');
    const data = await fetchJson(url);

    if (!data.sucesso) {
      alert(data.mensagem || 'Falha no resumo de produção');
      return;
    }

    cacheResumoProducao = data.dados || [];

    const tbody = document.getElementById('tbResumoP');
    if (tbody) {
      tbody.innerHTML = cacheResumoProducao
        .map((r) => `<tr><td>${r.nome}</td><td>${r.quantidade}</td></tr>`)
        .join('');
    }

    renderChartProducao(cacheResumoProducao);
  }

  async function loadResumoDetalhes() {
    const inicio = getVal('inicioDetalhes');
    const fim = getVal('fimDetalhes');
    const produtoId = getVal('filtroProdutoDetalhes');
    const atendenteId = getVal('filtroAtendente');

    const params = [];
    if (inicio && fim) {
      params.push(`inicio=${inicio}`);
      params.push(`fim=${fim}`);
    }
    if (produtoId) params.push(`produto_id=${produtoId}`);
    if (atendenteId) params.push(`atendente_id=${atendenteId}`);

    const url = 'api/relatorios.php?action=vendas_detalhes' + (params.length ? '&' + params.join('&') : '');
    const data = await fetchJson(url);

    if (!data.sucesso) {
      alert(data.mensagem || 'Falha nos detalhes de vendas');
      return;
    }

    cacheDetalhes = data.dados || [];

    const tbody = document.getElementById('tbDetalhes');
    if (tbody) {
      tbody.innerHTML = cacheDetalhes
        .map((r) => `<tr>
          <td>#${r.pedido}</td>
          <td>${r.data}</td>
          <td>${r.atendente || ''}</td>
          <td>${r.produto}</td>
          <td>${r.quantidade}</td>
          <td>${moeda(r.subtotal)}</td>
        </tr>`)
        .join('');
    }
  }

  async function loadRelatorioCaixa() {
    const inicio = getVal('inicioCaixa');
    const fim = getVal('fimCaixa');
    const usuarioId = getVal('filtroUsuarioCaixa');

    const params = [];
    if (inicio && fim) {
      params.push(`inicio=${inicio}`);
      params.push(`fim=${fim}`);
    }
    if (usuarioId) params.push(`usuario_id=${usuarioId}`);

    const url = 'api/relatorios.php?action=logins_caixa' + (params.length ? '&' + params.join('&') : '');
    const data = await fetchJson(url);

    if (!data.sucesso) {
      alert(data.mensagem || 'Falha no relatório de caixa');
      return;
    }

    cacheCaixa = data.dados || [];

    const tbody = document.getElementById('tbCaixa');
    if (tbody) {
      tbody.innerHTML = cacheCaixa
        .map((r) => `<tr>
          <td>#${r.sessao_id}</td>
          <td>${r.nome || r.login || ''}</td>
          <td>${r.abertura_caixa || ''}</td>
          <td>${r.fechamento_caixa || '-'}</td>
          <td>${r.status === 'aberto' ? 'Aberto' : 'Fechado'}</td>
          <td>${r.total_vendas || 0}</td>
          <td>${moeda(r.valor_total)}</td>
        </tr>`)
        .join('');
    }
  }

  function exportarVendasCSV() {
    const rows = cacheResumoVendas.map((r) => [r.dia, r.pedidos, Number(r.total || 0).toFixed(2)]);
    downloadCsv('resumo_vendas.csv', toCsv(rows, ['Data', 'Total Vendas', 'Valor Total']));
  }

  function exportarProducaoCSV() {
    const rows = cacheResumoProducao.map((r) => [r.nome, r.quantidade]);
    downloadCsv('producao_por_produto.csv', toCsv(rows, ['Produto', 'Quantidade Vendida']));
  }

  function exportarDetalhesCSV() {
    const rows = cacheDetalhes.map((r) => [r.pedido, r.data, r.atendente || '', r.produto, r.quantidade, Number(r.subtotal || 0).toFixed(2)]);
    downloadCsv('detalhes_vendas.csv', toCsv(rows, ['Numero', 'Data', 'Atendente', 'Produto', 'Qtd', 'Valor']));
  }

  function exportarCaixaCSV() {
    const rows = cacheCaixa.map((r) => [
      r.sessao_id,
      r.nome || r.login || '',
      r.abertura_caixa || '',
      r.fechamento_caixa || '',
      r.status || '',
      r.total_vendas || 0,
      Number(r.valor_total || 0).toFixed(2)
    ]);
    downloadCsv('logins_abertura_caixa.csv', toCsv(rows, ['Sessao', 'Usuario', 'Abertura', 'Fechamento', 'Status', 'Vendas', 'Total']));
  }

  window.loadResumoVendas = loadResumoVendas;
  window.loadResumoProducao = loadResumoProducao;
  window.loadResumoDetalhes = loadResumoDetalhes;
  window.loadRelatorioCaixa = loadRelatorioCaixa;
  window.exportarVendasCSV = exportarVendasCSV;
  window.exportarProducaoCSV = exportarProducaoCSV;
  window.exportarDetalhesCSV = exportarDetalhesCSV;
  window.exportarCaixaCSV = exportarCaixaCSV;

  document.addEventListener('DOMContentLoaded', () => {
    initFiltros();
  });
})();