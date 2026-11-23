<?php

function produto_colunas($conn) {
    static $colunas = null;
    if ($colunas === null) {
        $colunas = [];
        $resultado = $conn->query('SHOW COLUMNS FROM produtos');
        if ($resultado) {
            while ($row = $resultado->fetch_assoc()) {
                $colunas[$row['Field']] = $row;
            }
        }
    }
    return $colunas;
}

function produto_coluna($conn, $preferidas) {
    $colunas = produto_colunas($conn);
    foreach ($preferidas as $coluna) {
        if (isset($colunas[$coluna])) {
            return $coluna;
        }
    }
    return null;
}

function produto_imagem_arquivo($produtoId, $imagem = '') {
    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'produtos';
    $imagem = basename(trim((string)$imagem));
    if ($imagem !== '' && is_file($diretorio . DIRECTORY_SEPARATOR . $imagem)) {
        return $imagem;
    }

    $arquivos = glob($diretorio . DIRECTORY_SEPARATOR . 'produto_' . (int)$produtoId . '_*');
    if (!$arquivos) {
        return '';
    }
    usort($arquivos, static function ($a, $b) {
        preg_match('/_(\d+)\.[^.]+$/', basename($a), $aPartes);
        preg_match('/_(\d+)\.[^.]+$/', basename($b), $bPartes);
        $comparacao = (int)($bPartes[1] ?? 0) <=> (int)($aPartes[1] ?? 0);
        return $comparacao !== 0 ? $comparacao : filemtime($b) <=> filemtime($a);
    });
    foreach ($arquivos as $arquivo) {
        if (is_file($arquivo) && preg_match('/\.(jpe?g|png|gif|webp)$/i', $arquivo)) {
            return basename($arquivo);
        }
    }
    return '';
}
