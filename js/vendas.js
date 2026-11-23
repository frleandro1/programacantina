let produtos = [];
let carrinho = [];
let filtroAtual = 'Todos';
let produtosFixosVenda = [];
let CUPOM_LARGURA = 40;          // sobrescrito pelas configs da impressora
let _impressoraLargura = '80';   // largura em mm para impressão dedicada
let _impressoraAutoprint = false; // impressão automática ao exibir o cupom
let _impressoraFonte = 9;         // tamanho da fonte em pt
let _printLock = false;           // evita duplo disparo de impressão
const syncChannel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('cantina-sync') : null;
let _cupomCustomizacoes = {       // customizações do cupom (carregadas da API)
  nomeCantina: 'Cantina IBFC',
  vendedor: '',
  caixa: '',
  rotuloCupom: 'Venda N:',
  tituloProdutos: 'PRODUTOS',
  msg1: 'DEUS ABENCOE!',
  msg2: '',
  msg3: '',
  sepPrincipal: '='.repeat(30),
  sepSecao: '-'.repeat(30)
};

function padRight(texto, tamanho) {
  const base = (texto || '').toString();
  return base.slice(0, Math.max(0, tamanho)).padEnd(tamanho, ' ');
}

function padLeft(texto, tamanho) {
  const base = (texto || '').toString();
  return base.slice(-Math.max(0, tamanho)).padStart(tamanho, ' ');
}

function limitarLinha(texto, largura = 40) {
  return (texto || '').toString().replace(/\r/g, '').replace(/\n/g, ' ').slice(0, Math.max(0, largura));
}

function montarLinhaDupla(esq, dir, largura = 40) {
  const direita = limitarLinha(dir, largura);
  const maxEsq = Math.max(1, largura - direita.length - 1);
  const esquerda = limitarLinha(esq, maxEsq);
  const espacos = Math.max(1, largura - esquerda.length - direita.length);
  return esquerda + ' '.repeat(espacos) + direita;
}

