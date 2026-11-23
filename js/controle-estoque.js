document.addEventListener('DOMContentLoaded', () => {
    const API = 'api/estoque-gestao.php';

    const selectProduto = document.getElementById('produto_id');
    const buscaProduto = document.getElementById('busca_produto');
    const selectTipo = document.getElementById('tipo_operacao');
    const inputQtd = document.getElementById('quantidade');
    const inputPrecoQueima = document.getElementById('preco_queima');
    const selectDestino = document.getElementById('produto_destino_id');
    const inputQtdGerada = document.getElementById('qtd_gerada');

    const boxQueima = document.getElementById('box_queima');
    const boxReaproveitamento = document.getElementById('box_reaproveitamento');
    const sugestaoQueima = document.getElementById('sugestao_queima');
    const previewBox = document.getElementById('preview_financeiro');
    const alertaGeral = document.getElementById('alerta_geral');
    const alertaSemCusto = document.getElementById('alerta_sem_custo');

    const modalEditar = document.getElementById('modalEditarProduto');
    let periodoAtual = 'sessao';
    let produtosCache = [];

    function formatarMoeda(valor) {
        return Number(valor || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarQtd(valor, unidade) {
        const num = Number(valor || 0);
        if (unidade === 'un' && Number.isInteger(num)) {
            return num.toLocaleString('pt-BR', { maximumFractionDigits: 0 });
        }
        return num.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function mostrarAlerta(msg, tipo = 'sucesso') {
        alertaGeral.className = `alerta ${tipo}`;
        alertaGeral.textContent = msg;
        alertaGeral.classList.remove('hidden');
        setTimeout(() => alertaGeral.classList.add('hidden'), 6000);
    }

    async function carregarDashboard() {
        try {
            const res = await fetch(`${API}?action=dashboard&periodo=${encodeURIComponent(periodoAtual)}`, { cache: 'no-store' });
            if (res.status === 401) {
                window.location.href = 'login-menu.html';
                return;
            }
            const data = await res.json();
            if (!data.sucesso) {
                mostrarAlerta(data.mensagem || 'Erro ao carregar dados.', 'erro');
                return;
            }

            // Sessão e navegação
            const sessaoTag = data.sessao.sessao_caixa_id ? ` | Sessão #${data.sessao.sessao_caixa_id}` : '';
            document.getElementById('topbar_cantina').textContent = `${data.sessao.cantina_nome} — Estoque & Lucros${sessaoTag}`;
            document.getElementById('topbar_operador').textContent = `${data.sessao.usuario_nome} (${data.sessao.usuario_cargo})`;
            document.getElementById('btnConfirmarMov').textContent = `Registrar como ${data.sessao.usuario_nome.split(' ')[0]}`;

            if (data.sessao.is_admin) {
                document.getElementById('link_relatorios_perdas').classList.remove('hidden');
                document.getElementById('link_financeiro').classList.remove('hidden');
                document.getElementById('link_admin').classList.remove('hidden');
            }

            // KPIs
            const k = data.kpis;
            document.getElementById('kpi_capital').textContent = formatarMoeda(k.capital_estoque);
            document.getElementById('kpi_lucro').textContent = formatarMoeda(k.lucro_vendas);
            document.getElementById('kpi_receita_sub').textContent = `Receita bruta: ${formatarMoeda(k.receita_bruta)}`;
            document.getElementById('kpi_quebra').textContent = formatarMoeda(k.quebra_transformacao);
            document.getElementById('kpi_perda').textContent = formatarMoeda(k.total_perda_pura);
            document.getElementById('kpi_indice_sub').textContent = `Índice de perda: ${Number(k.indice_perda).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;

            const elRes = document.getElementById('kpi_resultado_real');
            elRes.textContent = formatarMoeda(k.resultado_liquido_real);
            elRes.className = k.resultado_liquido_real >= 0 ? 'texto-verde' : 'texto-vermelho';

            // Aviso sobre produtos sem custo cadastrado
            if (k.produtos_sem_custo > 0) {
                alertaSemCusto.textContent = `ℹ️ Existem ${k.produtos_sem_custo} produto(s) com custo unitário R$ 0,00. Clique em "Ajustar" na tabela de estoque para definir o custo e ter o lucro/prejuízo real exato.`;
                alertaSemCusto.classList.remove('hidden');
            } else {
                alertaSemCusto.classList.add('hidden');
            }

            if (data.sincronizados_agora > 0) {
                mostrarAlerta(`${data.sincronizados_agora} venda(s) do PDV foram contabilizadas automaticamente no histórico!`, 'sucesso');
            }

            // Produtos e selects
            produtosCache = data.produtos || [];
            const prodSelecionadoAntes = selectProduto.value;
            selectProduto.innerHTML = '<option value="">Selecione um produto...</option>';
            selectDestino.innerHTML = '<option value="">Selecione o subproduto...</option>';

            const criticos = [];
            produtosCache.forEach(p => {
                const saldo = Number(p.estoque_atual);
                const minimo = Number(p.estoque_minimo);
                if (saldo <= minimo) criticos.push(p);

                const opt1 = document.createElement('option');
                opt1.value = p.id;
                opt1.dataset.custo = p.custo_unitario;
                opt1.dataset.venda = p.preco_venda;
                opt1.dataset.estoque = p.estoque_atual;
                opt1.dataset.unidade = p.unidade;
                opt1.textContent = `${p.nome} (Saldo: ${formatarQtd(saldo, p.unidade)} ${p.unidade})`;
                selectProduto.appendChild(opt1);

                const opt2 = document.createElement('option');
                opt2.value = p.id;
                opt2.dataset.custo = p.custo_unitario;
                opt2.dataset.unidade = p.unidade;
                opt2.textContent = `${p.nome} (Custo: ${formatarMoeda(p.custo_unitario)}/${p.unidade})`;
                selectDestino.appendChild(opt2);
            });

            if (prodSelecionadoAntes) selectProduto.value = prodSelecionadoAntes;
            filtrarProdutos();

            // Banner de estoque baixo
            const banner = document.getElementById('banner_estoque_baixo');
            if (criticos.length > 0) {
                banner.innerHTML = `
                    <strong>⚠️ Atenção — Estoque Baixo (${criticos.length} ${criticos.length === 1 ? 'item' : 'itens'}):</strong>
                    <ul>
                        ${criticos.map(pc => `
                            <li>
                                <strong>${escapeHtml(pc.nome)}:</strong>
                                Saldo atual de <b>${formatarQtd(pc.estoque_atual, pc.unidade)} ${escapeHtml(pc.unidade)}</b>
                                (Mínimo recomendado: ${formatarQtd(pc.estoque_minimo, pc.unidade)} ${escapeHtml(pc.unidade)})
                            </li>
                        `).join('')}
                    </ul>
                `;
                banner.classList.remove('hidden');
            } else {
                banner.classList.add('hidden');
            }

            // Tabela de estoque
            const tbodyEstoque = document.getElementById('tbody_estoque');
            if (produtosCache.length === 0) {
                tbodyEstoque.innerHTML = '<tr><td colspan="8" style="text-align:center;">Nenhum produto cadastrado.</td></tr>';
            } else {
                tbodyEstoque.innerHTML = produtosCache.map(p => {
                    const saldo = Number(p.estoque_atual);
                    const minimo = Number(p.estoque_minimo);
                    const margem = Number(p.margem_unitaria);
                    const estaBaixo = saldo <= minimo;
                    const zerado = saldo <= 0;

                    let badge = '<span class="badge venda">NORMAL</span>';
                    if (zerado) badge = '<span class="badge perda">ESGOTADO</span>';
                    else if (estaBaixo) badge = '<span class="badge queima">REPOR</span>';

                    return `
                        <tr class="${estaBaixo ? 'linha-critica' : ''}">
                            <td>
                                <strong>${escapeHtml(p.nome)}</strong><br>
                                <small style="color:#64748b;">${escapeHtml(p.categoria)}</small>
                            </td>
                            <td><strong>${formatarQtd(saldo, p.unidade)} ${escapeHtml(p.unidade)}</strong></td>
                            <td>${formatarQtd(minimo, p.unidade)} ${escapeHtml(p.unidade)}</td>
                            <td>${formatarMoeda(p.custo_unitario)}</td>
                            <td>${formatarMoeda(p.preco_venda)}</td>
                            <td class="${margem >= 0 ? 'texto-verde' : 'texto-vermelho'}"><strong>${formatarMoeda(margem)}</strong></td>
                            <td>${badge}</td>
                            <td class="no-print"><button type="button" class="btn-mini btn-editar-rapido" data-id="${p.id}">Ajustar</button></td>
                        </tr>
                    `;
                }).join('');
            }

            // Tabela de histórico / auditoria
            const tbodyHist = document.getElementById('tbody_historico');
            const historico = data.historico || [];
            if (historico.length === 0) {
                tbodyHist.innerHTML = '<tr><td colspan="7" style="text-align:center;">Nenhuma movimentação registrada neste período.</td></tr>';
            } else {
                tbodyHist.innerHTML = historico.map(h => {
                    const resFin = Number(h.resultado_financeiro);
                    const corValor = resFin > 0 ? 'texto-verde' : (resFin < 0 ? 'texto-vermelho' : '');
                    const dt = new Date((h.data_hora || '').replace(' ', 'T'));
                    const dataFmt = isNaN(dt) ? escapeHtml(h.data_hora) : dt.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                    const isPdv = (h.origem === 'PDV' || h.origem === 'PERDA_PDV');
                    const badgeOrigem = isPdv
                        ? '<span class="badge origem-pdv">AUTO PDV</span>'
                        : `<span class="badge origem-manual">${escapeHtml(h.origem || 'MANUAL')}</span>`;

                    return `
                        <tr>
                            <td>${dataFmt}</td>
                            <td><strong>${escapeHtml(h.operador_nome)}</strong></td>
                            <td>${escapeHtml(h.produto_nome)}</td>
                            <td>
                                <span class="badge ${escapeHtml(h.tipo.toLowerCase())}">${escapeHtml(h.tipo)}</span>
                                ${badgeOrigem}
                            </td>
                            <td>${formatarQtd(h.quantidade, h.unidade)} ${escapeHtml(h.unidade)}</td>
                            <td class="${corValor}"><strong>${resFin > 0 ? '+' : ''}${formatarMoeda(resFin)}</strong></td>
                            <td>${escapeHtml(h.observacao)}</td>
                        </tr>
                    `;
                }).join('');
            }

            atualizarSimulador();
        } catch (err) {
            mostrarAlerta('Erro ao conectar ao servidor: ' + err.message, 'erro');
        }
    }

    function atualizarSimulador() {
        const tipo = selectTipo.value;
        const optProd = selectProduto.options[selectProduto.selectedIndex];

        boxQueima.classList.toggle('hidden', tipo !== 'QUEIMA');
        boxReaproveitamento.classList.toggle('hidden', tipo !== 'REAPROVEITAMENTO');

        inputPrecoQueima.required = (tipo === 'QUEIMA');
        inputQtdGerada.required = (tipo === 'REAPROVEITAMENTO');

        if (!optProd || !optProd.value) {
            previewBox.textContent = 'Selecione um produto e quantidade para simular o impacto financeiro em tempo real.';
            return;
        }

        const custo = parseFloat(optProd.dataset.custo) || 0;
        const venda = parseFloat(optProd.dataset.venda) || 0;
        const unidade = optProd.dataset.unidade || 'un';
        const qtd = parseFloat(inputQtd.value) || 0;

        if (tipo === 'QUEIMA') {
            const precoSugerido = (venda * 0.5).toFixed(2);
            sugestaoQueima.textContent = `Custo: ${formatarMoeda(custo)} | Venda Normal: ${formatarMoeda(venda)} | Sugestão 50% OFF: ${formatarMoeda(precoSugerido)}`;
            if (!inputPrecoQueima.value) inputPrecoQueima.value = precoSugerido;
        }

        if (qtd <= 0) {
            previewBox.innerHTML = `Custo unitário: <strong>${formatarMoeda(custo)}</strong> | Preço normal: <strong>${formatarMoeda(venda)}</strong>. Informe a quantidade.`;
            return;
        }

        const custoLote = qtd * custo;
        let htmlPreview = '';

        switch (tipo) {
            case 'ENTRADA':
                htmlPreview = `<strong>Entrada de Produção:</strong> Adicionando ${formatarQtd(qtd, unidade)} ${escapeHtml(unidade)} ao estoque. Custo alocado: <strong>${formatarMoeda(custoLote)}</strong>.`;
                break;
            case 'VENDA': {
                const lucroVenda = (qtd * venda) - custoLote;
                htmlPreview = `<strong>Venda Direta:</strong> Receita de ${formatarMoeda(qtd * venda)} − Custo de ${formatarMoeda(custoLote)} = <span class="${lucroVenda >= 0 ? 'texto-verde' : 'texto-vermelho'}" style="font-weight:bold;">${lucroVenda >= 0 ? 'Lucro' : 'Prejuízo'} de ${formatarMoeda(lucroVenda)}</span>.`;
                break;
            }
            case 'QUEIMA': {
                const pQueima = parseFloat(inputPrecoQueima.value) || 0;
                const resQueima = (qtd * pQueima) - custoLote;
                const statusQueima = resQueima >= 0
                    ? `<span class="texto-verde" style="font-weight:bold;">Lucro reduzido de ${formatarMoeda(resQueima)}</span> (evitou prejuízo total de ${formatarMoeda(custoLote)})`
                    : `<span class="texto-laranja" style="font-weight:bold;">Prejuízo amortizado de ${formatarMoeda(resQueima)}</span> (recuperou ${formatarMoeda(qtd * pQueima)} do custo)`;
                htmlPreview = `<strong>Simulação de Queima:</strong> ${statusQueima}.`;
                break;
            }
            case 'REAPROVEITAMENTO': {
                const optDest = selectDestino.options[selectDestino.selectedIndex];
                const custoDest = optDest && optDest.value ? (parseFloat(optDest.dataset.custo) || 0) : 0;
                const qtdGerada = parseFloat(inputQtdGerada.value) || 0;
                const valorRecuperado = qtdGerada * custoDest;
                const quebraProcesso = valorRecuperado - custoLote;
                htmlPreview = `<strong>Reaproveitamento:</strong> Custo de origem (${formatarMoeda(custoLote)}) vs Valor recuperado no subproduto (${formatarMoeda(valorRecuperado)}).<br>
                               Impacto da transformação: <span class="${quebraProcesso >= 0 ? 'texto-verde' : 'texto-vermelho'}" style="font-weight:bold;">${formatarMoeda(quebraProcesso)}</span>.`;
                break;
            }
            case 'PERDA':
                htmlPreview = `<strong>Perda Não Reaproveitável:</strong> Baixa definitiva sem retorno financeiro. <span class="texto-vermelho" style="font-weight:bold;">Prejuízo direto de -${formatarMoeda(custoLote)}</span>.`;
                break;
        }

        previewBox.innerHTML = htmlPreview;
    }

    function filtrarProdutos() {
        const termo = buscaProduto.value.trim().toLocaleLowerCase('pt-BR');
        let primeiroVisivel = '';
        Array.from(selectProduto.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                return;
            }
            const produto = produtosCache.find(item => String(item.id) === option.value);
            const texto = `${produto?.nome || ''} ${produto?.categoria || ''}`.toLocaleLowerCase('pt-BR');
            option.hidden = termo !== '' && !texto.includes(termo);
            if (!primeiroVisivel && !option.hidden) primeiroVisivel = option.value;
        });
        const selecionado = selectProduto.selectedOptions[0];
        if (termo && (!selecionado || selecionado.hidden) && primeiroVisivel) {
            selectProduto.value = primeiroVisivel;
            atualizarSimulador();
        }
    }

    buscaProduto.addEventListener('input', filtrarProdutos);

    // Submeter movimentação
    document.getElementById('formMovimentacao').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            produto_id: selectProduto.value,
            tipo: selectTipo.value,
            quantidade: inputQtd.value,
            preco_queima: inputPrecoQueima.value,
            produto_destino_id: selectDestino.value,
            qtd_gerada: inputQtdGerada.value,
            observacao: document.getElementById('observacao').value
        };

        try {
            const res = await fetch(`${API}?action=movimentar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.sucesso) {
                mostrarAlerta(data.mensagem, 'sucesso');
                inputQtd.value = '';
                inputPrecoQueima.value = '';
                inputQtdGerada.value = '';
                document.getElementById('observacao').value = '';
                carregarDashboard();
            } else {
                mostrarAlerta(data.mensagem || 'Erro ao registrar movimentação.', 'erro');
            }
        } catch (err) {
            mostrarAlerta('Erro ao conectar ao servidor.', 'erro');
        }
    });

    // Cadastrar novo produto
    document.getElementById('formNovoProduto').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            nome: document.getElementById('np_nome').value,
            categoria: document.getElementById('np_categoria').value,
            unidade: document.getElementById('np_unidade').value,
            custo_unitario: document.getElementById('np_custo').value,
            preco_venda: document.getElementById('np_venda').value,
            estoque_inicial: document.getElementById('np_estoque').value,
            estoque_minimo: document.getElementById('np_minimo').value
        };

        try {
            const res = await fetch(`${API}?action=novo_produto`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.sucesso) {
                mostrarAlerta(data.mensagem, 'sucesso');
                e.target.reset();
                document.getElementById('np_minimo').value = '5';
                carregarDashboard();
            } else {
                mostrarAlerta(data.mensagem || 'Erro ao cadastrar produto.', 'erro');
            }
        } catch (err) {
            mostrarAlerta('Erro ao conectar ao servidor.', 'erro');
        }
    });

    // Abrir modal de edição rápida
    document.getElementById('tbody_estoque').addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-editar-rapido');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        const prod = produtosCache.find(p => p.id === id);
        if (!prod) return;

        document.getElementById('modalEditarTitulo').textContent = `Ajustar — ${prod.nome}`;
        document.getElementById('edit_id').value = prod.id;
        document.getElementById('edit_unidade').value = prod.unidade;
        document.getElementById('edit_custo').value = Number(prod.custo_unitario).toFixed(2);
        document.getElementById('edit_venda').value = Number(prod.preco_venda).toFixed(2);
        document.getElementById('edit_minimo').value = Number(prod.estoque_minimo);
        modalEditar.classList.remove('hidden');
    });

    document.getElementById('btnFecharModalEditar').addEventListener('click', () => {
        modalEditar.classList.add('hidden');
    });

    modalEditar.addEventListener('click', (e) => {
        if (e.target === modalEditar) modalEditar.classList.add('hidden');
    });

    document.getElementById('formEditarRapido').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            id: document.getElementById('edit_id').value,
            unidade: document.getElementById('edit_unidade').value,
            custo_unitario: document.getElementById('edit_custo').value,
            preco_venda: document.getElementById('edit_venda').value,
            estoque_minimo: document.getElementById('edit_minimo').value
        };

        try {
            const res = await fetch(`${API}?action=atualizar_produto_rapido`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.sucesso) {
                modalEditar.classList.add('hidden');
                mostrarAlerta(data.mensagem, 'sucesso');
                carregarDashboard();
            } else {
                mostrarAlerta(data.mensagem || 'Erro ao atualizar.', 'erro');
            }
        } catch (err) {
            mostrarAlerta('Erro ao conectar ao servidor.', 'erro');
        }
    });

    // Filtro rápido de período
    document.querySelectorAll('.pill-btn[data-periodo]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.pill-btn[data-periodo]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            periodoAtual = btn.dataset.periodo;
            carregarDashboard();
        });
    });

    // Sincronizar histórico sob demanda
    document.getElementById('btnSincronizar').addEventListener('click', async () => {
        try {
            const res = await fetch(`${API}?action=sincronizar`, { method: 'POST' });
            const data = await res.json();
            mostrarAlerta(data.mensagem || 'Sincronização concluída.', data.sucesso ? 'sucesso' : 'erro');
            carregarDashboard();
        } catch (err) {
            mostrarAlerta('Erro ao sincronizar histórico.', 'erro');
        }
    });

    // Logout
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

    selectProduto.addEventListener('change', () => {
        inputPrecoQueima.value = '';
        atualizarSimulador();
    });
    selectTipo.addEventListener('change', atualizarSimulador);
    inputQtd.addEventListener('input', atualizarSimulador);
    inputPrecoQueima.addEventListener('input', atualizarSimulador);
    selectDestino.addEventListener('change', atualizarSimulador);
    inputQtdGerada.addEventListener('input', atualizarSimulador);

    carregarDashboard();
});
