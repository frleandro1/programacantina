// Configuracoes - Customizacao de cores, logo e banner

async function carregarConfiguracoes() {
  try {
    const response = await fetch(API_URL + '/configuracoes.php?action=obter', { cache: 'no-store' });
    const data = await response.json();
    
    if (data.sucesso && data.dados) {
      // Cores
      document.getElementById('corPrimaria').value = data.dados.cor_primaria || '#2c3e50';
      document.getElementById('corPrimariaText').value = data.dados.cor_primaria || '#2c3e50';
      document.getElementById('corSecundaria').value = data.dados.cor_secundaria || '#3498db';
      document.getElementById('corSecundariaText').value = data.dados.cor_secundaria || '#3498db';
      document.getElementById('corAcentuacao').value = data.dados.cor_acentuacao || '#e74c3c';
      document.getElementById('corAcentuacaoText').value = data.dados.cor_acentuacao || '#e74c3c';
      document.getElementById('corTexto').value = data.dados.cor_texto || '#ecf0f1';
      document.getElementById('corTextoText').value = data.dados.cor_texto || '#ecf0f1';
      document.getElementById('corFundoCard').value = data.dados.cor_fundo_card || '#34495e';
      document.getElementById('corFundoCardText').value = data.dados.cor_fundo_card || '#34495e';
      
      // Nome da cantina
      document.getElementById('nomeCantina').value = data.dados.nome_cantina || 'Programa Cantina';
      
      // Impressora Térmica
      const nome = data.dados.impressora_nome || '';
      const largura = data.dados.impressora_largura || '80';
      const autoprint = data.dados.impressora_autoprint || 'nao';
      const fonte = data.dados.impressora_fonte || '9';
      const inpNome = document.getElementById('impressoraNome');
      if (inpNome) inpNome.value = nome;
      const selLargura = document.getElementById('impressoraLargura');
      if (selLargura) selLargura.value = largura;
      const selAuto = document.getElementById('impressoraAutoprint');
      if (selAuto) selAuto.value = autoprint;
      const inpFonte = document.getElementById('impressoraFonte');
      if (inpFonte) inpFonte.value = fonte;
      
      // Logo
      if (data.dados.logo_url) {
        document.getElementById('logoPreview').innerHTML = `<img src="${data.dados.logo_url}" alt="Logo">`;
        document.getElementById('btnDeletarLogo').style.display = 'inline-block';
      }
      
      // Banner
      if (data.dados.banner_url) {
        document.getElementById('bannerPreview').innerHTML = `<img src="${data.dados.banner_url}" alt="Banner">`;
        document.getElementById('btnDeletarBanner').style.display = 'inline-block';
      }
    }
  } catch (error) {
    console.error('Erro ao carregar configuracões:', error);
  }
}

// Sincronizar cores entre input color e text
document.getElementById('corPrimaria')?.addEventListener('input', (e) => {
  document.getElementById('corPrimariaText').value = e.target.value;
});
document.getElementById('corPrimariaText')?.addEventListener('input', (e) => {
  document.getElementById('corPrimaria').value = e.target.value;
});

document.getElementById('corSecundaria')?.addEventListener('input', (e) => {
  document.getElementById('corSecundariaText').value = e.target.value;
});
document.getElementById('corSecundariaText')?.addEventListener('input', (e) => {
  document.getElementById('corSecundaria').value = e.target.value;
});

document.getElementById('corAcentuacao')?.addEventListener('input', (e) => {
  document.getElementById('corAcentuacaoText').value = e.target.value;
});
document.getElementById('corAcentuacaoText')?.addEventListener('input', (e) => {
  document.getElementById('corAcentuacao').value = e.target.value;
});

document.getElementById('corTexto')?.addEventListener('input', (e) => {
  document.getElementById('corTextoText').value = e.target.value;
});
document.getElementById('corTextoText')?.addEventListener('input', (e) => {
  document.getElementById('corTexto').value = e.target.value;
});

document.getElementById('corFundoCard')?.addEventListener('input', (e) => {
  document.getElementById('corFundoCardText').value = e.target.value;
});
document.getElementById('corFundoCardText')?.addEventListener('input', (e) => {
  document.getElementById('corFundoCard').value = e.target.value;
});

async function salvarCores() {
  const cores = {
    cor_primaria: document.getElementById('corPrimaria').value,
    cor_secundaria: document.getElementById('corSecundaria').value,
    cor_acentuacao: document.getElementById('corAcentuacao').value,
    cor_texto: document.getElementById('corTexto').value,
    cor_fundo_card: document.getElementById('corFundoCard').value
  };
  
  try {
    for (const [chave, valor] of Object.entries(cores)) {
      const formData = new FormData();
      formData.append('chave', chave);
      formData.append('valor', valor);
      
      await fetch(API_URL + '/configuracoes.php?action=atualizar', {
        method: 'POST',
        body: formData
      });
    }
    
    mostrarMensagem('configMessage', '✓ Cores atualizadas com sucesso! Atualize a página para ver as mudanças.', 'success');
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao salvar cores', 'error');
  }
}

function resetarCores() {
  if (confirm('Deseja restaurar as cores padrão?')) {
    document.getElementById('corPrimaria').value = '#2c3e50';
    document.getElementById('corPrimariaText').value = '#2c3e50';
    document.getElementById('corSecundaria').value = '#3498db';
    document.getElementById('corSecundariaText').value = '#3498db';
    document.getElementById('corAcentuacao').value = '#e74c3c';
    document.getElementById('corAcentuacaoText').value = '#e74c3c';
    document.getElementById('corTexto').value = '#ecf0f1';
    document.getElementById('corTextoText').value = '#ecf0f1';
    document.getElementById('corFundoCard').value = '#34495e';
    document.getElementById('corFundoCardText').value = '#34495e';
    
    salvarCores();
  }
}