function ajustarTextoCupomParaLargura(texto, largura = 40) {
  const linhas = String(texto || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  return linhas.map((linha) => limitarLinha(linha, largura)).join('\n');
}

function formatarValor(v) {
  return Number(v || 0).toFixed(2);
}

function mapGrupo(cat) {
  const c = String(cat || '').trim().toLowerCase();
  if (c.includes('todos') || c.includes('todas')) return 'Todos';
  if (c.includes('salgado') || c.includes('frito')) return 'Salgado';
  if (c.includes('bebida') || c.includes('refrigerante') || c.includes('suco')) return 'Bebida';
  if (c.includes('doce') || c.includes('bolo') || c.includes('mel')) return 'Doce';
  return 'Outro';
}

function normalizarFiltroGrupo(valor) {
  const texto = String(valor || '').trim().toLowerCase();
  if (texto === '' || texto === 'todos' || texto === 'todas') return 'Todos';
  if (texto === 'salgado') return 'Salgado';
  if (texto === 'bebida') return 'Bebida';
  if (texto === 'doce') return 'Doce';
  if (texto === 'outro') return 'Outro';
  return 'Todos';
}

function resolverImagemUrl(produto) {
  const urlBanco = String(produto.imagem_url || '').trim();
  const nomeArquivo = String(produto.imagem || '').trim();
  const base = window.location.pathname.includes('/')
    ? window.location.pathname.replace(/\/[^/]*$/, '/')
    : '/';

  if (urlBanco) {
    if (/^https?:\/\//i.test(urlBanco)) {
      return urlBanco;
    }
    if (urlBanco.startsWith('/')) {
      return `${window.location.origin}${urlBanco}`;
    }
    return `${window.location.origin}${base}${urlBanco.replace(/^\/+/, '')}`;
  }

  if (nomeArquivo) {
    return `${window.location.origin}${base}uploads/produtos/${nomeArquivo}`;
  }

  return '';
}

async function carregarProdutos() {
  try {
    const res = await fetch('api/produtos.php?action=listar');
    const data = await res.json();
    const lista = data.dados || data.produtos || [];
    produtos = lista.map(p => ({
      id: p.id || p.id_produto,
      nome: p.nome,
      descricao: (p.descricao || '').trim(),
      preco: parseFloat(p.preco || p.preco_venda || 0),
      estoque: parseInt(p.estoque || p.estoque_atual || 0),
      imagemUrl: resolverImagemUrl(p),
      categoria: p.categoria || p.tipo_categoria || '',
      grupo: mapGrupo(p.categoria || p.tipo_categoria || p.nome)
    }));
    await carregarProdutosFixosVenda();
    renderProdutos(filtroAtual);
  } catch (err) {
    console.error('Erro ao carregar produtos:', err);
  }
}

async function carregarProdutosFixosVenda() {
  produtosFixosVenda = [];

  try {
    const res = await fetch('api/configuracoes.php?action=obter-uma&chave=produtos_fixos_venda', { cache: 'no-store' });
    const data = await res.json();
    if (!data.sucesso || !data.dados || !data.dados.valor) {
      return;
    }

    const ids = JSON.parse(data.dados.valor);
    if (Array.isArray(ids)) {
      produtosFixosVenda = ids
        .map((id) => parseInt(id, 10))
        .filter((id) => Number.isFinite(id) && id > 0);
    }
  } catch (err) {
    produtosFixosVenda = [];
  }
}

function renderCardsProdutos(lista) {
  return lista.map(p => `
    <div class="prod-card">
      <div class="prod-imagem-wrap">
        ${p.imagemUrl ? `<img src="${p.imagemUrl}" alt="${p.nome}" class="prod-imagem" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">` : ''}
        <div class="prod-imagem-fallback" style="display:${p.imagemUrl ? 'none' : 'flex'};">🍽️</div>
      </div>
      <h3>${p.nome}</h3>
      <div class="prod-descricao">${p.descricao || p.categoria || 'Sem descrição'}</div>
      <div class="prod-preco">R$ ${p.preco.toFixed(2)}</div>
      <div class="prod-estoque">Estoque: ${p.estoque}</div>
      <button class="btn btn-primary" data-produto-id="${p.id}" onclick="addCarrinho(${p.id}, this)">
        + Adicionar
      </button>
    </div>
  `).join('');
}

function renderProdutosFixos() {
  const section = document.getElementById('produtosFixosSection');
  const grid = document.getElementById('prodGridFixos');
  if (!section || !grid) return;

  if (!Array.isArray(produtosFixosVenda) || produtosFixosVenda.length === 0) {
    section.style.display = 'none';
    grid.innerHTML = '';
    return;
  }

  const ordem = new Map(produtosFixosVenda.map((id, index) => [id, index]));
  const fixos = produtos
    .filter((produto) => produtosFixosVenda.includes(produto.id) && produto.estoque > 0)
    .sort((a, b) => (ordem.get(a.id) || 0) - (ordem.get(b.id) || 0));

  if (fixos.length === 0) {
    section.style.display = 'none';
    grid.innerHTML = '';
    return;
  }

  section.style.display = 'block';
  grid.innerHTML = renderCardsProdutos(fixos);
}

function renderProdutos(filtro) {
  const filtroNormalizado = normalizarFiltroGrupo(filtro);
  filtroAtual = filtroNormalizado;
  const grid = document.getElementById('prodGrid');
  const filtrados = produtos.filter((p) => {
    const pertenceAoFiltro = filtroNormalizado === 'Todos' ? true : p.grupo === filtroNormalizado;
    return pertenceAoFiltro && p.estoque > 0;
  });
  renderProdutosFixos();
  
  if (filtrados.length === 0) {
    grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#999;padding:60px 20px;">Nenhum produto disponível</p>';
    return;
  }
  
  grid.innerHTML = renderCardsProdutos(filtrados);
}

function addCarrinho(id, buttonEl) {
  const prod = produtos.find(p => p.id === id);
  if (!prod) return;
  
  const item = carrinho.find(c => c.id === id);
  if (item) {
    if (item.qtd >= prod.estoque) {
      alert('Estoque insuficiente');
      return;
    }
    item.qtd++;
  } else {
    carrinho.push({ id, nome: prod.nome, preco: prod.preco, qtd: 1, estoque: prod.estoque });
  }
  
  const botoes = buttonEl
    ? Array.from(document.querySelectorAll(`[data-produto-id="${id}"]`))
    : [];

  botoes.forEach((btn) => {
    btn.textContent = ' Adicionado!';
    btn.style.background = '#28a745';
  });

  setTimeout(() => {
    botoes.forEach((btn) => {
      btn.textContent = '+ Adicionar';
      btn.style.background = '';
    });
  }, 800);
  
  renderCarrinho();
}

function alterarQtd(id, delta) {
  const item = carrinho.find(c => c.id === id);
  if (!item) return;
  
  const novaQtd = item.qtd + delta;
  if (novaQtd <= 0) {
    removerItem(id);
    return;
  }
  if (novaQtd > item.estoque) {
    alert('Estoque insuficiente');
    return;
  }
  
  item.qtd = novaQtd;
  renderCarrinho();
}

function removerItem(id) {
  carrinho = carrinho.filter(c => c.id !== id);
  renderCarrinho();
}

function aplicarBaixaEstoqueLocal(itensVendidos) {
  itensVendidos.forEach(item => {
    const prod = produtos.find(p => p.id === item.id);
    if (!prod) return;
    prod.estoque = Math.max(0, (prod.estoque || 0) - (item.qtd || 0));
  });
}

function renderCarrinho() {
  const lista = document.getElementById('cartList');
  const badge = document.getElementById('cartBadge');
  const totalText = document.getElementById('totalText');
  const btnFinalizar = document.getElementById('btnFinalizar');
  
  const qtdTotal = carrinho.reduce((sum, c) => sum + c.qtd, 0);
  badge.textContent = qtdTotal + (qtdTotal === 1 ? ' item' : ' itens');
  
  if (carrinho.length === 0) {
    lista.innerHTML = '<div class="cart-empty"><div class="cart-empty-icon"></div><p>Carrinho vazio</p></div>';
    totalText.textContent = 'R$ 0,00';
    btnFinalizar.disabled = true;
    // Sincronizar modal
    if (typeof sincronizarCarrinhoModal === 'function') sincronizarCarrinhoModal();
    return;
  }
  
  const total = carrinho.reduce((sum, c) => sum + c.preco * c.qtd, 0);
  totalText.textContent = 'R$ ' + total.toFixed(2);
  btnFinalizar.disabled = false;
  
  lista.innerHTML = carrinho.map(c => `
    <div class="cart-item">
      <div class="cart-item-info">
        <strong>${c.nome}</strong>
        <span>R$ ${(c.preco * c.qtd).toFixed(2)}</span>
      </div>
      <div class="cart-item-controls">
        <button class="btn-qtd" onclick="alterarQtd(${c.id}, -1)"></button>
        <span>${c.qtd}</span>
        <button class="btn-qtd" onclick="alterarQtd(${c.id}, 1)">+</button>
        <button class="btn-remove" onclick="removerItem(${c.id})"></button>
      </div>
    </div>
  `).join('');
  
  // Sincronizar modal
  if (typeof sincronizarCarrinhoModal === 'function') sincronizarCarrinhoModal();
}

async function finalizarVenda() {
  if (carrinho.length === 0) return;
  
  const user = JSON.parse(localStorage.getItem('usuario') || '{}');
  const userId = user.id_usuario || user.id;
  const sessaoCaixaId = parseInt(localStorage.getItem('sessaoCaixaId') || '0', 10);
  
  if (!userId) {
    alert('Sessão expirada');
    window.location.href = 'login.html';
    return;
  }
  
  const itens = carrinho.map(c => ({
    produto_id: c.id,
    quantidade: c.qtd,
    preco_unitario: c.preco
  }));
  
  try {
    const res = await fetch('api/vendas.php?action=criar_carrinho', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        usuario_id: userId,
        sessao_caixa_id: sessaoCaixaId,
        itens
      })
    });
    
    const data = await res.json();
    console.log('Resposta da venda:', data);
    
    if (data.sucesso) {
      // Dispara sincronização imediatamente ao confirmar a venda,
      // antes de abrir o fluxo visual do cupom.
      try {
        const now = Date.now();
        localStorage.setItem('cantina:estoqueAtualizadoEm', String(now));
        if (syncChannel) {
          syncChannel.postMessage({ type: 'estoque-atualizado', at: now });
        }
      } catch (syncErr) {
        console.warn('Falha ao sincronizar atualização de estoque entre abas:', syncErr);
      }

      // Usar o número de venda do dia em vez do ID
      mostrarCupom(data.numero_venda_turno || data.numero_venda || data.id, carrinho);
      aplicarBaixaEstoqueLocal(carrinho);
      carrinho = [];
      renderCarrinho();
      renderProdutos(filtroAtual);
    } else {
      alert('Erro ao finalizar venda: ' + (data.mensagem || 'Erro desconhecido'));
    }
  } catch (err) {
    console.error('Erro na requisição:', err);
    alert('Erro ao finalizar venda');
  }
}

function centralizarLinha(texto, largura = 40) {
  const base = limitarLinha(texto, largura);
  const spaces = Math.max(0, Math.floor((largura - base.length) / 2));
  return (' '.repeat(spaces) + base).slice(0, largura);
}

function ajustarSeparador(texto, fallbackChar) {
  const limite = CUPOM_LARGURA;
  const valor = String(texto || '').trim();
  if (!valor) {
    return fallbackChar.repeat(limite);
  }
  // Quando vier apenas um caractere, preenche toda a largura do cupom.
  if (valor.length === 1) {
    return valor.repeat(limite);
  }
  return valor.slice(0, limite);
}

function obterNomeVendedorAtual() {
  const user = JSON.parse(localStorage.getItem('usuario') || '{}');
  const nome = user.username || user.login || localStorage.getItem('username') || user.nome || user.userName || '';
  return String(nome || '').trim();
}

async function carregarConfigImpressora() {
  try {
    const res  = await fetch('api/configuracoes.php?action=obter', { cache: 'no-store' });
    const body = await res.text();
    if (!body) return;
    const data = JSON.parse(body);
    if (!data.sucesso || !data.dados) return;

    const largura  = data.dados.impressora_largura   || '80';
    _impressoraLargura = largura;
    _impressoraAutoprint = (data.dados.impressora_autoprint || 'nao') === 'sim';
    _impressoraFonte     = parseInt(data.dados.impressora_fonte || '9', 10);
    if (!Number.isFinite(_impressoraFonte) || _impressoraFonte < 7) {
      _impressoraFonte = 9;
    }
    const is58mm = largura === '58';
    const fontePrint = is58mm ? Math.min(_impressoraFonte, 8) : _impressoraFonte;
    const lineHeightPrint = is58mm ? '0.92' : '0.98';

    // Colunas por largura de papel
    CUPOM_LARGURA = largura === '58' ? 30 : 46;

    // Injetar CSS de impressão dinâmico
    let styleEl = document.getElementById('impressoraPrintStyle');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'impressoraPrintStyle';
      document.head.appendChild(styleEl);
    }
    styleEl.textContent = [
      '@media print {',
      `  @page { size: ${largura}mm auto; margin: 0.5mm; }`,
      '  .modal, .modal-content, .modal-body, #cupomPreview { height: auto !important; min-height: 0 !important; max-height: none !important; overflow: visible !important; }',
      `  .modal-content { max-width: ${largura}mm !important; width: fit-content !important; margin: 0 !important; padding: 0 !important; }`,
      '  .modal-body { padding: 0.5mm !important; margin: 0 !important; }',
      `  #cupomPreview { font-size: ${fontePrint}pt !important; line-height: ${lineHeightPrint} !important; display: inline-block !important; white-space: pre !important; }`,
      '}'
    ].join('\n');
  } catch (err) {
    console.warn('Config impressora não carregada, usando padrões:', err);
  }
}

async function carregarCustomizacoes() {
  try {
    const res = await fetch('api/configuracoes.php?action=obter', { cache: 'no-store' });
    const body = await res.text();
    if (!body) return;
    const data = JSON.parse(body);
    if (!data.sucesso || !data.dados) return;
    
    // Atualizar customizações a partir de dados prefixados com 'cupom_'
    Object.keys(data.dados).forEach(chave => {
      if (chave.startsWith('cupom_')) {
        const key = chave.replace('cupom_', '');
        if (_cupomCustomizacoes.hasOwnProperty(key)) {
          _cupomCustomizacoes[key] = data.dados[chave] || _cupomCustomizacoes[key];
        }
      }
    });
    console.log('Customizações carregadas:', _cupomCustomizacoes);
  } catch (err) {
    console.warn('Customizações não carregadas, usando padrões:', err);
  }
}

