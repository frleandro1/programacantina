# 🚀 ATALHOS DO PROGRAMA CANTINA

## ⚡ Atalhos Criados

### 1. **🚀 Iniciar Cantina** (RECOMENDADO)
- **O que faz:** Inicia Apache + MySQL + Abre o navegador automaticamente
- **Clique em:** `INICIAR-PROGRAMA.bat`
- **Tempo:** ~5 segundos
- **Resultado:** Você é levado direto para login

### 2. **⛔ Parar Cantina**
- **O que faz:** Encerra todos os serviços
- **Clique em:** `PARAR-PROGRAMA.bat`
- **Quando usar:** Ao final do dia ou para reiniciar

### 3. **🔐 Cantina - Login** (Se Apache já está rodando)
- **Abre:** Página de login
- **URL:** http://localhost/Cantina/login-menu.html

### 4. **🛒 Cantina - Vendas**
- **Abre:** Módulo de vendas direto
- **Req:** Estar logado como vendedor

### 5. **👨‍💼 Cantina - Admin**
- **Abre:** Painel administrativo
- **Req:** Estar logado como admin

### 6. **📊 Cantina - Relatorios**
- **Abre:** Relatórios e análises
- **Req:** Estar logado como admin/gestor

---

## 🎯 COMO CRIAR OS ATALHOS

### Método 1: PowerShell (Rápido)
1. Abra PowerShell como **Administrador**
2. Execute:
```powershell
cd "C:\xampp\htdocs\Cantina"
.\Criar-Atalhos.ps1
```
3. Pronto! Verifique a **área de trabalho**

### Método 2: Manual (Se PowerShell não funcionar)

**Opção A - Criar atalho para INICIAR:**
1. Clique direito na **área de trabalho**
2. Novo → Atalho
3. Cole: `C:\xampp\htdocs\Cantina\INICIAR-PROGRAMA.bat`
4. Nome: `Iniciar Cantina`
5. Conclua

**Opção B - Fixar na barra de tarefas:**
1. Abra a pasta: `C:\xampp\htdocs\Cantina`
2. Clique direito em `INICIAR-PROGRAMA.bat`
3. "Fixar na barra de tarefas"

---

## 💡 FLUXO DE USO RECOMENDADO

### **Início do Dia:**
```
1. Duplo clique em "🚀 Iniciar Cantina"
   ↓
2. Aguarde a tela azul com informações
   ↓
3. Navegador abre automaticamente
   ↓
4. Faça login e comece a vender
```

### **Fim do Dia:**
```
1. Clique em "⛔ Parar Cantina"
   ↓
2. Confirme o encerramento
   ↓
3. Máquina está pronta para desligar
```

---

## 📋 ARQUIVOS IMPORTANTES

```
C:\xampp\htdocs\Cantina\
├── INICIAR-PROGRAMA.bat ............ Inicia tudo automaticamente
├── PARAR-PROGRAMA.bat ............. Para tudo
├── Criar-Atalhos.ps1 .............. Cria atalhos na área de trabalho
├── login-menu.html ................. Página de login
├── vendas.html ..................... Módulo de vendas
├── admin.html ...................... Painel administrativo
└── relatorios.html ................. Relatórios
```

---

## 🔧 TROUBLESHOOTING

### **O navegador não abre automaticamente?**
- Execute `INICIAR-PROGRAMA.bat` manualmente
- Acesse manualmente: http://localhost/Cantina/login-menu.html

### **Apache/MySQL não iniciam?**
- Verifique se portas 80 (Apache) ou 3306 (MySQL) estão livres
- Reinicie o computador
- Abra XAMPP Control Panel manualmente

### **Preciso parar os serviços?**
- Execute `PARAR-PROGRAMA.bat`
- Ou abra XAMPP Control Panel e clique "Stop"

---

## 📞 ATALHOS DIRETOS (Sem Iniciar)

Se Apache já está rodando, use estes atalhos diretos:

| Atalho | URL |
|--------|-----|
| Login | http://localhost/Cantina/login-menu.html |
| Vendas | http://localhost/Cantina/vendas.html |
| Admin | http://localhost/Cantina/admin.html |
| Dashboard | http://localhost/Cantina/dashboard.html |
| Relatórios | http://localhost/Cantina/relatorios.html |
| Monitoramento | http://localhost/Cantina/monitoramento.html |
| Config | http://localhost/Cantina/customizador-cupom.html |

---

## ✅ CREDENCIAIS PADRÃO

### Admin:
```
Usuário: admin
Senha: admin123
```

### Vendedores (Teste):
```
Usuário: joao
Senha: vendedor123

Usuário: maria
Senha: vendedor123

Usuário: pedro
Senha: vendedor123
```

---

## 🎨 CUSTOMIZAÇÃO DOS ATALHOS

Para trocar os ícones dos atalhos:
1. Clique direito no atalho
2. Propriedades
3. Clique em "Alterar Ícone"
4. Escolha um ícone

Ícones sugeridos:
- Iniciar: `C:\Windows\System32\shell32.dll` (ícone 1 - estrela verde)
- Parar: `C:\Windows\System32\shell32.dll` (ícone 27 - X vermelho)

---

**Versão:** 2026-05-20  
**Sistema:** Cantina POS v1.0  
**Plataforma:** Windows + XAMPP
