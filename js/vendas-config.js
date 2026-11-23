// Vendas - Configuracoes personalizadas

async function carregarConfiguracoesPedidos() {
  try {
    console.log('[Vendas Config] Iniciando carregamento de configurações...');
    
    const response = await fetch('api/configuracoes.php?action=obter', { cache: 'no-store' });
    const data = await response.json();
    
    console.log('[Vendas Config] Resposta recebida:', data);
    
    if (data.sucesso && data.dados) {
      console.log('[Vendas Config] Aplicando configurações...');
      const nomeCantina = data.dados.nome_cantina || (window.CantinaSistema ? window.CantinaSistema.nomeSistema : 'Cantina IBFC');
      const fallbackLogo = 'LOGO_F.png';
      const logoUrl = data.dados.logo_url ? `${data.dados.logo_url}?t=${Date.now()}` : fallbackLogo;
      
      // Atualizar nome da cantina
      document.querySelectorAll('.brand').forEach(el => {
        let logoImg = el.querySelector('.brand-logo');

        if (!logoImg && logoUrl) {
          logoImg = document.createElement('img');
          logoImg.className = 'brand-logo';
          logoImg.alt = 'Logo Cantina';
        }

        if (logoImg) {
          logoImg.src = logoUrl;
          logoImg.onerror = () => {
            logoImg.onerror = null;
            logoImg.src = `${fallbackLogo}?t=${Date.now()}`;
          };
        }

        el.textContent = '';
        if (logoImg) {
          el.appendChild(logoImg);
        }
        el.appendChild(document.createTextNode(` ${nomeCantina}`));
      });
      document.title = nomeCantina + ' - Vendas';
      console.log('[Vendas Config] Nome atualizado:', nomeCantina);
      
      // Não renderizar logo abaixo da barra; manter somente ao lado do nome
      const logoEl = document.getElementById('logoContainer');
      if (logoEl) {
        logoEl.innerHTML = '';
      }
      
      // Adicionar banner se existir
      if (data.dados.banner_url) {
        const bannerEl = document.getElementById('bannerContainer');
        if (bannerEl) {
          bannerEl.innerHTML = `<img src="${data.dados.banner_url}?t=${Date.now()}" alt="Banner" class="banner-img">`;
          console.log('[Vendas Config] Banner adicionado:', data.dados.banner_url);
        }
      }
      
      // Aplicar cores personalizadas dinamicamente
      aplicarCoresPersonalizadas(data.dados);
      console.log('[Vendas Config] Cores aplicadas com sucesso!');
    }
  } catch (error) {
    console.error('[Vendas Config] Erro ao carregar configuracoes:', error);
  }
}

function aplicarCoresPersonalizadas(config) {
  const root = document.documentElement;
  
  // Mapear as cores do banco para as variáveis CSS do theme.css
  if (config.cor_primaria) {
    root.style.setProperty('--brand', config.cor_primaria);
    // Gerar cor secundária mais clara
    const rgb = parseInt(config.cor_primaria.slice(1), 16);
    const r = (rgb >> 16) & 255;
    const g = (rgb >> 8) & 255;
    const b = rgb & 255;
    const brighter = `rgb(${Math.min(r + 50, 255)}, ${Math.min(g + 50, 255)}, ${Math.min(b + 50, 255)})`;
    root.style.setProperty('--brand-2', brighter);
  }
  
  if (config.cor_texto) {
    root.style.setProperty('--text', config.cor_texto);
  }
  
  if (config.cor_fundo_card) {
    root.style.setProperty('--card', config.cor_fundo_card);
  }
  
  // Cor de acentuação - usar como cor de hover/active
  if (config.cor_acentuacao) {
    // Será usada em elementos interativos
    document.documentElement.style.setProperty('--accent-color', config.cor_acentuacao);
  }
}

// Carregar configuracoes quando a pagina carrega
// Usar DOMContentLoaded para garantir que o DOM está pronto, e también load como fallback
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', carregarConfiguracoesPedidos);
} else {
  // DOM já foi carregado, executar imediatamente
  carregarConfiguracoesPedidos();
}

// Também executar on load para garantir que tudo está carregado
window.addEventListener('load', carregarConfiguracoesPedidos);