function escaparHtmlCupom(texto) {
  return String(texto || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

async function imprimirViaLpt1(textoCupom) {
  try {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 6000);
    const resp = await fetch('api/impressora.php?action=enfileirar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ texto: textoCupom, copias: 1 }),
      signal: controller.signal
    });
    clearTimeout(timer);

    const body = await resp.text();
    if (!body) {
      return { ok: false, erro: 'Resposta vazia da API de impressão' };
    }

    let data = null;
    try {
      data = JSON.parse(body);
    } catch {
      return { ok: false, erro: 'Resposta inválida da API de impressão' };
    }

    if (!resp.ok || !data || data.sucesso !== true) {
      return { ok: false, erro: (data && data.mensagem) ? data.mensagem : `HTTP ${resp.status}` };
    }

    return { ok: true };
  } catch (err) {
    return { ok: false, erro: err && err.message ? err.message : 'Falha ao chamar API de impressão' };
  }
}

async function imprimirViaApiDireta(textoCupom) {
  try {
    const resp = await fetch('api/impressora.php?action=imprimir', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ texto: textoCupom, copias: 1 })
    });

    const body = await resp.text();
    if (!body) {
      return { ok: false, erro: 'Resposta vazia da API de impressão direta' };
    }

    let data = null;
    try {
      data = JSON.parse(body);
    } catch {
      return { ok: false, erro: 'Resposta inválida da API de impressão direta' };
    }

    if (!resp.ok || !data || data.sucesso !== true) {
      return { ok: false, erro: (data && data.mensagem) ? data.mensagem : `HTTP ${resp.status}` };
    }

    return { ok: true, detalhe: data.dados || null };
  } catch (err) {
    return { ok: false, erro: err && err.message ? err.message : 'Falha ao chamar impressão direta' };
  }
}

function imprimirTextoCupom(textoCupom) {
  const larguraMm = _impressoraLargura === '58' ? '58' : '80';
  const fontePt = _impressoraLargura === '58' ? Math.min(_impressoraFonte || 9, 8) : (_impressoraFonte || 9);
  const lineHeight = _impressoraLargura === '58' ? '0.92' : '0.98';
  const textoNormalizado = String(textoCupom || '').replace(/\n{3,}/g, '\n\n').trimEnd() + '\n';
  const htmlCupom = escaparHtmlCupom(textoNormalizado);
  const totalLinhas = textoNormalizado.split('\n').length;
  const mmPorLinha = _impressoraLargura === '58' ? 2.6 : 2.9;
  const alturaMm = Math.max(40, Math.ceil((totalLinhas * mmPorLinha) + 8));

  const html = `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Cupom</title>
  <style>
    @page { size: ${larguraMm}mm ${alturaMm}mm; margin: 0.5mm; }
    html, body {
      margin: 0;
      padding: 0;
      width: ${larguraMm}mm;
      height: ${alturaMm}mm;
      background: #fff;
      overflow: visible;
    }
    pre {
      margin: 0;
      padding: 0.5mm;
      font-family: "Courier New", Courier, monospace;
      font-size: ${fontePt}pt;
      line-height: ${lineHeight};
      white-space: pre;
      display: inline-block;
      color: #000;
      background: #fff;
      border: none;
    }
  </style>
</head>
<body>
  <pre>${htmlCupom}</pre>
</body>
</html>`;

  const oldFrame = document.getElementById('printCupomFrame');
  if (oldFrame) {
    oldFrame.remove();
  }

  const frame = document.createElement('iframe');
  frame.id = 'printCupomFrame';
  frame.style.position = 'fixed';
  frame.style.right = '0';
  frame.style.bottom = '0';
  frame.style.width = '1px';
  frame.style.height = '1px';
  frame.style.opacity = '0';
  frame.style.border = '0';
  document.body.appendChild(frame);

  const doc = frame.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  setTimeout(() => {
    frame.contentWindow.focus();
    frame.contentWindow.print();
    setTimeout(() => frame.remove(), 500);
  }, 250);
}

