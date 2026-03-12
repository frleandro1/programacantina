# 🚀 Como Rodar o Projeto (Servidor Web Necessário)

## ⚠️ IMPORTANTE

O projeto **NÃO funciona** abrindo arquivos diretamente (file://)
Você **PRECISA** de um servidor web como Apache ou Nginx

---

## Opção 1: XAMPP (Recomendado para Windows) ✅

### Passo 1: Baixar XAMPP
1. Acesse: https://www.apachefriends.org/pt_BR/index.html
2. Baixe a versão para Windows
3. Instale (use as configurações padrão)

### Passo 2: Copiar Projeto
1. Localize a pasta de instalação do XAMPP (geralmente `C:\xampp`)
2. Entre na pasta `htdocs`
3. **Copie toda a pasta `ProgramaCantina Dezembro` para `htdocs`**

Resultado:
```
C:\xampp\htdocs\ProgramaCantina Dezembro\
├── login.html
├── dashboard.html
├── api/
├── db/
└── ... (outros arquivos)
```

### Passo 3: Iniciar XAMPP
1. Abra **XAMPP Control Panel**
2. Clique em **Start** ao lado de "Apache"
3. Clique em **Start** ao lado de "MySQL"

### Passo 4: Acessar no Navegador
```
http://localhost/ProgramaCantina%20Dezembro/teste-completo.php
```

Ou copie sem espaços:
```
C:\xampp\htdocs\ProgramaCantinaDezembro
```

Então acesse:
```
http://localhost/ProgramaCantinaDezembro/teste-completo.php
```

---

## Opção 2: WampServer (Alternativa)

1. Baixe em: http://www.wampserver.com/
2. Instale
3. Copie o projeto para `C:\wamp64\www\ProgramaCantina`
4. Acesse: `http://localhost/ProgramaCantina/teste-completo.php`

---

## Opção 3: Usar Laravel Valet (Mac/Linux)

```bash
composer global require laravel/valet
valet install
valet link
```

---

## ⚡ Opção 4: PHP Built-in Server (Rápido!)

### Passo 1: Abra PowerShell na pasta do projeto
```powershell
cd "C:\Users\Leandro\Desktop\ProgramaCantina Dezembro"
```

### Passo 2: Inicie o servidor PHP
```powershell
php -S localhost:8000
```

### Passo 3: Acesse no navegador
```
http://localhost:8000/teste-completo.php
```

✅ **Mais rápido e não precisa instalar nada extra!**

---

## 🔧 Verificar se Funciona

Depois de rodar o servidor (qualquer opção), acesse:

```
http://localhost:8000/teste-completo.php
```

(ou a URL que você configurou)

Se ver uma página com "🔍 TESTE COMPLETO DO SISTEMA" = **Sucesso!** ✅

---

## 📝 Resumo de URLs

| Parte | URL |
|-------|-----|
| Teste de Conexão | `http://localhost:8000/teste-conexao.php` |
| Instalar Banco | `http://localhost:8000/install.php` |
| Login | `http://localhost:8000/login.html` |
| Dashboard | `http://localhost:8000/dashboard.html` |

---

## ✅ Qual Método Você Quer Usar?

1. **XAMPP** - Mais fácil, completo (recomendado)
2. **WampServer** - Similar ao XAMPP
3. **PHP Built-in** - Mais rápido, sem instalação extra
4. **Outro servidor** - Você tem algum?

Me avise qual você quer e vou detalhar! 🚀