async function salvarNomeCantina() {
  const nome = document.getElementById('nomeCantina').value.trim();
  
  if (!nome) {
    mostrarMensagem('configMessage', 'Digite um nome para a cantina', 'error');
    return;
  }
  
  try {
    const formData = new FormData();
    formData.append('chave', 'nome_cantina');
    formData.append('valor', nome);
    
    const response = await fetch(API_URL + '/configuracoes.php?action=atualizar', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    if (result.sucesso) {
      mostrarMensagem('configMessage', '✓ Nome atualizado com sucesso!', 'success');
    } else {
      mostrarMensagem('configMessage', result.mensagem || 'Erro ao salvar nome', 'error');
    }
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao salvar nome', 'error');
  }
}

// Upload de Logo
document.getElementById('logoFile')?.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  
  const formData = new FormData();
  formData.append('arquivo', file);
  
  try {
    const response = await fetch(API_URL + '/configuracoes.php?action=upload-logo', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    if (result.sucesso) {
      document.getElementById('logoPreview').innerHTML = `<img src="${result.dados.url}" alt="Logo">`;
      document.getElementById('btnDeletarLogo').style.display = 'inline-block';
      mostrarMensagem('configMessage', '✓ Logo enviada com sucesso!', 'success');
    } else {
      mostrarMensagem('configMessage', result.mensagem || 'Erro ao fazer upload', 'error');
    }
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao fazer upload de logo', 'error');
  }
  
  e.target.value = '';
});

// Upload de Banner
document.getElementById('bannerFile')?.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  
  const formData = new FormData();
  formData.append('arquivo', file);
  
  try {
    const response = await fetch(API_URL + '/configuracoes.php?action=upload-banner', {
      method: 'POST',
      body: formData
    });
    
    const result = await response.json();
    if (result.sucesso) {
      document.getElementById('bannerPreview').innerHTML = `<img src="${result.dados.url}" alt="Banner">`;
      document.getElementById('btnDeletarBanner').style.display = 'inline-block';
      mostrarMensagem('configMessage', '✓ Banner enviado com sucesso!', 'success');
    } else {
      mostrarMensagem('configMessage', result.mensagem || 'Erro ao fazer upload', 'error');
    }
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao fazer upload de banner', 'error');
  }
  
  e.target.value = '';
});

async function deletarLogo() {
  if (!confirm('Deseja remover a logo?')) return;
  
  try {
    const response = await fetch(API_URL + '/configuracoes.php?action=deletar-logo', {
      method: 'POST'
    });
    
    const result = await response.json();
    if (result.sucesso) {
      document.getElementById('logoPreview').innerHTML = '<p style="color: var(--muted);">📭 Nenhuma logo enviada</p>';
      document.getElementById('btnDeletarLogo').style.display = 'none';
      mostrarMensagem('configMessage', '✓ Logo removida com sucesso!', 'success');
    }
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao remover logo', 'error');
  }
}

async function deletarBanner() {
  if (!confirm('Deseja remover o banner?')) return;
  
  try {
    const response = await fetch(API_URL + '/configuracoes.php?action=deletar-banner', {
      method: 'POST'
    });
    
    const result = await response.json();
    if (result.sucesso) {
      document.getElementById('bannerPreview').innerHTML = '<p style="color: var(--muted);">📭 Nenhum banner enviado</p>';
      document.getElementById('btnDeletarBanner').style.display = 'none';
      mostrarMensagem('configMessage', '✓ Banner removido com sucesso!', 'success');
    }
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao remover banner', 'error');
  }
}

async function detectarImpressoras() {
  const lista = document.getElementById('impressoraLista');
  if (!lista) return;
  lista.style.display = 'block';
  lista.innerHTML = '<option value="">⏳ Detectando...</option>';
  try {
    const r = await fetch(API_URL + '/impressora.php?action=listar');
    const body = await r.text();
    const json = body ? JSON.parse(body) : null;
    if (json && json.sucesso && json.dados.impressoras.length > 0) {
      lista.innerHTML = '<option value="">— selecione a impressora —</option>';
      json.dados.impressoras.forEach(n => {
        const opt = document.createElement('option');
        opt.value = n;
        opt.textContent = n;
        lista.appendChild(opt);
      });
    } else {
      lista.innerHTML = '<option value="">Nenhuma impressora encontrada</option>';
    }
  } catch {
    lista.innerHTML = '<option value="">Erro ao detectar impressoras</option>';
  }
}

async function salvarImpressora() {
  const nome     = document.getElementById('impressoraNome')?.value   || '';
  const largura  = document.getElementById('impressoraLargura')?.value  || '80';
  const autoprint = document.getElementById('impressoraAutoprint')?.value || 'nao';
  const fonte    = document.getElementById('impressoraFonte')?.value    || '9';

  const configs = {
    impressora_nome:      nome,
    impressora_largura:   largura,
    impressora_autoprint: autoprint,
    impressora_fonte:     fonte
  };

  try {
    for (const [chave, valor] of Object.entries(configs)) {
      const formData = new FormData();
      formData.append('chave', chave);
      formData.append('valor', valor);
      await fetch(API_URL + '/configuracoes.php?action=atualizar', {
        method: 'POST',
        body: formData
      });
    }
    mostrarMensagem('configMessage', '✓ Impressora configurada com sucesso!', 'success');
  } catch (error) {
    mostrarMensagem('configMessage', 'Erro ao salvar configurações da impressora', 'error');
  }
}
