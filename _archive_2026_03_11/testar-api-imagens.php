<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Teste API de Imagens</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .resultado { border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #f5f5f5; }
        .erro { color: red; }
        .sucesso { color: green; }
        button { padding: 10px 20px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Teste da API de Imagens</h1>
    
    <button onclick="testarListar()">Testar: /api/imagens.php?action=list</button>
    <button onclick="testarConexao()">Testar: Conexão com BD</button>
    
    <div id="resultado" class="resultado" style="display: none;"></div>
    
    <script>
        async function testarListar() {
            const div = document.getElementById('resultado');
            div.style.display = 'block';
            div.innerHTML = '<p>Aguardando resposta...</p>';
            
            try {
                const response = await fetch('/Cantina/api/imagens.php?action=list');
                const texto = await response.text();
                
                div.innerHTML = `
                    <h3>Status HTTP: ${response.status}</h3>
                    <h3>Content-Type: ${response.headers.get('content-type')}</h3>
                    <h3>Resposta (raw):</h3>
                    <pre>${JSON.stringify(texto, null, 2)}</pre>
                    <h3>Tentando parsear JSON...</h3>
                `;
                
                if (texto) {
                    try {
                        const dados = JSON.parse(texto);
                        div.innerHTML += `<pre class="sucesso">${JSON.stringify(dados, null, 2)}</pre>`;
                    } catch (e) {
                        div.innerHTML += `<p class="erro">Erro ao parsear JSON: ${e.message}</p>`;
                    }
                } else {
                    div.innerHTML += `<p class="erro">A resposta está vazia!</p>`;
                }
            } catch (e) {
                div.innerHTML = `<p class="erro">Erro na requisição: ${e.message}</p>`;
            }
        }
        
        async function testarConexao() {
            const div = document.getElementById('resultado');
            div.style.display = 'block';
            div.innerHTML = '<p>Testando conexão...</p>';
            
            try {
                const response = await fetch('/Cantina/verificar-bd.php');
                const texto = await response.text();
                div.innerHTML = `<pre>${texto}</pre>`;
            } catch (e) {
                div.innerHTML = `<p class="erro">Erro: ${e.message}</p>`;
            }
        }
    </script>
</body>
</html>
