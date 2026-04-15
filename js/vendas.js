let produtos = [];
let carrinho = [];
let filtroAtual = 'Todos';
let produtosFixosVenda = [];
let CUPOM_LARGURA = 40;          // sobrescrito pelas configs da impressora
let _impressoraLargura = '80';   // largura em mm para impressão dedicada
let _impressoraAutoprint = false; // impressão automática ao exibir o cupom
let _impressoraFonte = 9;         // tamanho da fonte em pt
let _printLock = false;           // evita duplo disparo de impressão
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

  if (urlBanco) {
    if (/^https?:\/\//i.test(urlBanco)) {
      return urlBanco;
    }
    if (urlBanco.startsWith('/')) {
      return `${window.location.origin}${urlBanco}`;
    }
    return `${window.location.origin}/Cantina/${urlBanco.replace(/^\/+/, '')}`;
  }

  if (nomeArquivo) {
    return `${window.location.origin}/Cantina/uploads/produtos/${nomeArquivo}`;
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
  const nome = user.nome || user.login || user.username || user.userName || '';
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

function logout() {
  localStorage.removeItem('usuario');
  localStorage.removeItem('sessaoCaixaId');
  localStorage.removeItem('aberturaCaixaEm');
  window.location.href = 'login-menu.html';
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
});