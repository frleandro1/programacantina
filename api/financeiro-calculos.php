<?php

function financeiro_inteiro($valor, $campo, $minimo = 0, $maximo = 1000000000) {
    if (filter_var($valor, FILTER_VALIDATE_INT) === false || $valor < $minimo || $valor > $maximo) {
        throw new InvalidArgumentException("Valor invalido para {$campo}.");
    }
    return (int)$valor;
}

function financeiro_texto($valor, $campo, $limite, $obrigatorio = false) {
    if (!is_string($valor) || strlen($valor) > $limite || ($obrigatorio && trim($valor) === '')) {
        throw new InvalidArgumentException("Preencha corretamente {$campo}.");
    }
    return trim($valor);
}

function financeiro_lista($valor, $campo) {
    if (!is_array($valor) || array_values($valor) !== $valor || count($valor) > 1000) {
        throw new InvalidArgumentException("Lista invalida: {$campo}.");
    }
    return $valor;
}

function financeiro_apurar($produtos, $despesas, $vendas) {
    $receita = 0;
    $custoTotal = 0;
    $estoque = 0;
    $extras = 0;
    $pendencias = [];
    $ids = [];
    $linhas = [];
    foreach ($produtos as $produto) {
        $id = financeiro_inteiro($produto['produto_id'] ?? null, 'produto', 1);
        if (isset($ids[$id])) {
            throw new InvalidArgumentException('Produto repetido. Use varios lotes na mesma linha.');
        }
        $ids[$id] = true;
        $disponivel = 0;
        $reutilizavel = 0;
        $custo = 0;
        $retorno = 0;
        foreach (financeiro_lista($produto['lotes'] ?? null, 'lotes') as $lote) {
            $quantidade = financeiro_inteiro($lote['quantidade'] ?? null, 'quantidade', 1, 1000000);
            $unitario = financeiro_inteiro($lote['custo_centavos'] ?? null, 'custo unitario');
            $sobra = financeiro_inteiro($lote['reaproveitavel'] ?? null, 'sobra reaproveitavel', 0, $quantidade);
            $disponivel += $quantidade;
            $reutilizavel += $sobra;
            $custo += $quantidade * $unitario;
            $retorno += $sobra * $unitario;
        }
        $perdida = financeiro_inteiro($produto['perdida'] ?? null, 'perda', 0, 1000000);
        $doada = financeiro_inteiro($produto['doada'] ?? null, 'doacao', 0, 1000000);
        $equipe = financeiro_inteiro($produto['equipe'] ?? null, 'consumo da equipe', 0, 1000000);
        $vendida = $vendas[$id]['quantidade'] ?? 0;
        $bruta = $vendas[$id]['receita_centavos'] ?? 0;
        $pendente = $disponivel - $vendida - $reutilizavel - $perdida - $doada - $equipe;
        if ($pendente !== 0 || !$produto['lotes']) {
            $pendencias[] = ($produto['nome'] ?? "Produto {$id}") . ": saldo sem destino {$pendente}; confira os lotes e as quantidades.";
        }
        $produto['vendida'] = $vendida;
        $produto['receita_centavos'] = $bruta;
        $produto['disponivel'] = $disponivel;
        $produto['pendente'] = $pendente;
        $produto['custo_total_centavos'] = $custo;
        $produto['retorno_centavos'] = $retorno;
        $produto['ganho_centavos'] = $bruta - ($custo - $retorno);
        $linhas[] = $produto;
        $receita += $bruta;
        $custoTotal += $custo;
        $estoque += $retorno;
    }
    foreach ($vendas as $id => $venda) {
        if (!isset($ids[$id])) {
            $pendencias[] = "Produto vendido sem custo cadastrado: {$id}.";
        }
    }
    foreach ($despesas as $despesa) {
        financeiro_texto($despesa['descricao'] ?? null, 'descricao da despesa', 300, true);
        $extras += financeiro_inteiro($despesa['valor_centavos'] ?? null, 'valor da despesa');
    }
    $lucro = $receita - ($custoTotal - $estoque) - $extras;
    return [
        'produtos' => $linhas,
        'receita_centavos' => $receita,
        'custo_total_centavos' => $custoTotal,
        'retorno_centavos' => $estoque,
        'custo_consumido_centavos' => $custoTotal - $estoque,
        'despesas_centavos' => $extras,
        'lucro_centavos' => $lucro,
        'margem_percentual' => $receita > 0 ? round($lucro / $receita * 100, 2) : null,
        'pendencias' => $pendencias
    ];
}

function financeiro_receita($dados) {
    $nome = financeiro_texto($dados['nome'] ?? null, 'nome da receita', 150, true);
    $rendimento = financeiro_inteiro($dados['rendimento'] ?? null, 'rendimento', 1, 1000000);
    $ingredientes = financeiro_lista($dados['ingredientes'] ?? null, 'ingredientes');
    if (!$ingredientes) {
        throw new InvalidArgumentException('Cadastre pelo menos um ingrediente.');
    }
    $total = 0;
    foreach ($ingredientes as &$ingrediente) {
        $ingrediente['nome'] = financeiro_texto($ingrediente['nome'] ?? null, 'ingrediente', 150, true);
        $ingrediente['unidade'] = financeiro_texto($ingrediente['unidade'] ?? null, 'unidade', 30, true);
        $pago = financeiro_inteiro($ingrediente['pago_centavos'] ?? null, 'valor pago');
        foreach (['comprada', 'usada'] as $campo) {
            $valor = $ingrediente[$campo] ?? null;
            if (!is_numeric($valor) || !is_finite((float)$valor) || $valor <= 0 || $valor > 1000000) {
                throw new InvalidArgumentException('Quantidades dos ingredientes devem ser positivas.');
            }
            $ingrediente[$campo] = (float)$valor;
        }
        if ($ingrediente['usada'] > $ingrediente['comprada']) {
            throw new InvalidArgumentException('A quantidade usada nao pode superar a comprada. Informe o total adquirido.');
        }
        $ingrediente['custo_centavos'] = (int)round($pago * $ingrediente['usada'] / $ingrediente['comprada']);
        $total += $ingrediente['custo_centavos'];
    }
    unset($ingrediente);
    return [
        'nome' => $nome, 'rendimento' => $rendimento, 'ingredientes' => $ingredientes,
        'observacoes' => financeiro_texto($dados['observacoes'] ?? '', 'observacoes', 5000),
        'total_centavos' => $total,
        'unitario_centavos' => (int)round($total / $rendimento)
    ];
}
