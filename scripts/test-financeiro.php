<?php
require_once __DIR__ . '/../api/financeiro-calculos.php';

function conferir($condicao, $mensagem) {
    if (!$condicao) {
        throw new RuntimeException($mensagem);
    }
}

function rejeitar($acao, $mensagem) {
    try {
        $acao();
    } catch (InvalidArgumentException $e) {
        return;
    }
    throw new RuntimeException($mensagem);
}

$produto = [
    'produto_id' => 1, 'nome' => 'Pastel',
    'lotes' => [
        ['quantidade' => 20, 'custo_centavos' => 200, 'reaproveitavel' => 5],
        ['quantidade' => 10, 'custo_centavos' => 300, 'reaproveitavel' => 2]
    ],
    'perdida' => 1, 'doada' => 1, 'equipe' => 1
];
$vendas = [1 => ['quantidade' => 20, 'receita_centavos' => 10000]];
$a = financeiro_apurar([$produto], [['descricao' => 'Gas', 'valor_centavos' => 1000]], $vendas);
conferir($a['custo_total_centavos'] === 7000, 'Custo dos dois lotes.');
conferir($a['retorno_centavos'] === 1600, 'Retorno deve preservar o custo de cada lote.');
conferir($a['custo_consumido_centavos'] === 5400, 'Perda, doacao e equipe devem integrar o custo.');
conferir($a['lucro_centavos'] === 3600 && $a['margem_percentual'] === 36.0, 'Lucro e margem com despesa extra.');
conferir(!$a['pendencias'], 'Quantidades conciliadas nao devem gerar pendencias.');

$produto['equipe'] = 0;
$a = financeiro_apurar([$produto], [], $vendas);
conferir(count($a['pendencias']) === 1 && $a['produtos'][0]['pendente'] === 1, 'Sobra sem destino deve impedir conclusao.');
$a = financeiro_apurar([], [], $vendas);
conferir(count($a['pendencias']) === 1, 'Venda sem custo deve gerar pendencia.');
$produto['lotes'][0]['reaproveitavel'] = 21;
rejeitar(function () use ($produto, $vendas) { financeiro_apurar([$produto], [], $vendas); }, 'Sobra maior que lote deve ser rejeitada.');
$produto['lotes'] = [['quantidade' => 5, 'custo_centavos' => 200, 'reaproveitavel' => 0]];
$produto['perdida'] = 5;
$produto['doada'] = 0;
$a = financeiro_apurar([$produto], [], []);
conferir($a['lucro_centavos'] === -1000 && $a['margem_percentual'] === null, 'Evento sem receita deve mostrar prejuizo e margem nao aplicavel.');
$produto['lotes'][0]['custo_centavos'] = 0;
$produto['perdida'] = 0;
$a = financeiro_apurar([$produto], [], [1 => ['quantidade' => 5, 'receita_centavos' => 2500]]);
conferir($a['lucro_centavos'] === 2500 && !$a['pendencias'], 'Doacao recebida com custo zero.');
rejeitar(function () use ($produto) { financeiro_apurar([$produto, $produto], [], []); }, 'Produtos repetidos devem ser rejeitados.');
rejeitar(function () { financeiro_inteiro('1.5', 'quantidade'); }, 'Quantidade fracionada deve ser rejeitada.');

$receita = [
    'nome' => 'Bolo', 'rendimento' => 10, 'observacoes' => 'Acucar doado',
    'ingredientes' => [
        ['nome' => 'Farinha', 'unidade' => 'g', 'pago_centavos' => 1000, 'comprada' => 1000, 'usada' => 250],
        ['nome' => 'Acucar', 'unidade' => 'g', 'pago_centavos' => 0, 'comprada' => 500, 'usada' => 100],
        ['nome' => 'Embalagem', 'unidade' => 'un', 'pago_centavos' => 500, 'comprada' => 100, 'usada' => 10]
    ]
];
$ficha = financeiro_receita($receita);
conferir($ficha['total_centavos'] === 300 && $ficha['unitario_centavos'] === 30, 'Custo proporcional, doacao e embalagem.');
$receita['rendimento'] = 0;
rejeitar(function () use ($receita) { financeiro_receita($receita); }, 'Rendimento zero deve ser rejeitado.');
$receita['rendimento'] = 10;
$receita['ingredientes'][0]['comprada'] = 0;
rejeitar(function () use ($receita) { financeiro_receita($receita); }, 'Compra com quantidade zero deve ser rejeitada.');
$receita['ingredientes'][0]['comprada'] = 1;
rejeitar(function () use ($receita) { financeiro_receita($receita); }, 'Uso superior a compra deve ser rejeitado.');
echo "OK: calculos financeiros e validacoes.\n";
