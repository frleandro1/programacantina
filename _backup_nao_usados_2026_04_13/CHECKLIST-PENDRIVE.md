# ✅ CHECKLIST - Rodar Programa Cantina do Pendrive E:

## 🔍 PASSO 1: Preparar o Pendrive

- [ ] XAMPP Portable baixado de: https://www.apachefriends.org/download.html
- [ ] XAMPP extraído em: `E:\xampp`
- [ ] Projeto em: `E:\htdocs\Cantina` (já está aqui!)
- [ ] Verificar pasta: `E:\xampp\xampp_start.exe` existe?

**Status:** VERIFICAR COM:
```
E:\xampp\xampp_start.exe
```

---

## 🗄️ PASSO 2: Criar Banco de Dados

### Opção A: Automática (RECOMENDADO)
1. Certifique-se que XAMPP está rodando
2. Acesse: `http://localhost/Cantina/install.php`
3. Clique em "Instalar" e aguarde

### Opção B: Manual
1. Abra: `http://localhost/phpmyadmin`
2. Crie novo banco: `programa_cantina`
3. Importe arquivo: `E:\htdocs\Cantina\db\schema.sql`

---

## 🚀 PASSO 3: Testar Sistema

Execute um destes arquivos:

### Teste Rápido (RECOMENDADO)
```
E:\htdocs\Cantina\TESTAR-SISTEMA-PENDRIVE.bat
```
→ Abre diagnóstico automático em: `http://localhost/Cantina/api/diagnostico-pendrive.php`

### Teste Manual
1. Inicie XAMPP: `E:\xampp\xampp_start.exe`
2. Aguarde 5 segundos
3. Acesse: `http://localhost/Cantina/api/diagnostico-pendrive.php`
4. Verifique todos os itens estão verdes ✓

---

## 🎯 PASSO 4: Rodar Programa

### Programa de Vendas
```
E:\htdocs\Cantina\INICIAR_VENDAS.bat
```
→ Abre: `http://localhost/Cantina/vendas.html`

### Programa Cantina (Admin)
```
E:\htdocs\Cantina\INICIAR_CANTINA.bat
```
→ Abre: `http://localhost/Cantina/login-menu.html`

---

## 🆘 TROUBLESHOOTING

### Problema: "XAMPP não encontrado"
**Solução:**
- Verifique se XAMPP está em `E:\xampp`
- Baixe XAMPP Portable em: https://www.apachefriends.org/download.html
- Extraia em E:\ (automaticamente cria `E:\xampp`)

### Problema: "Erro ao conectar ao banco"
**Solução:**
- Execute: `E:\htdocs\Cantina\install.php`
- Ou importe manualmente em: `http://localhost/phpmyadmin`

### Problema: "Página não carrega"
**Solução:**
- Aguarde 10 segundos após iniciar .bat
- Verifique se XAMPP está rodando (deve ter janela miniaturizada)
- Acesse diagnóstico: `http://localhost/Cantina/api/diagnostico-pendrive.php`

### Problema: "Porta 80 já em uso"
**Solução:**
- Abra: `E:\xampp\xampp-control.exe`
- Clique "Config" → "Service and Port Settings"
- Mude Apache para porta 8080
- Acesse: `http://localhost:8080/Cantina/vendas.html`

---

## 📂 Arquivos Modificados

✅ **INICIAR_VENDAS.bat** - Agora usa E:\
✅ **INICIAR_CANTINA.bat** - Agora usa E:\
✅ **TESTAR-SISTEMA-PENDRIVE.bat** - Novo arquivo de teste
✅ **db/config-pendrive.php** - Novo arquivo de config flexível
✅ **api/diagnostico-pendrive.php** - Novo diagnóstico visual
✅ **README-PENDRIVE.md** - Guia de referência

---

## 📞 Notas

- Todas as mudanças foram feitas para serem **compatíveis com ambos** pendrive (E:) e XAMPP local (C:\xampp)
- O sistema **detecta automaticamente** onde está rodando
- Se precisar alterar banco de dados, edite: `E:\htdocs\Cantina\db\config.php`

---

**Criado em:** 2026-04-08  
**Versão:** 1.0 (Pendrive Setup)
