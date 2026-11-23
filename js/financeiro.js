'use strict';

(() => {
  const el = id => document.getElementById(id);
  const moeda = centavos => (Number(centavos || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency', currency: 'BRL'
  });
  const numero = valor => Number(valor || 0).toLocaleString('pt-BR');
  const escapar = valor => String(valor ?? '').replace(/[&<>"']/g, caractere => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[caractere]));
  let dashboard = { total: {}, eventos: [] };

  function mensagem(texto, erro = false) {
    el('mensagem').textContent = texto;
    el('mensagem').className = `alerta ${erro ? 'erro' : 'sucesso'}`;
  }

  async function carregar() {
    const botao = el('recarregar');
    botao.disabled = true;
    try {
      const resposta = await fetch('api/financeiro.php?action=dashboard', { cache: 'no-store' });
      const json = await resposta.json();
      if (!resposta.ok || !json.sucesso) {
        throw new Error(json.mensagem || 'Não foi possível carregar o dashboard financeiro.');
      }
      dashboard = json.dados;
      el('evento-filtro').innerHTML = '<option value="">Todos os eventos</option>' +
        dashboard.eventos.map(evento => `<option value="${evento.id}">${escapar(evento.data_evento)} — ${escapar(evento.nome)}</option>`).join('');
      renderizar();
      mensagem(`Dashboard atualizado com ${dashboard.eventos.length} evento(s).`);
    } catch (erro) {
      mensagem(erro.message, true);
    } finally {
      botao.disabled = false;
    }
  }

  function renderizar() {
    const selecionado = Number(el('evento-filtro').value || 0);
    const eventos = selecionado
      ? dashboard.eventos.filter(evento => Number(evento.id) === selecionado)
      : dashboard.eventos;
    const total = selecionado ? somarEventos(eventos) : dashboard.total;

    el('indicadores').innerHTML = [
      ['Receita das vendas', moeda(total.receita_centavos), 'positivo'],
      ['Unidades vendidas', numero(total.vendida), ''],
      ['Perdas', numero(total.perdida), 'negativo'],
      ['Doações', numero(total.doada), 'informativo'],
      ['Consumo de voluntários', numero(total.equipe), 'informativo'],
      ['Total de saídas', numero(total.total_saida), ''],
      ['Custos + despesas', moeda(Number(total.custo_centavos || 0) + Number(total.despesas_centavos || 0)), 'negativo'],
      ['Resultado do evento', moeda(total.lucro_centavos), Number(total.lucro_centavos || 0) >= 0 ? 'positivo' : 'negativo']
    ].map(([titulo, valor, classe]) => `
      <div class="kpi-card ${classe === 'positivo' ? 'positivo' : classe === 'negativo' ? 'negativo' : classe === 'informativo' ? 'alerta-card' : ''}">
        <span>${escapar(titulo)}</span>
        <strong>${valor}</strong>
      </div>
    `).join('');

    el('eventos-tabela').innerHTML = eventos.map(evento => `
      <tr>
        <td><strong>${escapar(evento.nome)}</strong><br><small>${escapar(evento.data_evento)}</small></td>
        <td><span class="status ${evento.status}">${evento.status === 'finalizado' ? 'Finalizado' : 'Rascunho'}</span>
          ${evento.caixas_abertos ? `<br><small>${evento.caixas_abertos} caixa(s) aberto(s)</small>` : ''}
          ${evento.pendencias ? `<br><small class="pendente">${evento.pendencias} pendência(s)</small>` : ''}
        </td>
        <td>${numero(evento.vendida)}</td>
        <td class="negativo">${numero(evento.perdida)}</td>
        <td>${numero(evento.doada)}</td>
        <td>${numero(evento.equipe)}</td>
        <td><strong>${numero(evento.total_saida)}</strong></td>
        <td>${moeda(evento.receita_centavos)}</td>
        <td>${moeda(Number(evento.custo_centavos) + Number(evento.despesas_centavos))}</td>
        <td class="${Number(evento.lucro_centavos) >= 0 ? 'positivo' : 'negativo'}"><strong>${moeda(evento.lucro_centavos)}</strong></td>
      </tr>
    `).join('') || '<tr><td colspan="10">Nenhum evento financeiro encontrado.</td></tr>';

    el('produtos-eventos').innerHTML = eventos.map(evento => `
      <article class="card evento-card">
        <div class="evento-titulo">
          <div><h3>${escapar(evento.nome)}</h3><small>${escapar(evento.data_evento)} · ${evento.caixas} caixa(s)</small></div>
          <strong class="${Number(evento.lucro_centavos) >= 0 ? 'positivo' : 'negativo'}">${moeda(evento.lucro_centavos)}</strong>
        </div>
        <div class="table-responsive">
          <table>
            <thead><tr><th>Produto</th><th>Vendidas</th><th>Perdas</th><th>Doações</th><th>Voluntários</th><th>Total</th><th>Receita</th></tr></thead>
            <tbody>${evento.produtos.map(produto => `
              <tr>
                <td>${escapar(produto.nome)}</td>
                <td>${numero(produto.vendida)}</td>
                <td>${numero(produto.perdida)}</td>
                <td>${numero(produto.doada)}</td>
                <td>${numero(produto.equipe)}</td>
                <td><strong>${numero(produto.total_saida)}</strong></td>
                <td>${moeda(produto.receita_centavos)}</td>
              </tr>
            `).join('') || '<tr><td colspan="7">Nenhuma movimentação vinculada.</td></tr>'}</tbody>
          </table>
        </div>
      </article>
    `).join('');
  }

  function somarEventos(eventos) {
    const campos = ['vendida', 'perdida', 'doada', 'equipe', 'total_saida', 'receita_centavos', 'custo_centavos', 'despesas_centavos', 'lucro_centavos'];
    return campos.reduce((total, campo) => {
      total[campo] = eventos.reduce((soma, evento) => soma + Number(evento[campo] || 0), 0);
      return total;
    }, {});
  }

  el('evento-filtro').addEventListener('change', renderizar);
  el('recarregar').addEventListener('click', carregar);
  carregar();
})();
