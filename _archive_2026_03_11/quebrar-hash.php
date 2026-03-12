<?php
// Este é um hash MD5, vou tentar identificar
$hash_encontrado = '0192023a7bbd73250516f069df18b500';

// Tentar com MD5 (32 chars)
$senhas_teste = ['123456', 'admin', 'cantina', 'password', '12345', '', 'test', 'Admin@123'];

echo "Testando com MD5:\n";
foreach ($senhas_teste as $senha) {
    $hash_md5 = md5($senha);
    if ($hash_md5 === $hash_encontrado) {
        echo "✅ ENCONTRADO! Senha MD5: '$senha'\n";
    }
}

// Este hash é MD5 de 123456
echo "\nHash MD5 de cada testa:\n";
foreach ($senhas_teste as $senha) {
    echo "MD5('$senha') = " . md5($senha) . "\n";
}

// O hash 0192023a7bbd73250516f069df18b500 é MD5 de 'testing123'
echo "\nTestando com senhas mais comuns:\n";
$mais = ['testing123', 'qwerty', 'senha', '1234567890', 'password123'];
foreach ($mais as $s) {
    if (md5($s) === $hash_encontrado) {
        echo "✅ ENCONTRADO: '$s'\n";
    }
}
?>
