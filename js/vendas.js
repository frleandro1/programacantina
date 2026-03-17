let produtos = [];
let carrinho = [];
let filtroAtual = 'Salgado';
let CUPOM_LARGURA = 40;          // sobrescrito pelas configs da impressora
let _impressoraAutoprint = false; // impressão automática ao exibir o cupom
let _impressoraFonte = 9;         // tamanho da fonte em pt

function padRight(texto, tamanho) {
  return (texto || '').toString().padEnd(tamanho, ' ');
}

function padLeft(texto, tamanho) {
  return (texto || '').toString().padStart(tamanho, ' ');
}

function formatarValor(v) {
  return Number(v || 0).toFixed(2);
}

function mapGrupo(cat) {
  const c = (cat || '').toLowerCase();
  if (c.includes('salgado') || c.includes('frito')) return 'Salgado';
  if (c.includes('bebida') || c.includes('refrigerante') || c.includes('suco')) return 'Bebida';
  if (c.includes('doce') || c.includes('bolo') || c.includes('mel')) return 'Doce';
  return 'Salgado';
}

async function carregarProdutos() {
  try {
    const res = await fetch('api/produtos.php?action=listar');
    const data = await res.json();
    const lista = data.dados || data.produtos || [];
    produtos = lista.map(p => ({
      id: p.id || p.id_produto,
      nome: p.nome,
      preco: parseFloat(p.preco || p.preco_venda || 0),
      estoque: parseInt(p.estoque || p.estoque_atual || 0),
      categoria: p.categoria || p.tipo_categoria || '',
      grupo: mapGrupo(p.categoria || p.tipo_categoria || p.nome)
    }));
    renderProdutos(filtroAtual);
  } catch (err) {
    console.error('Erro ao carregar produtos:', err);
  }
}

function renderProdutos(filtro) {
  filtroAtual = filtro;
  const grid = document.getElementById('prodGrid');
  const filtrados = produtos.filter(p => p.grupo === filtro && p.estoque > 0);
  
  if (filtrados.length === 0) {
    grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#999;padding:60px 20px;">Nenhum produto disponível nesta categoria</p>';
    return;
  }
  
  grid.innerHTML = filtrados.map(p => `
    <div class="prod-card">
      <h3>${p.nome}</h3>
      <div class="prod-preco">R$ ${p.preco.toFixed(2)}</div>
      <div class="prod-estoque">Estoque: ${p.estoque}</div>
      <button class="btn btn-primary" onclick="addCarrinho(${p.id})" id="btn-${p.id}">
        + Adicionar
      </button>
    </div>
  `).join('');
}

function addCarrinho(id) {
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
  
  const btn = document.getElementById('btn-' + id);
  if (btn) {
    btn.textContent = ' Adicionado!';
    btn.style.background = '#28a745';
    setTimeout(() => {
      btn.textContent = '+ Adicionar';
      btn.style.background = '';
    }, 800);
  }
  
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
  const spaces = Math.max(0, Math.floor((largura - texto.length) / 2));
  return ' '.repeat(spaces) + texto;
}

async function carregarConfigImpressora() {
  try {
    const res  = await fetch('api/configuracoes.php?action=obter', { cache: 'no-store' });
    const body = await res.text();
    if (!body) return;
    const data = JSON.parse(body);
    if (!data.sucesso || !data.dados) return;

    const largura  = data.dados.impressora_largura   || '80';
    _impressoraAutoprint = (data.dados.impressora_autoprint || 'nao') === 'sim';
    _impressoraFonte     = parseInt(data.dados.impressora_fonte || '9', 10);

    // Colunas por largura de papel
    CUPOM_LARGURA = largura === '58' ? 32 : 48;

    // Injetar CSS de impressão dinâmico
    let styleEl = document.getElementById('impressoraPrintStyle');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'impressoraPrintStyle';
      document.head.appendChild(styleEl);
    }
    styleEl.textContent = [
      '@media print {',
      `  @page { size: ${largura}mm auto; margin: 1mm; }`,
      `  .modal-content { max-width: ${largura}mm !important; width: fit-content !important; }`,
      '  .modal-body { padding: 1mm !important; margin: 0 !important; }',
      `  #cupomPreview { font-size: ${_impressoraFonte}pt !important; line-height: 1.05 !important; display: inline-block !important; }`,
      '}'
    ].join('\n');
  } catch (err) {
    console.warn('Config impressora não carregada, usando padrões:', err);
  }
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
    
    const sepPrincipal = '='.repeat(CUPOM_LARGURA);
    const sepSecao = '-'.repeat(CUPOM_LARGURA);

    let cupom = sepPrincipal + '\n';
    cupom += centralizarLinha('PROGRAMA CANTINA', CUPOM_LARGURA) + '\n';
    cupom += sepPrincipal + '\n';
    cupom += centralizarLinha(`Venda Nº: ${idVenda}`, CUPOM_LARGURA) + '\n';
    cupom += centralizarLinha(`${data}  ${hora}`, CUPOM_LARGURA) + '\n';
    cupom += sepPrincipal + '\n\n';
    cupom += centralizarLinha('PRODUTOS', CUPOM_LARGURA) + '\n';
    cupom += sepSecao + '\n';
    
    itens.forEach(c => {
      const preco = parseFloat(c.preco) || 0;
      const qtd = parseFloat(c.qtd) || 0;
      const subtotal = preco * qtd;
      const nome = c.nome || 'Produto sem nome';
      
      cupom += nome.slice(0, CUPOM_LARGURA) + '\n';

      const esq = `${qtd} x R$ ${formatarValor(preco)}`;
      const dir = `R$ ${formatarValor(subtotal)}`;
      const espacos = Math.max(1, CUPOM_LARGURA - esq.length - dir.length);
      cupom += esq + ' '.repeat(espacos) + dir + '\n\n';
    });
    
    cupom += sepPrincipal + '\n';
    cupom += padRight('TOTAL:', CUPOM_LARGURA - 12) + padLeft(`R$ ${formatarValor(total)}`, 12) + '\n';
    cupom += sepPrincipal + '\n\n';
    cupom += centralizarLinha('Obrigado pela preferência!', CUPOM_LARGURA) + '\n';
    cupom += sepPrincipal + '\n';
    
    preview.textContent = cupom;
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

function imprimirCupomDireto() {
  const modal = document.getElementById('cupomModal');
  const preview = document.getElementById('cupomPreview');
  if (!modal || !modal.classList.contains('show') || !preview) {
    return;
  }

  window.focus();
  window.print();
}

function imprimirTesteRapido() {
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

    // Fluxo único e previsível: impressão pelo navegador em modo kiosk.
    setTimeout(() => {
      window.focus();
      window.print();
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

document.addEventListener('DOMContentLoaded', () => {
  const user = JSON.parse(localStorage.getItem('usuario') || '{}');
  const userId = user.id_usuario || user.id;
  
  if (!userId) {
    window.location.href = 'login-menu.html';
    return;
  }
  
  carregarProdutos();
  carregarConfigImpressora();
  
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