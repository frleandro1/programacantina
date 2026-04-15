<?php
/**
 * Debug - Verificar se LOGO_F.png está acessível
 * Acesse: http://localhost/Cantina/debug-logo.php
 */

echo "<!DOCTYPE html>";
echo "<html lang='pt-BR'>";
echo "<head>";
echo "    <meta charset='UTF-8'>";
echo "    <title>Debug - Logo</title>";
echo "    <style>";
echo "        body { font-family: Arial; margin: 20px; }";
echo "        .box { background: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0; }";
echo "        .success { background: #d4edda; color: #155724; }";
echo "        .error { background: #f8d7da; color: #721c24; }";
echo "    </style>";
echo "</head>";
echo "<body>";
echo "<h1>🔍 Debug - Verificação do Logo</h1>";

// Verificar se arquivo existe
$logo_path = __DIR__ . '/LOGO_F.png';

echo "<div class='box'>";
echo "<strong>Caminho do arquivo:</strong><br>";
echo $logo_path . "<br><br>";

if (file_exists($logo_path)) {
    echo "<div class='box success'>";
    echo "✅ Arquivo encontrado!<br>";
    echo "Tamanho: " . filesize($logo_path) . " bytes<br>";
    echo "Permissões: " . substr(sprintf('%o', fileperms($logo_path)), -4) . "<br>";
    echo "</div>";
} else {
    echo "<div class='box error'>";
    echo "❌ Arquivo NÃO encontrado!<br>";
    echo "Copie LOGO_F.png para: " . __DIR__;
    echo "</div>";
}
echo "</div>";

// Teste com tag img
echo "<div class='box'>";
echo "<strong>Teste de renderização:</strong><br>";
echo "<img src='LOGO_F.png' alt='Logo' style='height: 50px; border: 1px solid #999;'>";
echo "</div>";

// Listar arquivos na pasta
echo "<div class='box'>";
echo "<strong>Arquivos PNG na pasta:</strong><br>";
$files = glob('*.png');
if (empty($files)) {
    echo "Nenhum arquivo .png encontrado!";
} else {
    foreach ($files as $file) {
        echo "✅ " . $file . " (" . filesize($file) . " bytes)<br>";
    }
}
echo "</div>";

echo "</body>";
echo "</html>";
?>