function mostrarCupom(idVenda, itens) {
  try {
    const modal = document.getElementById('cupomModal');
    const preview = document.getElementById('cupomPreview');
    
    if (!modal || !preview) {
      console.error('Modal ou preview não encontrado');
      return;
    }
    
    const now = new Date();
    const data = now.toLocaleDateString('pt-BR');
    const hora = now.toLocaleTimeString('pt-BR');
    
    // Validar itens
    if (!Array.isArray(itens) || itens.length === 0) {
      console.error('Itens inválidos:', itens);
      return;
    }
    
    const total = itens.reduce((sum, c) => {
      const preco = parseFloat(c.preco) || 0;
      const qtd = parseFloat(c.qtd) || 0;
      return sum + (preco * qtd);
    }, 0);
    
    const sepPrincipal = ajustarSeparador(_cupomCustomizacoes.sepPrincipal, '=');
    const sepSecao = ajustarSeparador(_cupomCustomizacoes.sepSecao, '-');

    let cupom = sepPrincipal + '\n';
    cupom += centralizarLinha(_cupomCustomizacoes.nomeCantina || 'Cantina IBFC', CUPOM_LARGURA) + '\n';
    cupom += sepPrincipal + '\n';
    cupom += centralizarLinha(`${_cupomCustomizacoes.rotuloCupom || 'Venda Nº'}: ${idVenda}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`${data}  ${hora}`, CUPOM_LARGURA) + '\n';
    const vendedorAtual = obterNomeVendedorAtual() || _cupomCustomizacoes.vendedor || 'NAO IDENTIFICADO';
    cupom += centralizarLinha(`Operador: ${vendedorAtual}`, CUPOM_LARGURA) + '\n';
    const eventoCupom = localStorage.getItem('eventoNomeAtual');
    if (eventoCupom && eventoCupom.trim()) {
      cupom += centralizarLinha(`Evento: ${eventoCupom.trim()}`, CUPOM_LARGURA) + '\n';
    }
    if (_cupomCustomizacoes.caixa) cupom += centralizarLinha(_cupomCustomizacoes.caixa, CUPOM_LARGURA) + '\n';
    cupom += sepSecao + '\n';
    cupom += centralizarLinha(_cupomCustomizacoes.tituloProdutos || 'PRODUTOS', CUPOM_LARGURA) + '\n';
    cupom += sepSecao + '\n';
    
    itens.forEach(c => {
      const preco = parseFloat(c.preco) || 0;
      const qtd = parseFloat(c.qtd) || 0;
      const subtotal = preco * qtd;
      const nome = c.nome || 'Produto sem nome';
      
      cupom += limitarLinha(nome, CUPOM_LARGURA) + '\n';

      const esq = `${qtd} x R$ ${formatarValor(preco)}`;
      const dir = `R$ ${formatarValor(subtotal)}`;
      cupom += montarLinhaDupla(esq, dir, CUPOM_LARGURA) + '\n';
    });
    
    cupom += sepPrincipal + '\n';
    cupom += montarLinhaDupla('TOTAL:', `R$ ${formatarValor(total)}`, CUPOM_LARGURA) + '\n';
    cupom += sepPrincipal + '\n';
    if (_cupomCustomizacoes.msg1) cupom += centralizarLinha(_cupomCustomizacoes.msg1, CUPOM_LARGURA) + '\n';
    if (_cupomCustomizacoes.msg2) cupom += centralizarLinha(_cupomCustomizacoes.msg2, CUPOM_LARGURA) + '\n';
    if (_cupomCustomizacoes.msg3) cupom += centralizarLinha(_cupomCustomizacoes.msg3, CUPOM_LARGURA) + '\n';
    if (_cupomCustomizacoes.msg1 || _cupomCustomizacoes.msg2 || _cupomCustomizacoes.msg3) {
      cupom += sepPrincipal + '\n';
    }
    
    preview.textContent = ajustarTextoCupomParaLargura(cupom, CUPOM_LARGURA);
    modal.classList.add('show');

    // Impressão automática configurada no admin
    if (_impressoraAutoprint) {
      setTimeout(() => imprimirCupomDireto(), 300);
    }
  } catch (err) {
    console.error('Erro ao gerar cupom:', err);
    alert('Erro ao gerar cupom: ' + err.message);
  }
}

function fecharModal() {
  const modal = document.getElementById('cupomModal');
  modal.classList.remove('show');
}

function _setPrintStatus(msg, cor) {
  const el = document.getElementById('printStatusMsg');
  if (!el) return;
  el.textContent = msg;
  el.style.color = cor || '#555';
  el.style.display = msg ? 'block' : 'none';
}

function fallbackImpressaoNavegador(textoCupom, detalhe) {
  try {
    imprimirTextoCupom(textoCupom);
    _setPrintStatus('⚠️ Impressão nativa indisponível. Abrindo impressão do navegador.', '#f59e0b');
    if (detalhe) {
      console.warn('Detalhe da falha nativa:', detalhe);
    }
    return true;
  } catch (err) {
    const msg = err && err.message ? err.message : 'falha ao abrir impressão do navegador';
    _setPrintStatus('⚠️ Falha na impressão nativa e no fallback do navegador: ' + msg, '#dc3545');
    return false;
  }
}

async function imprimirCupomDireto() {
  if (_printLock) return;
  const modal = document.getElementById('cupomModal');
  const preview = document.getElementById('cupomPreview');
  if (!modal || !modal.classList.contains('show') || !preview) {
    return;
  }
  _printLock = true;
  const btnPrint = document.querySelector('.btn-print');
  if (btnPrint) { btnPrint.disabled = true; btnPrint.textContent = 'Imprimindo...'; }
  _setPrintStatus('', '');

  const texto = preview.textContent || '';
  try {
    // Primeiro tenta impressão nativa via API (funciona com impressora térmica real)
    _setPrintStatus('⏳ Enviando para impressão...', '#555');
    const direta = await imprimirViaApiDireta(texto);
    if (direta.ok) {
      _setPrintStatus('✓ Cupom impresso com sucesso!', '#28a745');
      return;
    }
    // Se a API falhar (sem impressora térmica), usa impressão pelo navegador
    console.warn('Impressão nativa falhou, usando navegador:', direta.erro || 'sem detalhe');
    imprimirTextoCupom(texto);
    _setPrintStatus('✓ Diálogo de impressão aberto.', '#28a745');
  } finally {
    setTimeout(() => {
      _printLock = false;
      if (btnPrint) { btnPrint.disabled = false; btnPrint.textContent = 'Imprimir'; }
    }, 3000);
  }
}

async function imprimirTesteRapido() {
  try {
    const modal = document.getElementById('cupomModal');
    const preview = document.getElementById('cupomPreview');

    if (!modal || !preview) {
      alert('Não foi possível abrir o modal de cupom para teste.');
      return;
    }

    const now = new Date();
    const dataHora = `${now.toLocaleDateString('pt-BR')} ${now.toLocaleTimeString('pt-BR')}`;
    const sep = '='.repeat(CUPOM_LARGURA);

    let cupom = '';
    cupom += sep + '\n';
    cupom += centralizarLinha('TESTE IMPRESSORA TERMICA', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += centralizarLinha(`Data: ${dataHora}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Largura: ${CUPOM_LARGURA} colunas`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`Fonte: ${_impressoraFonte}pt`, CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';
    cupom += '0123456789012345678901234567890123456789\n';
    cupom += 'Linha para verificar alinhamento e corte\n';
    cupom += sep + '\n';
    cupom += centralizarLinha('FIM DO TESTE', CUPOM_LARGURA) + '\n';
    cupom += sep + '\n';

    preview.textContent = cupom;
    modal.classList.add('show');

    // Teste rápido: tenta impressão direta primeiro.
    setTimeout(async () => {
      if (_printLock) return;
      const native = await imprimirViaApiDireta(cupom);
      _setPrintStatus(
        native.ok ? '✓ Teste enviado para impressão.' : '⚠️ Falha no teste. Confira a impressora nas configurações.',
        native.ok ? '#28a745' : '#dc3545'
      );
    }, 250);
  } catch (err) {
    console.error('Erro no teste de impressão:', err);
    alert('Falha ao executar teste de impressão: ' + err.message);
  }
}

function novoPedido() {
  fecharModal();
  carregarProdutos();
}

async function finalizarCaixaDiario() {
  if (!confirm('Tem certeza que deseja finalizar o caixa do dia? Você poderá abrir um novo caixa depois.')) {
    return;
  }

  const sessaoId = localStorage.getItem('sessaoCaixaId');
  
  if (!sessaoId) {
    alert('Nenhuma sessão de caixa aberta');
    return;
  }
  
  await mostrarModalFechamentoCaixa('finalizar');
}

async function mostrarModalFechamentoCaixa(tipo = 'logout') {
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  let userId = localStorage.getItem('userId');
  
  if (!userId) {
    try {
      const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
      userId = usuario.id_usuario || usuario.id;
    } catch (err) {
      console.warn('Erro ao parsear usuario:', err);
    }
  }
  
  if (!sessaoId || !userId) {
    alert('❌ Erro: Nenhuma sessão de caixa encontrada');
    return;
  }
  
  try {
    window._dadosAberturaCache = null;

    // Buscar valor de abertura da sessão exata deste login
    const resAberturaRes = await fetch(`api/abertura-caixa.php?action=verificar_hoje&sessao_id=${sessaoId}&usuario_id=${userId}`, { cache: 'no-store' });
    const resAbertura = await resAberturaRes.json();
    
    if (!resAbertura.sucesso || !resAbertura.dados) {
      alert('❌ Erro: Sessão de caixa não encontrada');
      return;
    }
    
    const valorAbertura = parseFloat(resAbertura.dados.valor_abertura || 0);
    
    // Buscar vendas do dia da sessão
    const resVendasRes = await fetch(`api/vendas.php?action=listar_por_sessao&sessao_id=${sessaoId}`);
    const resVendas = await resVendasRes.json();
    
    const totalVendas = resVendas.sucesso && resVendas.dados 
      ? resVendas.dados.reduce((sum, v) => sum + (parseFloat(v.valor_total) || 0), 0)
      : 0;
    
    // Calcular valor esperado
    const valorEsperado = valorAbertura + totalVendas;
    
    // Armazenar dados para o próximo passo
    window._dadosAberturaCache = {
      valorAbertura: valorAbertura,
      totalVendasPdv: totalVendas,
      totalVendas: totalVendas,
      valorEsperado: valorEsperado
    };
    window._perdasFechamento = [];
    window._queimasFechamento = [];

    // Preencher selects de produtos para Perda e Queima no modal de fechamento
    await popularProdutosFechamentoCaixa();

    // Preencher o modal com a sugestão
    const modal = document.getElementById('modalFechamentoCaixa');
    modal.dataset.tipo = tipo;

    document.getElementById('obsFechamentoCaixa').value = '';
    renderListasFechamento();
    atualizarResumoModalFechamento(true);

    modal.style.display = 'flex';
    document.getElementById('valorFechamentoCaixa').focus();

  } catch (err) {
    console.error('Erro ao preparar fechamento:', err);
    alert('❌ Erro ao carregar dados do caixa: ' + err.message);
  }
}

async function popularProdutosFechamentoCaixa() {
  const selPerda = document.getElementById('fechPerdaProduto');
  const selQueima = document.getElementById('fechQueimaProduto');
  if (!selPerda || !selQueima) return;

  let listaProd = Array.isArray(window.produtos) && window.produtos.length > 0 ? window.produtos : (typeof produtos !== 'undefined' && Array.isArray(produtos) ? produtos : []);
  try {
    const res = await fetch('api/produtos.php?action=listar', { cache: 'no-store' });
    const d = await res.json();
    if (d.sucesso && Array.isArray(d.dados)) {
      listaProd = d.dados;
    }
  } catch (e) {
    // Usa cache em memória se falhar
  }

  window._produtosFechamentoCache = listaProd;

  const opcoes = ['<option value="">-- Produto --</option>'];
  listaProd
    .filter(p => Number(p.estoque_atual ?? p.estoque ?? 0) > 0)
    .forEach(p => {
      const est = Number(p.estoque_atual ?? p.estoque ?? 0);
      const un = p.unidade || 'un';
      opcoes.push(`<option value="${p.id}">${p.nome} (est: ${est} ${un})</option>`);
    });

  selPerda.innerHTML = opcoes.join('');
  selQueima.innerHTML = opcoes.join('');

  const qPerda = document.getElementById('fechPerdaQtd');
  const mPerda = document.getElementById('fechPerdaMotivo');
  const qQueima = document.getElementById('fechQueimaQtd');
  const pQueima = document.getElementById('fechQueimaPreco');
  const oQueima = document.getElementById('fechQueimaObs');
  const sugQueima = document.getElementById('sugestaoQueimaFechamento');

  if (qPerda) qPerda.value = '';
  if (mPerda) mPerda.value = '';
  if (qQueima) qQueima.value = '';
  if (pQueima) pQueima.value = '';
  if (oQueima) oQueima.value = '';
  if (sugQueima) sugQueima.textContent = '';
}

function obterProdutoFechamentoPorId(id) {
  const lista = window._produtosFechamentoCache || (typeof produtos !== 'undefined' ? produtos : []);
  return lista.find(p => Number(p.id) === Number(id)) || null;
}

function qtdComprometidaNoFechamento(produtoId) {
  const perdas = (window._perdasFechamento || [])
    .filter(x => Number(x.produto_id) === Number(produtoId))
    .reduce((acc, x) => acc + Number(x.quantidade || 0), 0);
  const queimas = (window._queimasFechamento || [])
    .filter(x => Number(x.produto_id) === Number(produtoId))
    .reduce((acc, x) => acc + Number(x.quantidade || 0), 0);
  return perdas + queimas;
}

function sugerirPrecoQueimaFechamento() {
  const sel = document.getElementById('fechQueimaProduto');
  const inpPreco = document.getElementById('fechQueimaPreco');
  const sug = document.getElementById('sugestaoQueimaFechamento');
  if (!sel || !inpPreco) return;

  const prod = obterProdutoFechamentoPorId(sel.value);
  if (!prod) {
    inpPreco.value = '';
    if (sug) sug.textContent = '';
    return;
  }

  const venda = Number(prod.preco || 0);
  const custo = Number(prod.preco_custo || 0);
  const sugerido = (venda * 0.5).toFixed(2);
  inpPreco.value = sugerido;
  if (sug) {
    sug.textContent = `Venda normal: R$ ${venda.toFixed(2).replace('.', ',')} | Custo: R$ ${custo.toFixed(2).replace('.', ',')} | Sugestão 50% OFF: R$ ${sugerido.replace('.', ',')}`;
  }
}

function adicionarPerdaFechamento(silencioso = false) {
  const sel = document.getElementById('fechPerdaProduto');
  const inpQtd = document.getElementById('fechPerdaQtd');
  const inpMotivo = document.getElementById('fechPerdaMotivo');
  if (!sel || !inpQtd) return true;

  const pid = parseInt(sel.value, 10);
  const qtd = parseFloat(inpQtd.value);
  const motivo = (inpMotivo ? inpMotivo.value : '').trim();

  if (!pid && (!inpQtd.value || isNaN(qtd))) {
    return true;
  }
  if (!pid || isNaN(qtd) || qtd <= 0) {
    if (!silencioso) alert('Selecione o produto e informe uma quantidade válida para a perda.');
    return false;
  }

  const prod = obterProdutoFechamentoPorId(pid);
  if (!prod) {
    if (!silencioso) alert('Produto não encontrado.');
    return false;
  }

  const disponivel = Number(prod.estoque_atual ?? prod.estoque ?? 0) - qtdComprometidaNoFechamento(pid);
  if (qtd > disponivel) {
    alert(`Quantidade de perda para "${prod.nome}" excede o estoque disponível (${disponivel} ${prod.unidade || 'un'}).`);
    return false;
  }

  const custo = Math.max(0, Number(prod.preco_custo || 0));
  const prejuizo = Number((qtd * custo).toFixed(2));

  window._perdasFechamento = window._perdasFechamento || [];
  window._perdasFechamento.push({
    produto_id: pid,
    nome: prod.nome,
    unidade: prod.unidade || 'un',
    quantidade: qtd,
    custo_unitario: custo,
    prejuizo: prejuizo,
    motivo: motivo
  });

  sel.value = '';
  inpQtd.value = '';
  if (inpMotivo) inpMotivo.value = '';

  renderListasFechamento();
  atualizarResumoModalFechamento(false);
  return true;
}

function removerPerdaFechamento(index) {
  if (!Array.isArray(window._perdasFechamento)) return;
  window._perdasFechamento.splice(index, 1);
  renderListasFechamento();
  atualizarResumoModalFechamento(false);
}

function adicionarQueimaFechamento(silencioso = false) {
  const sel = document.getElementById('fechQueimaProduto');
  const inpQtd = document.getElementById('fechQueimaQtd');
  const inpPreco = document.getElementById('fechQueimaPreco');
  const inpObs = document.getElementById('fechQueimaObs');
  const sug = document.getElementById('sugestaoQueimaFechamento');
  if (!sel || !inpQtd || !inpPreco) return true;

  const pid = parseInt(sel.value, 10);
  const qtd = parseFloat(inpQtd.value);
  const precoQueima = parseFloat(inpPreco.value);
  const obs = (inpObs ? inpObs.value : '').trim();

  if (!pid && (!inpQtd.value || isNaN(qtd))) {
    return true;
  }
  if (!pid || isNaN(qtd) || qtd <= 0 || isNaN(precoQueima) || precoQueima <= 0) {
    if (!silencioso) alert('Selecione o produto, a quantidade e o preço promocional para a queima de estoque.');
    return false;
  }

  const prod = obterProdutoFechamentoPorId(pid);
  if (!prod) {
    if (!silencioso) alert('Produto não encontrado.');
    return false;
  }

  const disponivel = Number(prod.estoque_atual ?? prod.estoque ?? 0) - qtdComprometidaNoFechamento(pid);
  if (qtd > disponivel) {
    alert(`Quantidade de queima para "${prod.nome}" excede o estoque disponível (${disponivel} ${prod.unidade || 'un'}).`);
    return false;
  }

  const custo = Math.max(0, Number(prod.preco_custo || 0));
  const receita = Number((qtd * precoQueima).toFixed(2));
  const resultado = Number((receita - (qtd * custo)).toFixed(2));

  window._queimasFechamento = window._queimasFechamento || [];
  window._queimasFechamento.push({
    produto_id: pid,
    nome: prod.nome,
    unidade: prod.unidade || 'un',
    quantidade: qtd,
    preco_queima: precoQueima,
    custo_unitario: custo,
    receita: receita,
    resultado: resultado,
    observacao: obs
  });

  sel.value = '';
  inpQtd.value = '';
  inpPreco.value = '';
  if (inpObs) inpObs.value = '';
  if (sug) sug.textContent = '';

  renderListasFechamento();
  atualizarResumoModalFechamento(true);
  return true;
}

function removerQueimaFechamento(index) {
  if (!Array.isArray(window._queimasFechamento)) return;
  window._queimasFechamento.splice(index, 1);
  renderListasFechamento();
  atualizarResumoModalFechamento(true);
}

function renderListasFechamento() {
  const divPerdas = document.getElementById('listaPerdasFechamento');
  const divQueimas = document.getElementById('listaQueimasFechamento');
  const perdas = window._perdasFechamento || [];
  const queimas = window._queimasFechamento || [];

  if (divPerdas) {
    if (perdas.length === 0) {
      divPerdas.innerHTML = '<span style="color:#999;">Nenhuma perda adicionada neste fechamento.</span>';
    } else {
      divPerdas.innerHTML = perdas.map((p, idx) => `
        <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:6px 8px; border-radius:6px; border:1px solid #feb2b2; margin-bottom:4px;">
          <span><strong>${p.quantidade} ${p.unidade}</strong> × ${p.nome} ${p.motivo ? `(${p.motivo})` : ''} — <strong style="color:#c53030;">-R$ ${p.prejuizo.toFixed(2).replace('.', ',')}</strong></span>
          <button type="button" onclick="removerPerdaFechamento(${idx})" style="border:none; background:transparent; color:#c53030; font-weight:700; cursor:pointer; padding:2px 6px;">✕</button>
        </div>
      `).join('');
    }
  }

  if (divQueimas) {
    if (queimas.length === 0) {
      divQueimas.innerHTML = '<span style="color:#999;">Nenhuma queima adicionada neste fechamento.</span>';
    } else {
      divQueimas.innerHTML = queimas.map((q, idx) => `
        <div style="display:flex; justify-content:space-between; align-items:center; background:#fff; padding:6px 8px; border-radius:6px; border:1px solid #fbd38d; margin-bottom:4px;">
          <span><strong>${q.quantidade} ${q.unidade}</strong> × ${q.nome} a R$ ${q.preco_queima.toFixed(2).replace('.', ',')} — <strong style="color:#2b6cb0;">+R$ ${q.receita.toFixed(2).replace('.', ',')}</strong></span>
          <button type="button" onclick="removerQueimaFechamento(${idx})" style="border:none; background:transparent; color:#c05621; font-weight:700; cursor:pointer; padding:2px 6px;">✕</button>
        </div>
      `).join('');
    }
  }
}

function atualizarResumoModalFechamento(atualizarInputValor = false) {
  if (!window._dadosAberturaCache) return;

  const valorAbertura = Number(window._dadosAberturaCache.valorAbertura || 0);
  const vendasPdv = Number(window._dadosAberturaCache.totalVendasPdv ?? window._dadosAberturaCache.totalVendas ?? 0);
  const receitaQueima = (window._queimasFechamento || []).reduce((acc, q) => acc + Number(q.receita || 0), 0);
  const prejuizoPerdas = (window._perdasFechamento || []).reduce((acc, p) => acc + Number(p.prejuizo || 0), 0);

  const totalVendas = Number((vendasPdv + receitaQueima).toFixed(2));
  const valorEsperado = Number((valorAbertura + totalVendas).toFixed(2));

  window._dadosAberturaCache.totalVendas = totalVendas;
  window._dadosAberturaCache.valorEsperado = valorEsperado;
  window._dadosAberturaCache.receitaQueima = receitaQueima;
  window._dadosAberturaCache.prejuizoPerdas = prejuizoPerdas;

  const fmt = (n) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const valorFormatado = fmt(valorEsperado);

  if (atualizarInputValor) {
    const inp = document.getElementById('valorFechamentoCaixa');
    if (inp) inp.value = valorFormatado;
  }

  const sugestaoDiv = document.getElementById('sugestaoValorFechamento');
  if (sugestaoDiv) {
    sugestaoDiv.innerHTML = `
      <div style="background: #e3f2fd; padding: 12px; border-radius: 8px; margin-bottom: 4px; border-left: 4px solid #2196F3;">
        <div style="font-size: 12px; color: #1565c0; margin-bottom: 4px;">Cálculo do Caixa</div>
        <div style="font-size: 14px; color: #333; line-height: 1.5;">
          Abertura: <strong>R$ ${fmt(valorAbertura)}</strong><br>
          Vendas PDV: <strong>R$ ${fmt(vendasPdv)}</strong>
          ${receitaQueima > 0 ? `<br>Queima no Fechamento: <strong style="color:#dd6b20;">+R$ ${fmt(receitaQueima)}</strong>` : ''}
          ${prejuizoPerdas > 0 ? `<br>Perdas Contabilizadas (Custo): <strong style="color:#e53e3e;">-R$ ${fmt(prejuizoPerdas)}</strong>` : ''}
          <br><strong style="color: #2196F3;">Total Esperado em Caixa: R$ ${valorFormatado}</strong>
        </div>
      </div>
    `;
  }
}

function cancelarFechamentoCaixa() {
  const modal = document.getElementById('modalFechamentoCaixa');
  modal.style.display = 'none';
}

async function processarFechamentoCaixa(e) {
  e.preventDefault();
  
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  
  if (!sessaoId) {
    alert('❌ Nenhuma sessão de caixa aberta');
    return;
  }

  // Se o operador preencheu os campos de perda ou queima e clicou direto em "Próximo", inclui automaticamente
  const selPerda = document.getElementById('fechPerdaProduto');
  const inpPerdaQtd = document.getElementById('fechPerdaQtd');
  if (selPerda && selPerda.value && inpPerdaQtd && inpPerdaQtd.value) {
    if (!adicionarPerdaFechamento(false)) return;
  }

  const selQueima = document.getElementById('fechQueimaProduto');
  const inpQueimaQtd = document.getElementById('fechQueimaQtd');
  if (selQueima && selQueima.value && inpQueimaQtd && inpQueimaQtd.value) {
    if (!adicionarQueimaFechamento(false)) return;
  }
  
  const valorInput = document.getElementById('valorFechamentoCaixa').value;
  const valor = parseFloat(valorInput.replace(/\./g, '').replace(',', '.'));
  
  if (isNaN(valor) || valor < 0) {
    alert('❌ Valor inválido. Use formato: 100,50');
    return;
  }
  
  const obs = document.getElementById('obsFechamentoCaixa').value.trim();
  
  try {
    atualizarResumoModalFechamento(false);

    // Usar dados já calculados quando o modal foi aberto
    let valorAbertura, totalVendas, valorEsperado;
    
    if (window._dadosAberturaCache) {
      valorAbertura = window._dadosAberturaCache.valorAbertura;
      totalVendas = window._dadosAberturaCache.totalVendas;
      valorEsperado = window._dadosAberturaCache.valorEsperado;
    } else {
      // Fallback: buscar dados se não estiver em cache
      let userId = localStorage.getItem('userId');
      if (!userId) {
        try {
          const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
          userId = usuario.id_usuario || usuario.id;
        } catch (err) {
          console.warn('Erro ao parsear usuario:', err);
        }
      }
      
      const resAberturaRes = await fetch(`api/abertura-caixa.php?action=verificar_hoje&sessao_id=${sessaoId}&usuario_id=${userId}`, { cache: 'no-store' });
      const resAbertura = await resAberturaRes.json();
      
      if (!resAbertura.sucesso || !resAbertura.dados) {
        alert('❌ Erro ao buscar dados de abertura');
        return;
      }
      
      valorAbertura = parseFloat(resAbertura.dados.valor_abertura || 0);
      
      const resVendasRes = await fetch(`api/vendas.php?action=listar_por_sessao&sessao_id=${sessaoId}`);
      const resVendas = await resVendasRes.json();
      
      totalVendas = resVendas.sucesso && resVendas.dados 
        ? resVendas.dados.reduce((sum, v) => sum + (parseFloat(v.valor_total) || 0), 0)
        : 0;
      
      valorEsperado = valorAbertura + totalVendas;
    }
    
    const diferenca = valor - valorEsperado;
    const perdas = window._perdasFechamento || [];
    const queimas = window._queimasFechamento || [];
    const receitaQueima = queimas.reduce((acc, q) => acc + Number(q.receita || 0), 0);
    const prejuizoPerdas = perdas.reduce((acc, p) => acc + Number(p.prejuizo || 0), 0);
    
    // Preencher modal de relatório com os valores
    document.getElementById('relValorAbertura').textContent = 'R$ ' + valorAbertura.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('relTotalVendas').textContent = 'R$ ' + totalVendas.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('relValorEsperado').textContent = 'R$ ' + valorEsperado.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('relValorInformado').textContent = 'R$ ' + valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    const boxResumoPQ = document.getElementById('relResumoPerdasQueimas');
    if (boxResumoPQ) {
      if (perdas.length > 0 || queimas.length > 0) {
        let htmlPQ = '<div style="font-weight:700; margin-bottom:6px;">📋 Perdas e Queimas no Fechamento:</div>';
        if (queimas.length > 0) {
          htmlPQ += `<div style="color:#c05621; margin-bottom:4px;">🔥 <strong>Queima de Estoque (+R$ ${receitaQueima.toFixed(2).replace('.', ',')}):</strong><br>` +
            queimas.map(q => `• ${q.quantidade} ${q.unidade} × ${q.nome} (R$ ${q.preco_queima.toFixed(2).replace('.', ',')}/un)`).join('<br>') +
            '</div>';
        }
        if (perdas.length > 0) {
          htmlPQ += `<div style="color:#c53030;">🗑️ <strong>Perdas Registradas (Prejuízo: -R$ ${prejuizoPerdas.toFixed(2).replace('.', ',')}):</strong><br>` +
            perdas.map(p => `• ${p.quantidade} ${p.unidade} × ${p.nome}${p.motivo ? ' (' + p.motivo + ')' : ''}`).join('<br>') +
            '</div>';
        }
        boxResumoPQ.innerHTML = htmlPQ;
        boxResumoPQ.style.display = 'block';
      } else {
        boxResumoPQ.style.display = 'none';
        boxResumoPQ.innerHTML = '';
      }
    }
    
    const textoObservacoes = obs ? `📝 Observações: ${obs}` : '(Sem observações)';
    document.getElementById('relObservacoes').textContent = textoObservacoes;
    
    // Mostrar diferença com cor
    const relDiferenca = document.getElementById('relDiferenca');
    const relDiferencaTexto = document.getElementById('relDiferencaTexto');
    
    if (Math.abs(diferenca) < 0.01) {
      relDiferenca.textContent = 'R$ 0,00';
      relDiferenca.style.color = '#4CAF50';
      relDiferencaTexto.textContent = '✓ Caixa fechou corretamente!';
      relDiferencaTexto.style.color = '#2e7d32';
    } else if (diferenca > 0) {
      relDiferenca.textContent = '+R$ ' + diferenca.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      relDiferenca.style.color = '#4CAF50';
      relDiferencaTexto.textContent = `💰 Sobra de R$ ${diferenca.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
      relDiferencaTexto.style.color = '#2e7d32';
    } else {
      relDiferenca.textContent = '-R$ ' + Math.abs(diferenca).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      relDiferenca.style.color = '#f44336';
      relDiferencaTexto.textContent = `⚠️ Falta de R$ ${Math.abs(diferenca).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
      relDiferencaTexto.style.color = '#c62828';
    }
    
    // Armazenar dados para confirmação final
    window._dadosFechamento = {
      sessao_id: parseInt(sessaoId, 10),
      valor_fechamento: valor,
      observacoes: obs,
      valor_abertura: valorAbertura,
      total_vendas: totalVendas,
      valor_esperado: valorEsperado,
      perdas: perdas,
      queimas: queimas
    };
    
    // Mostrar relatório e esconder formulário
    document.getElementById('modalFechamentoCaixa').style.display = 'none';
    document.getElementById('modalRelatorioCaixa').style.display = 'flex';
    
  } catch (err) {
    alert('❌ Erro ao processar fechamento: ' + err.message);
    console.error('Erro:', err);
  }
}

function voltarParaFechamento() {
  document.getElementById('modalRelatorioCaixa').style.display = 'none';
  document.getElementById('modalFechamentoCaixa').style.display = 'flex';
}

async function confirmarFechamentoCaixa() {
  const dados = window._dadosFechamento;
  
  if (!dados) {
    alert('❌ Dados de fechamento inválidos');
    return;
  }
  
  try {
    const res = await fetch('api/caixa.php?action=fechar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessao_id: dados.sessao_id,
        valor_fechamento: dados.valor_fechamento,
        observacoes: dados.observacoes,
        valor_abertura: dados.valor_abertura,
        total_vendas: dados.total_vendas,
        valor_esperado: dados.valor_esperado,
        perdas: dados.perdas || [],
        queimas: dados.queimas || []
      })
    });
    
    const data = await res.json();
    
    if (data.sucesso) {
      localStorage.removeItem('sessaoCaixaId');
      localStorage.removeItem('aberturaCaixaEm');
      localStorage.removeItem('valorAberturaAtual');
      window._dadosFechamento = null;
      
      const modal = document.getElementById('modalFechamentoCaixa');
      const tipo = modal.dataset.tipo;
      
      alert('✓ Caixa finalizado com sucesso!\n\nVocê pode:\n1. Abrir um novo caixa para continuar\n2. Fazer logout');
      
      if (tipo === 'logout') {
        window.location.href = 'login-menu.html';
      } else {
        window.location.href = 'abertura-caixa.html';
      }
    } else {
      alert('❌ Erro ao fechar caixa: ' + (data.mensagem || 'Erro desconhecido'));
    }
  } catch (err) {
    alert('❌ Erro ao fechar caixa: ' + err.message);
    console.error('Erro ao fechar caixa:', err);
  }
}

function logout() {
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  if (sessaoId) {
    mostrarModalFechamentoCaixa('logout');
  } else {
    // Sem caixa aberto, fazer logout direto
    localStorage.removeItem('usuario');
    localStorage.removeItem('token');
    localStorage.removeItem('username');
    localStorage.removeItem('userType');
    localStorage.removeItem('userId');
    window.location.href = 'login-menu.html';
  }
}

// Funções de Conexão com Eventos
function atualizarBadgeEvento(nome) {
  const label = document.getElementById('labelEventoNav');
  const modalNome = document.getElementById('modalEventoAtualNome');
  const displayNome = (nome && String(nome).trim()) ? String(nome).trim() : 'Geral';
  if (label) {
    label.textContent = 'Evento: ' + displayNome;
  }
  if (modalNome) {
    modalNome.textContent = (displayNome === 'Geral') ? 'Nenhum (Venda Geral)' : displayNome;
  }
  if (displayNome !== 'Geral') {
    localStorage.setItem('eventoNomeAtual', displayNome);
  } else {
    localStorage.removeItem('eventoNomeAtual');
  }
}

async function carregarEventosSelect(selectId, eventoSelecionadoId) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  try {
    const res = await fetch('api/abertura-caixa.php?action=listar_eventos');
    const data = await res.json();
    if (data.sucesso && Array.isArray(data.dados)) {
      sel.innerHTML = '<option value="">-- Sem evento (Venda Geral) --</option>';
      data.dados.forEach(ev => {
        const opt = document.createElement('option');
        opt.value = ev.id;
        opt.textContent = `${ev.nome} (${ev.status || 'planejado'})`;
        if (eventoSelecionadoId && String(eventoSelecionadoId) === String(ev.id)) {
          opt.selected = true;
        }
        sel.appendChild(opt);
      });
      const optNovo = document.createElement('option');
      optNovo.value = '__novo__';
      optNovo.textContent = '➕ Criar Novo Evento...';
      sel.appendChild(optNovo);
    }
  } catch (err) {
    console.error('Erro ao listar eventos:', err);
  }
}

function toggleNovoEventoCache(selectEl) {
  const wrap = document.getElementById('wrapNovoEventoCache');
  const input = document.getElementById('novoEventoNomeCache');
  if (!wrap) return;
  if (selectEl.value === '__novo__') {
    wrap.style.display = 'block';
    if (input) input.focus();
  } else {
    wrap.style.display = 'none';
  }
}

function toggleNovoEventoModal(selectEl) {
  const wrap = document.getElementById('wrapNovoEventoModal');
  const input = document.getElementById('modalNovoEventoNome');
  if (!wrap) return;
  if (selectEl.value === '__novo__') {
    wrap.style.display = 'block';
    if (input) input.focus();
  } else {
    wrap.style.display = 'none';
  }
}

async function abrirModalEventoVendas() {
  const modal = document.getElementById('modalEventoVendas');
  if (!modal) return;
  let eventoAtualId = localStorage.getItem('eventoIdAtual');
  let eventoAtualNome = localStorage.getItem('eventoNomeAtual');

  let userId = localStorage.getItem('userId');
  if (!userId) {
    try {
      const u = JSON.parse(localStorage.getItem('usuario') || '{}');
      userId = u.id_usuario || u.id;
    } catch (_) {}
  }
  if (userId) {
    try {
      const r = await fetch(`api/abertura-caixa.php?action=verificar_hoje&usuario_id=${userId}`);
      const d = await r.json();
      if (d.sucesso && d.dados) {
        if (d.dados.evento_id) {
          eventoAtualId = d.dados.evento_id;
          eventoAtualNome = d.dados.evento_nome;
        } else {
          eventoAtualId = '';
          eventoAtualNome = '';
        }
      }
    } catch (_) {}
  }

  atualizarBadgeEvento(eventoAtualNome);
  await carregarEventosSelect('modalSelectEvento', eventoAtualId);
  const wrap = document.getElementById('wrapNovoEventoModal');
  if (wrap) wrap.style.display = 'none';
  modal.style.display = 'flex';
}

function fecharModalEventoVendas() {
  const modal = document.getElementById('modalEventoVendas');
  if (modal) modal.style.display = 'none';
}

async function salvarVinculoEventoModal(e) {
  e.preventDefault();
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  if (!sessaoId) {
    alert('⚠️ Nenhuma sessão de caixa aberta no momento.');
    return;
  }
  const select = document.getElementById('modalSelectEvento');
  const inputNovo = document.getElementById('modalNovoEventoNome');
  let eventoId = select ? select.value : '';
  let eventoNome = '';

  if (eventoId === '__novo__') {
    eventoNome = inputNovo ? inputNovo.value.trim() : '';
    if (!eventoNome) {
      alert('Informe o nome do novo evento!');
      if (inputNovo) inputNovo.focus();
      return;
    }
    eventoId = '';
  }

  try {
    const res = await fetch('api/abertura-caixa.php?action=vincular_evento', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessao_id: parseInt(sessaoId, 10),
        evento_id: eventoId ? parseInt(eventoId, 10) : null,
        evento_nome: eventoNome || null
      })
    });
    const data = await res.json();
    if (data.sucesso) {
      if (data.evento_id) {
        localStorage.setItem('eventoIdAtual', data.evento_id);
      } else {
        localStorage.removeItem('eventoIdAtual');
      }
      atualizarBadgeEvento(data.evento_nome);
      fecharModalEventoVendas();
      alert('✓ Evento vinculado com sucesso ao caixa ativo!');
    } else {
      alert('❌ Erro: ' + (data.mensagem || 'Falha ao vincular evento'));
    }
  } catch (err) {
    alert('❌ Erro de comunicação: ' + err.message);
  }
}

// Funções de Abertura de Caixa
function mostrarModalAberturaCache() {
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  let userId = localStorage.getItem('userId');
  
  // Se não encontrar userId, tenta obter do objeto usuario
  if (!userId) {
    try {
      const usuario = JSON.parse(localStorage.getItem('usuario') || '{}');
      userId = usuario.id_usuario || usuario.id;
    } catch (err) {
      console.warn('Erro ao parsear usuario:', err);
    }
  }
  
  if (!sessaoId || !userId) {
    alert('⚠️ Erro: Nenhuma sessão de caixa encontrada. Faça login novamente.');
    return;
  }
  
  // Verificar se já tem valor de abertura registrado
  fetch(`api/abertura-caixa.php?action=verificar_hoje&usuario_id=${userId}`)
    .then(res => res.json())
    .then(data => {
      if (data.dados && data.dados.valor_abertura && data.dados.valor_abertura > 0) {
        // Já tem valor de abertura, caixa está em uso
        alert('💰 Caixa já foi aberto com valor: R$ ' + parseFloat(data.dados.valor_abertura).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
      } else if (data.dados && data.dados.status === 'fechado') {
        // Caixa foi fechado, não pode abrir novo pelo modal
        alert('⚠️ Caixa foi fechado. Faça login novamente para abrir um novo caixa.');
        localStorage.removeItem('sessaoCaixaId');
      } else {
        // Caixa está aberto mas SEM valor de abertura, pode preencher agora
        const modal = document.getElementById('modalAberturaCache');
        const usuario = localStorage.getItem('username') || 'Usuário';
        document.getElementById('nomeUsuarioModal').textContent = usuario;
        document.getElementById('valorAberturaCache').value = '';
        document.getElementById('obsAberturaCache').value = '';
        carregarEventosSelect('eventoAberturaCache');
        const wrap = document.getElementById('wrapNovoEventoCache');
        if (wrap) wrap.style.display = 'none';
        modal.style.display = 'flex';
        document.getElementById('valorAberturaCache').focus();
      }
    })
    .catch(err => {
      console.error('Erro ao verificar caixa:', err);
      alert('❌ Erro ao verificar status do caixa');
    });
}

function cancelarAberturaCache() {
  const modal = document.getElementById('modalAberturaCache');
  modal.style.display = 'none';
}

async function processarAberturaCache(e) {
  e.preventDefault();
  
  const sessaoId = localStorage.getItem('sessaoCaixaId');
  
  if (!sessaoId) {
    alert('❌ Erro: Nenhuma sessão de caixa encontrada. Faça login novamente.');
    return;
  }
  
  const valorInput = document.getElementById('valorAberturaCache').value;
  const valor = parseFloat(valorInput.replace(/\./g, '').replace(',', '.'));
  
  if (isNaN(valor) || valor < 0) {
    alert('❌ Valor inválido. Use formato: 100,50');
    return;
  }
  
  const obs = document.getElementById('obsAberturaCache').value.trim();
  const selectEvento = document.getElementById('eventoAberturaCache');
  const inputNovoEvento = document.getElementById('novoEventoNomeCache');
  let eventoId = selectEvento ? selectEvento.value : '';
  let eventoNome = '';

  if (eventoId === '__novo__') {
    eventoNome = inputNovoEvento ? inputNovoEvento.value.trim() : '';
    eventoId = '';
    if (!eventoNome) {
      alert('Digite o nome do novo evento ou escolha um existente.');
      if (inputNovoEvento) inputNovoEvento.focus();
      return;
    }
  }
  
  try {
    const response = await fetch('api/abertura-caixa.php?action=registrar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        sessao_id: parseInt(sessaoId),
        valor_abertura: valor,
        observacoes: obs,
        evento_id: eventoId ? parseInt(eventoId, 10) : null,
        evento_nome: eventoNome || null
      })
    });
    
    const data = await response.json();
    
    if (data.sucesso) {
      localStorage.setItem('aberturaCaixaEm', new Date().toISOString());
      localStorage.setItem('valorAberturaAtual', valor);
      if (data.evento_id) {
        localStorage.setItem('eventoIdAtual', data.evento_id);
      } else {
        localStorage.removeItem('eventoIdAtual');
      }
      atualizarBadgeEvento(data.evento_nome);
      
      const modal = document.getElementById('modalAberturaCache');
      modal.style.display = 'none';
      
      const eventoMsg = data.evento_nome ? `\nEvento: ${data.evento_nome}` : '';
      alert(`✓ Caixa aberto com sucesso!\nValor: R$ ${valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}${eventoMsg}`);
    } else {
      alert('❌ Erro ao abrir caixa: ' + (data.mensagem || 'Erro desconhecido'));
    }
  } catch (err) {
    alert('❌ Erro ao abrir caixa: ' + err.message);
    console.error('Erro ao abrir caixa:', err);
  }
}

async function garantirWorkerImpressao() {
  try {
    const res  = await fetch('api/impressora.php?action=iniciar-worker', { cache: 'no-store' });
    const body = await res.text();
    if (!body) return;
    const data = JSON.parse(body);
    if (data && data.dados && data.dados.status === 'iniciado') {
      console.log('Worker de impressão iniciado automaticamente.');
    }
  } catch (e) {
    // silencioso — não é crítico para vendas
    console.warn('Worker de impressão não pôde ser iniciado automaticamente:', e && e.message);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const user = JSON.parse(localStorage.getItem('usuario') || '{}');
  const userId = user.id_usuario || user.id;
  
  if (!userId) {
    window.location.href = 'login-menu.html';
    return;
  }
  
  carregarProdutos();
  carregarConfigImpressora();
  carregarCustomizacoes();
  garantirWorkerImpressao(); // inicia o worker de impressão automaticamente

  // Sincronizar status do caixa e evento vinculado
  fetch(`api/abertura-caixa.php?action=verificar_hoje&usuario_id=${userId}`)
    .then(r => r.json())
    .then(d => {
      if (d.sucesso && d.dados) {
        if (d.dados.id_sessao) {
          localStorage.setItem('sessaoCaixaId', d.dados.id_sessao);
        }
        if (d.dados.evento_id) {
          localStorage.setItem('eventoIdAtual', d.dados.evento_id);
        } else {
          localStorage.removeItem('eventoIdAtual');
        }
        atualizarBadgeEvento(d.dados.evento_nome);
      } else {
        atualizarBadgeEvento(localStorage.getItem('eventoNomeAtual'));
      }
    })
    .catch(() => {
      atualizarBadgeEvento(localStorage.getItem('eventoNomeAtual'));
    });
  
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderProdutos(btn.dataset.grupo);
    });
  });
  
  document.getElementById('btnFinalizar').addEventListener('click', finalizarVenda);
  
  document.getElementById('cupomModal').addEventListener('click', (e) => {
    if (e.target.id === 'cupomModal') fecharModal();
  });

  // Máscara de moeda para Abertura de Caixa
  const inputAberturaCache = document.getElementById('valorAberturaCache');
  if (inputAberturaCache) {
    inputAberturaCache.addEventListener('input', function(e) {
      let valor = e.target.value.replace(/\D/g, '');
      if (valor) {
        valor = (parseInt(valor) / 100).toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      e.target.value = valor;
    });
  }

  // Máscara de moeda para Fechamento de Caixa
  const inputFechamentoCaixa = document.getElementById('valorFechamentoCaixa');
  if (inputFechamentoCaixa) {
    inputFechamentoCaixa.addEventListener('input', function(e) {
      let valor = e.target.value.replace(/\D/g, '');
      if (valor) {
        valor = (parseInt(valor) / 100).toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }
      e.target.value = valor;
    });
  }
});