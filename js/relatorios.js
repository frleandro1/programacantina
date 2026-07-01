(function () {
  const moeda = (v) => Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const CUPOM_LARGURA = 46;

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

  function hojeISO() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  function limitarLinha(texto, largura = CUPOM_LARGURA) {
    return String(texto || '').replace(/\r/g, '').replace(/\n/g, ' ').slice(0, Math.max(0, largura));
  }

  function centralizarLinha(texto, largura = CUPOM_LARGURA) {
    const base = limitarLinha(texto, largura);
    const spaces = Math.max(0, Math.floor((largura - base.length) / 2));
    return (' '.repeat(spaces) + base).slice(0, largura);
  }

  function montarLinhaDupla(esq, dir, largura = CUPOM_LARGURA) {
    const direita = limitarLinha(dir, largura);
    const maxEsq = Math.max(1, largura - direita.length - 1);
    const esquerda = limitarLinha(esq, maxEsq);
    const espacos = Math.max(1, largura - esquerda.length - direita.length);
    return esquerda + ' '.repeat(espacos) + direita;
  }

  async function chamarApiImpressao(action, textoCupom) {
    const resp = await fetch(`api/impressora.php?action=${action}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ texto: textoCupom, copias: 1 }),
      cache: 'no-store'
    });

    const body = await resp.text();
    let data = null;
    try {
      data = body ? JSON.parse(body) : null;
    } catch (_) {
      data = null;
    }

    return {
      ok: resp.ok && !!data && data.sucesso === true,
      status: resp.status,
      mensagem: (data && data.mensagem) ? data.mensagem : `Falha na ação ${action} (HTTP ${resp.status})`
    };
  }

  async function imprimirCupomTermico(textoCupom) {
    const direta = await chamarApiImpressao('imprimir', textoCupom);
    if (direta.ok) {
      return { modo: 'direta', mensagem: direta.mensagem };
    }

    const fila = await chamarApiImpressao('enfileirar', textoCupom);
    if (fila.ok) {
      return { modo: 'fila', mensagem: fila.mensagem };
    }

    throw new Error(`Impressão direta: ${direta.mensagem} | Fila: ${fila.mensagem}`);
  }

  async function carregarProducaoPorPeriodo(inicio, fim) {
    const params = [];
    if (inicio && fim) {
      params.push(`inicio=${inicio}`);
      params.push(`fim=${fim}`);
    }
    const url = 'api/relatorios.php?action=producao_resumo' + (params.length ? '&' + params.join('&') : '');
    const data = await fetchJson(url);
    if (!data.sucesso) {
      throw new Error(data.mensagem || 'Falha ao carregar produção do período');
    }
    return data.dados || [];
  }

  function montarCupomProducaoLinhas(itens, inicio, fim) {
    const sep = '='.repeat(CUPOM_LARGURA);
    const sepSec = '-'.repeat(CUPOM_LARGURA);
    const dataHora = new Date().toLocaleString('pt-BR');
    const periodo = inicio === fim ? inicio : `${inicio} a ${fim}`;

    const totalQtd = itens.reduce((acc, item) => acc + Number(item.quantidade || 0), 0);
    const totalValor = itens.reduce((acc, item) => acc + Number(item.total || 0), 0);

    let cupom = '';
    cupom += sep + '\n';
    cupom += centralizarLinha('LEFRO CANTINA', CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha('RELATORIO DE PRODUCAO', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha(`Periodo: ${periodo}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Emitido: ${dataHora}`, CUPOM_LARGURA) + '\n';
    cupom += sepSec + '\n';

    itens.forEach((item) => {
      const nome = item.nome || 'Produto sem nome';
      const qtd = Number(item.quantidade || 0);
      const valor = Number(item.total || 0);
      cupom += limitarLinha(nome, CUPOM_LARGURA) + '\n';
      cupom += montarLinhaDupla(`Qtd: ${qtd}`, `R$ ${valor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
    });

    cupom += sep + '\n';
    cupom += montarLinhaDupla('TOTAL DE ITENS:', String(totalQtd), CUPOM_LARGURA) + '\n';
    cupom += montarLinhaDupla('TOTAL GERAL:', `R$ ${totalValor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    return cupom;
  }

  function montarCupomResumoVendasLinhas(resumoVendas, itensProducao, inicio, fim) {
    const sep = '='.repeat(CUPOM_LARGURA);
    const sepSec = '-'.repeat(CUPOM_LARGURA);
    const dataHora = new Date().toLocaleString('pt-BR');
    const periodo = inicio === fim ? inicio : `${inicio} a ${fim}`;

    const totalPedidos = resumoVendas.reduce((acc, item) => acc + Number(item.pedidos || 0), 0);
    const totalVendas = resumoVendas.reduce((acc, item) => acc + Number(item.total || 0), 0);
    const itensComVenda = itensProducao.filter((item) => Number(item.quantidade || 0) > 0);

    let cupom = '';
    cupom += sep + '\n';
    cupom += centralizarLinha('LEFRO CANTINA', CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha('RESUMO DE VENDAS', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha(`Periodo: ${periodo}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Emitido: ${dataHora}`, CUPOM_LARGURA) + '\n';
    cupom += sepSec + '\n';
    cupom += montarLinhaDupla('TOTAL PEDIDOS:', String(totalPedidos), CUPOM_LARGURA) + '\n';
    cupom += montarLinhaDupla('TOTAL VENDAS:', `R$ ${totalVendas.toFixed(2)}`, CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha('PRODUTOS NO PERIODO', CUPOM_LARGURA) + '\n';
    cupom += sepSec + '\n';

    if (!itensComVenda.length) {
      cupom += centralizarLinha('SEM VENDAS NO PERIODO', CUPOM_LARGURA) + '\n';
    } else {
      itensComVenda.forEach((item) => {
        const nome = item.nome || 'Produto sem nome';
        const qtd = Number(item.quantidade || 0);
        const valor = Number(item.total || 0);
        cupom += limitarLinha(nome, CUPOM_LARGURA) + '\n';
        cupom += montarLinhaDupla(`Qtd: ${qtd}`, `R$ ${valor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
      });
    }

    cupom += sep + '\n';
    return cupom;
  }

  function montarCupomDetalhesLinhas(itens, inicio, fim) {
    const sep = '='.repeat(CUPOM_LARGURA);
    const sepSec = '-'.repeat(CUPOM_LARGURA);
    const dataHora = new Date().toLocaleString('pt-BR');
    const periodo = inicio === fim ? inicio : `${inicio} a ${fim}`;

    const totalQtd = itens.reduce((acc, item) => acc + Number(item.quantidade || 0), 0);
    const totalValor = itens.reduce((acc, item) => acc + Number(item.subtotal || 0), 0);

    let cupom = '';
    cupom += sep + '\n';
    cupom += centralizarLinha('LEFRO CANTINA', CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha('DETALHES DE VENDAS', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha(`Periodo: ${periodo}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Emitido: ${dataHora}`, CUPOM_LARGURA) + '\n';
    cupom += sepSec + '\n';

    itens.forEach((item) => {
      const pedido = item.pedido ? `Pedido #${item.pedido}` : 'Pedido';
      const produto = item.produto || 'Produto sem nome';
      const atendente = item.atendente || 'N/A';
      const qtd = Number(item.quantidade || 0);
      const valor = Number(item.subtotal || 0);

      cupom += limitarLinha(`${pedido} | ${item.data || ''}`, CUPOM_LARGURA) + '\n';
      cupom += limitarLinha(produto, CUPOM_LARGURA) + '\n';
      cupom += montarLinhaDupla(`Atend: ${atendente}`, `Qtd: ${qtd}`, CUPOM_LARGURA) + '\n';
      cupom += montarLinhaDupla('Subtotal:', `R$ ${valor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
      cupom += sepSec + '\n';
    });

    cupom += montarLinhaDupla('TOTAL ITENS:', String(totalQtd), CUPOM_LARGURA) + '\n';
    cupom += montarLinhaDupla('TOTAL GERAL:', `R$ ${totalValor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    return cupom;
  }

  function montarCupomCaixaLinhas(itens, inicio, fim) {
    const sep = '='.repeat(CUPOM_LARGURA);
    const sepSec = '-'.repeat(CUPOM_LARGURA);
    const dataHora = new Date().toLocaleString('pt-BR');
    const periodo = inicio === fim ? inicio : `${inicio} a ${fim}`;

    const totalSessoes = itens.length;
    const totalVendas = itens.reduce((acc, item) => acc + Number(item.total_vendas || 0), 0);
    const totalValor = itens.reduce((acc, item) => acc + Number(item.valor_total || 0), 0);

    let cupom = '';
    cupom += sep + '\n';
    cupom += centralizarLinha('LEFRO CANTINA', CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha('ABERTURA DE CAIXA', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha(`Periodo: ${periodo}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Emitido: ${dataHora}`, CUPOM_LARGURA) + '\n';
    cupom += sepSec + '\n';

    itens.forEach((item) => {
      const sessao = `Sessao #${item.sessao_id || 0}`;
      const usuario = item.nome || item.login || 'Usuario';
      const status = (item.status || '').toLowerCase() === 'aberto' ? 'Aberto' : 'Fechado';
      const vendas = Number(item.total_vendas || 0);
      const valor = Number(item.valor_total || 0);

      cupom += limitarLinha(`${sessao} | ${status}`, CUPOM_LARGURA) + '\n';
      cupom += limitarLinha(`Usuario: ${usuario}`, CUPOM_LARGURA) + '\n';
      cupom += limitarLinha(`Abertura: ${item.abertura_caixa || '-'}`, CUPOM_LARGURA) + '\n';
      cupom += limitarLinha(`Fechamento: ${item.fechamento_caixa || '-'}`, CUPOM_LARGURA) + '\n';
      cupom += montarLinhaDupla(`Vendas: ${vendas}`, `R$ ${valor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
      cupom += sepSec + '\n';
    });

    cupom += montarLinhaDupla('TOTAL SESSOES:', String(totalSessoes), CUPOM_LARGURA) + '\n';
    cupom += montarLinhaDupla('TOTAL VENDAS:', String(totalVendas), CUPOM_LARGURA) + '\n';
    cupom += montarLinhaDupla('VALOR TOTAL:', `R$ ${totalValor.toFixed(2)}`, CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    return cupom;
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
        .map((r) => `<tr><td>${r.nome}</td><td>${r.quantidade}</td><td>${moeda(r.total)}</td></tr>`)
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
        .map((r, idx) => `<tr>
          <td>#${r.pedido}</td>
          <td>${r.data}</td>
          <td>${r.atendente || ''}</td>
          <td>${r.produto}</td>
          <td>${r.quantidade}</td>
          <td>${moeda(r.subtotal)}</td>
          <td style="display: flex; gap: 5px;">
            <button class="btn" onclick="abrirModalEdicao(${idx})" style="padding: 6px 10px; font-size: 12px; background: #4CAF50;">✎ Editar</button>
            <button class="btn" onclick="deletarVenda(${idx})" style="padding: 6px 10px; font-size: 12px; background: #f44336;">✕ Deletar</button>
          </td>
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
        .map((r, index) => `<tr>
          <td>#${r.sessao_id}</td>
          <td>${r.nome || r.login || ''}</td>
          <td>${r.abertura_caixa || ''}</td>
          <td>${r.fechamento_caixa || '-'}</td>
          <td>${r.status === 'aberto' ? 'Aberto' : 'Fechado'}</td>
          <td>${r.total_vendas || 0}</td>
          <td>${moeda(r.valor_total)}</td>
          <td><button class="btn-delete" onclick="deletarCaixa(${index})">🗑️ Deletar</button></td>
        </tr>`)
        .join('');
    }
  }

  function exportarVendasCSV() {
    const rows = cacheResumoVendas.map((r) => [r.dia, r.pedidos, Number(r.total || 0).toFixed(2)]);
    downloadCsv('resumo_vendas.csv', toCsv(rows, ['Data', 'Total Vendas', 'Valor Total']));
  }

  async function imprimirResumoVendasTermica() {
    const inicio = getVal('inicioVendas') || hojeISO();
    const fim = getVal('fimVendas') || inicio;

    if (!cacheResumoVendas.length) {
      await loadResumoVendas();
    }

    const resumo = cacheResumoVendas || [];
    if (!resumo.length) {
      alert('Sem dados de vendas para imprimir no período selecionado.');
      return;
    }

    try {
      const producao = await carregarProducaoPorPeriodo(inicio, fim);
      const cupom = montarCupomResumoVendasLinhas(resumo, producao, inicio, fim);
      const resultado = await imprimirCupomTermico(cupom);
      if (resultado.modo === 'fila') {
        alert('Impressora ocupada/indisponível no momento. Relatório enviado para a fila de impressão.');
      } else {
        alert('Resumo de vendas enviado para impressora térmica com sucesso.');
      }
    } catch (err) {
      alert('Falha ao imprimir resumo térmico: ' + (err && err.message ? err.message : 'erro desconhecido'));
    }
  }

  function exportarProducaoCSV() {
    const rows = cacheResumoProducao.map((r) => [r.nome, r.quantidade]);
    downloadCsv('producao_por_produto.csv', toCsv(rows, ['Produto', 'Quantidade Vendida']));
  }

  async function imprimirProducaoTermica() {
    const inicio = getVal('inicioProducao') || hojeISO();
    const fim = getVal('fimProducao') || inicio;
    const produtoId = getVal('filtroProducao');

    if (!cacheResumoProducao.length) {
      await loadResumoProducao();
    }

    let itens = cacheResumoProducao
      .filter((r) => Number(r.quantidade || 0) > 0)
      .map((r) => ({
        nome: r.nome,
        quantidade: Number(r.quantidade || 0),
        total: Number(r.total || 0),
        id: Number(r.id || 0)
      }));

    if (produtoId) {
      const pid = Number(produtoId);
      itens = itens.filter((r) => r.id === pid);
    }

    if (!itens.length) {
      alert('Nenhum item com venda no período selecionado para imprimir.');
      return;
    }

    const cupom = montarCupomProducaoLinhas(itens, inicio, fim);

    try {
      const resultado = await imprimirCupomTermico(cupom);
      if (resultado.modo === 'fila') {
        alert('Impressora ocupada/indisponível no momento. Relatório enviado para a fila de impressão.');
      } else {
        alert('Relatório enviado para impressora térmica com sucesso.');
      }
    } catch (err) {
      alert('Falha ao imprimir relatório térmico: ' + (err && err.message ? err.message : 'erro desconhecido'));
    }
  }

  function exportarDetalhesCSV() {
    const rows = cacheDetalhes.map((r) => [r.pedido, r.data, r.atendente || '', r.produto, r.quantidade, Number(r.subtotal || 0).toFixed(2)]);
    downloadCsv('detalhes_vendas.csv', toCsv(rows, ['Numero', 'Data', 'Atendente', 'Produto', 'Qtd', 'Valor']));
  }

  async function imprimirDetalhesTermica() {
    const inicio = getVal('inicioDetalhes') || hojeISO();
    const fim = getVal('fimDetalhes') || inicio;

    if (!cacheDetalhes.length) {
      await loadResumoDetalhes();
    }

    const itens = cacheDetalhes || [];
    if (!itens.length) {
      alert('Sem dados de detalhes para imprimir no período selecionado.');
      return;
    }

    const cupom = montarCupomDetalhesLinhas(itens, inicio, fim);

    try {
      const resultado = await imprimirCupomTermico(cupom);
      if (resultado.modo === 'fila') {
        alert('Impressora ocupada/indisponível no momento. Relatório enviado para a fila de impressão.');
      } else {
        alert('Detalhes de vendas enviados para impressora térmica com sucesso.');
      }
    } catch (err) {
      alert('Falha ao imprimir detalhes térmico: ' + (err && err.message ? err.message : 'erro desconhecido'));
    }
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

  async function imprimirCaixaTermica() {
    const inicio = getVal('inicioCaixa') || hojeISO();
    const fim = getVal('fimCaixa') || inicio;

    if (!cacheCaixa.length) {
      await loadRelatorioCaixa();
    }

    const itens = cacheCaixa || [];
    if (!itens.length) {
      alert('Sem dados de caixa para imprimir no período selecionado.');
      return;
    }

    const cupom = montarCupomCaixaLinhas(itens, inicio, fim);

    try {
      const resultado = await imprimirCupomTermico(cupom);
      if (resultado.modo === 'fila') {
        alert('Impressora ocupada/indisponível no momento. Relatório enviado para a fila de impressão.');
      } else {
        alert('Relatório de caixa enviado para impressora térmica com sucesso.');
      }
    } catch (err) {
      alert('Falha ao imprimir caixa térmico: ' + (err && err.message ? err.message : 'erro desconhecido'));
    }
  }

  // FUNÇÕES DE CRUD PARA DETALHES DE VENDAS
  window.abrirModalEdicao = function(index) {
    const venda = cacheDetalhes[index];
    if (!venda) return;

    document.getElementById('editPedidoId').value = venda.pedido_id || venda.pedido || index;
    document.getElementById('editPedidoNum').textContent = venda.pedido;
    document.getElementById('editData').value = venda.data;
    document.getElementById('editAtendente').value = venda.atendente || '';
    document.getElementById('editProduto').value = venda.produto;
    document.getElementById('editQuantidade').value = venda.quantidade;
    document.getElementById('editValor').value = Number(venda.subtotal || 0).toFixed(2);

    document.getElementById('modalEdicaoVenda').style.display = 'flex';
  };

  window.fecharModalEdicao = function() {
    document.getElementById('modalEdicaoVenda').style.display = 'none';
    document.getElementById('formEdicaoVenda').reset();
  };

  window.salvarEdicaoVenda = async function(event) {
    event.preventDefault();

    const pedidoId = document.getElementById('editPedidoId').value;
    const data = document.getElementById('editData').value;
    const atendente = document.getElementById('editAtendente').value;
    const produto = document.getElementById('editProduto').value;
    const quantidade = parseInt(document.getElementById('editQuantidade').value);
    const valor = parseFloat(document.getElementById('editValor').value);

    if (!data || !produto || quantidade <= 0 || valor < 0) {
      alert('Preencha todos os campos corretamente');
      return;
    }

    try {
      const response = await fetch('api/relatorios.php?action=atualizar_venda', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pedido_id: pedidoId,
          data,
          atendente,
          produto,
          quantidade,
          valor
        })
      });

      const result = await response.json();

      if (result.sucesso) {
        alert('Venda atualizada com sucesso!');
        fecharModalEdicao();
        loadResumoDetalhes();
      } else {
        alert(result.mensagem || 'Erro ao atualizar venda');
      }
    } catch (err) {
      console.error(err);
      alert('Erro ao atualizar venda');
    }
  };

  window.deletarVenda = async function(index) {
    if (!confirm('Tem certeza que deseja deletar esta venda?')) return;

    const venda = cacheDetalhes[index];
    if (!venda) return;

    try {
      const response = await fetch('api/relatorios.php?action=deletar_venda', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          pedido_id: venda.pedido_id || venda.pedido || index
        })
      });

      const result = await response.json();

      if (result.sucesso) {
        alert('Venda deletada com sucesso!');
        loadResumoDetalhes();
      } else {
        alert(result.mensagem || 'Erro ao deletar venda');
      }
    } catch (err) {
      console.error(err);
      alert('Erro ao deletar venda');
    }
  };

  window.deletarCaixa = async function(index) {
    if (!confirm('Tem certeza que deseja deletar esta sessão de caixa?')) return;

    const caixa = cacheCaixa[index];
    if (!caixa) return;

    try {
      const response = await fetch('api/relatorios.php?action=deletar_caixa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          sessao_id: caixa.sessao_id
        })
      });

      const result = await response.json();

      if (result.sucesso) {
        alert('Sessão de caixa deletada com sucesso!');
        loadRelatorioCaixa();
      } else {
        alert(result.mensagem || 'Erro ao deletar sessão de caixa');
      }
    } catch (err) {
      console.error(err);
      alert('Erro ao deletar sessão de caixa');
    }
  };

  window.loadResumoVendas = loadResumoVendas;
  window.loadResumoProducao = loadResumoProducao;
  window.loadResumoDetalhes = loadResumoDetalhes;
  window.loadRelatorioCaixa = loadRelatorioCaixa;
  window.imprimirResumoVendasTermica = imprimirResumoVendasTermica;
  window.exportarVendasCSV = exportarVendasCSV;
  window.exportarProducaoCSV = exportarProducaoCSV;
  window.imprimirProducaoTermica = imprimirProducaoTermica;
  window.exportarDetalhesCSV = exportarDetalhesCSV;
  window.imprimirDetalhesTermica = imprimirDetalhesTermica;
  window.exportarCaixaCSV = exportarCaixaCSV;
  window.imprimirCaixaTermica = imprimirCaixaTermica;

  document.addEventListener('DOMContentLoaded', () => {
    initFiltros();
  });
})();