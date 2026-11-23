document.addEventListener('DOMContentLoaded', () => {
    const API = 'api/estoque-gestao.php';

    const inputInicio = document.getElementById('data_inicio');
    const inputFim = document.getElementById('data_fim');
    const selectSessao = document.getElementById('sessao_id');
    const selectUsuario = document.getElementById('usuario_id');
    const selectCategoria = document.getElementById('categoria');
    const btnCsv = document.getElementById('btnExportarCsv');
    const alertaRel = document.getElementById('alerta_rel');

    const hoje = new Date();
    const trintaDiasAtras = new Date();
    trintaDiasAtras.setDate(hoje.getDate() - 30);

    inputFim.value = hoje.toISOString().split('T')[0];
    inputInicio.value = trintaDiasAtras.toISOString().split('T')[0];

    function formatarMoeda(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarNumero(valor, decimais = 2) {
        return Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: decimais, maximumFractionDigits: decimais });
    }

    function formatarDataBr(iso) {
        if (!iso) return '-';
        const parts = iso.split('-');
        if (parts.length !== 3) return iso;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function mostrarAlerta(msg, tipo = 'erro') {
        alertaRel.className = `alerta ${tipo}`;
        alertaRel.textContent = msg;
        alertaRel.classList.remove('hidden');
    }

    async function carregarRelatorios() {
        alertaRel.classList.add('hidden');

        const baseParams = {
            data_inicio: inputInicio.value,
            data_fim: inputFim.value,
            sessao_id: selectSessao ? selectSessao.value : 'ATUAL',
            usuario_id: selectUsuario.value,
            categoria: selectCategoria.value
        };

        const paramsJson = new URLSearchParams({ action: 'relatorios', ...baseParams });
        const paramsCsv = new URLSearchParams({ action: 'exportar_csv', ...baseParams });
        btnCsv.href = `${API}?${paramsCsv.toString()}`;

        try {
            const res = await fetch(`${API}?${paramsJson.toString()}`, { cache: 'no-store' });
            if (res.status === 401) {
                window.location.href = 'login-menu.html';
                return;
            }
            if (res.status === 403) {
                alert('Acesso restrito: apenas administradores podem visualizar o relatório gerencial de perdas e lucros.');
                window.location.href = 'controle-estoque.html';
                return;
            }
            if (!res.ok) {
                mostrarAlerta(`Não foi possível carregar o relatório (HTTP ${res.status}). Tente novamente.`);
                return;
            }

            const data = await res.json();
            if (!data.sucesso) {
                mostrarAlerta(data.mensagem || 'Erro ao carregar relatório.');
                return;
            }

            const sessaoTag = data.sessao.sessao_caixa_id ? ` (Sessão #${data.sessao.sessao_caixa_id})` : '';
            document.getElementById('topbar_gerente').textContent = data.sessao.usuario_nome + sessaoTag;

            if (selectSessao && selectSessao.options.length === 2 && Array.isArray(data.sessoes)) {
                data.sessoes.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    const dt = (s.abertura || '').replace('T', ' ').slice(0, 16);
                    opt.textContent = `Sessão #${s.id} — ${s.login} (${dt})`;
                    selectSessao.appendChild(opt);
                });
            }

            // Popular selects na primeira carga
            if (selectUsuario.options.length === 1 && Array.isArray(data.operadores)) {
                data.operadores.forEach(op => {
                    const opt = document.createElement('option');
                    opt.value = op.id;
                    opt.textContent = op.nome;
                    selectUsuario.appendChild(opt);
                });
            }

            if (selectCategoria.options.length === 1 && Array.isArray(data.categorias)) {
                data.categorias.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat;
                    opt.textContent = cat;
                    selectCategoria.appendChild(opt);
                });
            }

            // KPIs
            const r = data.resumo_geral;
            document.getElementById('kpi_fat').textContent = formatarMoeda(r.receita_bruta);
            document.getElementById('kpi_fat_sub').textContent = `Normal: ${formatarMoeda(r.faturamento_normal)} | Queima: ${formatarMoeda(r.faturamento_queima)}`;
            document.getElementById('kpi_lucro_vendas').textContent = formatarMoeda(r.lucro_vendas);
            document.getElementById('kpi_queima_sub').textContent = `Recuperado em queima: ${formatarMoeda(r.faturamento_queima)}`;
            document.getElementById('kpi_quebra').textContent = formatarMoeda(r.quebra_transformacao);
            document.getElementById('kpi_perda').textContent = formatarMoeda(r.perda_pura);
            document.getElementById('kpi_indice_sub').textContent = `Índice de perda: ${formatarNumero(r.indice_perda, 1)}%`;

            const elFinal = document.getElementById('kpi_resultado_final');
            elFinal.textContent = formatarMoeda(r.resultado_liquido_real);
            elFinal.className = r.resultado_liquido_real >= 0 ? 'texto-verde' : 'texto-vermelho';

            // 1. Categorias
            const tbodyCat = document.getElementById('tbody_categorias');
            const cats = data.ranking_categorias || [];
            if (cats.length === 0) {
                tbodyCat.innerHTML = '<tr><td colspan="9" style="text-align:center;">Nenhuma movimentação encontrada neste período.</td></tr>';
            } else {
                tbodyCat.innerHTML = cats.map((cat, idx) => {
                    const fatNormal = Number(cat.faturamento_normal);
                    const fatQueima = Number(cat.faturamento_queima);
                    const quebra = Number(cat.quebra_transformacao);
                    const perda = Number(cat.perda_pura);
                    const lucroVendas = Number(cat.lucro_vendas);
                    const resultado = Number(cat.resultado_liquido);
                    const fatTotal = fatNormal + fatQueima;
                    const baseIndice = fatTotal + perda;
                    const indice = baseIndice > 0 ? (perda / baseIndice) * 100 : 0;
                    const classeIndice = indice > 5 ? 'badge perda' : (indice > 2 ? 'badge queima' : 'badge venda');

                    return `
                        <tr>
                            <td><strong>#${idx + 1}</strong></td>
                            <td><strong>${escapeHtml(cat.categoria)}</strong></td>
                            <td>${formatarMoeda(fatNormal)}</td>
                            <td class="texto-laranja">${formatarMoeda(fatQueima)}</td>
                            <td>${formatarMoeda(quebra)}</td>
                            <td class="texto-vermelho"><strong>${formatarMoeda(perda)}</strong> <small>(${cat.qtd_ocorrencias_perda} reg.)</small></td>
                            <td><span class="${classeIndice}">${formatarNumero(indice, 1)}%</span></td>
                            <td class="${lucroVendas >= 0 ? 'texto-verde' : 'texto-vermelho'}">${formatarMoeda(lucroVendas)}</td>
                            <td class="${resultado >= 0 ? 'texto-verde' : 'texto-vermelho'}"><strong>${formatarMoeda(resultado)}</strong></td>
                        </tr>
                    `;
                }).join('');
            }

            // 2. Operadores
            const tbodyOp = document.getElementById('tbody_operadores');
            const ops = data.ranking_operadores || [];
            if (ops.length === 0) {
                tbodyOp.innerHTML = '<tr><td colspan="6" style="text-align:center;">Sem registros no período.</td></tr>';
            } else {
                tbodyOp.innerHTML = ops.map(op => {
                    const resOp = Number(op.resultado_liquido_rs);
                    return `
                        <tr>
                            <td>
                                <strong>${escapeHtml(op.operador_nome)}</strong><br>
                                <small style="color:#64748b;">${escapeHtml(op.cargo)} (${op.total_lancamentos} lanç.)</small>
                            </td>
                            <td>${formatarMoeda(op.total_vendas_rs)}</td>
                            <td class="texto-vermelho"><strong>${formatarMoeda(op.total_perda_rs)}</strong></td>
                            <td class="texto-laranja">${formatarMoeda(op.total_queima_recuperada_rs)}</td>
                            <td>${formatarNumero(op.qtd_reaproveitada, 1)}</td>
                            <td class="${resOp >= 0 ? 'texto-verde' : 'texto-vermelho'}"><strong>${formatarMoeda(resOp)}</strong></td>
                        </tr>
                    `;
                }).join('');
            }

            // 3. Produtos Críticos
            const tbodyViloes = document.getElementById('tbody_viloes');
            const viloes = data.produtos_viloes || [];
            if (viloes.length === 0) {
                tbodyViloes.innerHTML = '<tr><td colspan="5" style="text-align:center;">Nenhuma perda, queima ou reaproveitamento neste período.</td></tr>';
            } else {
                tbodyViloes.innerHTML = viloes.map(pv => `
                    <tr>
                        <td>
                            <strong>${escapeHtml(pv.produto_nome)}</strong><br>
                            <small style="color:#64748b;">${escapeHtml(pv.categoria)}</small>
                        </td>
                        <td class="texto-vermelho">
                            <strong>${formatarMoeda(pv.valor_perda)}</strong><br>
                            <small>(${formatarNumero(pv.qtd_perda, 2)} ${escapeHtml(pv.unidade)})</small>
                        </td>
                        <td>${formatarNumero(pv.qtd_queima, 2)} ${escapeHtml(pv.unidade)}</td>
                        <td>${formatarNumero(pv.qtd_reaproveitada, 2)} ${escapeHtml(pv.unidade)}</td>
                        <td class="${Number(pv.lucro_vendas) >= 0 ? 'texto-verde' : 'texto-vermelho'}"><strong>${formatarMoeda(pv.lucro_vendas)}</strong></td>
                    </tr>
                `).join('');
            }

            // 4. Evolução Diária
            const tbodyDia = document.getElementById('tbody_diario');
            const dias = data.evolucao_diaria || [];
            if (dias.length === 0) {
                tbodyDia.innerHTML = '<tr><td colspan="5" style="text-align:center;">Nenhuma movimentação no período.</td></tr>';
            } else {
                tbodyDia.innerHTML = dias.map(d => {
                    const perdasDia = Number(d.perda_pura) + Number(d.quebra_transf);
                    const resDia = Number(d.resultado_liquido);
                    return `
                        <tr>
                            <td><strong>${formatarDataBr(d.dia)}</strong></td>
                            <td>${formatarMoeda(d.receita_bruta)}</td>
                            <td class="texto-verde">${formatarMoeda(d.lucro_vendas)}</td>
                            <td class="texto-vermelho">${formatarMoeda(perdasDia)}</td>
                            <td class="${resDia >= 0 ? 'texto-verde' : 'texto-vermelho'}"><strong>${resDia > 0 ? '+' : ''}${formatarMoeda(resDia)}</strong></td>
                        </tr>
                    `;
                }).join('');
            }
        } catch (err) {
            if (err instanceof SyntaxError) {
                mostrarAlerta('O servidor retornou uma resposta inválida. Não foi possível carregar o relatório.');
                return;
            }
            mostrarAlerta('Erro ao carregar relatório: ' + err.message);
        }
    }

    document.getElementById('formFiltros').addEventListener('submit', (e) => {
        e.preventDefault();
        carregarRelatorios();
    });

    document.getElementById('btnSair').addEventListener('click', async () => {
        if (!confirm('Deseja sair do sistema?')) return;
        await fetch('api/auth.php?action=logout', { method: 'POST' }).catch(() => {});
        localStorage.removeItem('token');
        localStorage.removeItem('usuario');
        localStorage.removeItem('username');
        localStorage.removeItem('userType');
        localStorage.removeItem('sessaoCaixaId');
        localStorage.removeItem('aberturaCaixaEm');
        window.location.href = 'login-menu.html';
    });

    carregarRelatorios();
});
