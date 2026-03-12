# 🗄️ Criar Banco de Dados - Guia Prático

## Opção 1: phpMyAdmin (Mais Fácil) 🖱️

### Passo 1: Abrir phpMyAdmin
- Abra seu navegador
- Digite: `http://localhost/phpmyadmin`
- Faça login (geralmente usuário: `root`, sem senha)

### Passo 2: Criar o Banco
1. No menu esquerdo, clique em **"Novo"**
2. Digite o nome: `programa_cantina`
3. Selecione: `utf8mb4_unicode_ci`
4. Clique em **"Criar"**

### Passo 3: Importar as Tabelas
1. Clique na aba **"SQL"**
2. Copie todo o conteúdo do arquivo `db/schema.sql`
3. Cole no campo SQL
4. Clique em **"Executar"**

---

## Opção 2: Command Line (Terminal) ⚡

### Passo 1: Abrir Terminal/CMD
```bash
# Windows - Abra cmd.exe ou PowerShell
```

### Passo 2: Conectar ao MySQL
```bash
mysql -u root -p
```
(Digite sua senha se tiver, senão apenas pressione Enter)

### Passo 3: Executar o Script
```sql
source "c:/Users/Leandro/Desktop/ProgramaCantina Dezembro/db/schema.sql";
```

Ou execute diretamente:
```bash
mysql -u root < "c:/Users/Leandro/Desktop/ProgramaCantina Dezembro/db/schema.sql"
```

---

## Opção 3: MySQL Workbench 🔧

1. Abra **MySQL Workbench**
2. Conecte ao servidor MySQL
3. Clique em **File > Open SQL Script**
4. Selecione `db/schema.sql`
5. Clique em ⚡ (Execute)

---

## ✅ Verificar se Funcionou

Depois de executar o script, verifique:

### No phpMyAdmin:
1. Na esquerda, procure por `programa_cantina`
2. Clique nele para expandir
3. Você deve ver 4 tabelas:
   - usuarios
   - produtos
   - vendas
   - movimentacoes

### No Command Line:
```bash
mysql -u root -p programa_cantina -e "SHOW TABLES;"
```

---

## 📝 Credenciais Padrão

Após a criação:
- **Usuário:** `admin`
- **Senha:** `admin123`

---

## 🐛 Se Algo Der Errado

### Erro: "Access denied"
- Verifique o usuário e senha em `db/config.php`
- Certifique-se que o MySQL está rodando

### Erro: "Database already exists"
- O banco já foi criado
- Você pode deletar e criar novamente, ou apenas usar o existente

### Erro: "Table already exists"
- Use a opção `ON DUPLICATE KEY UPDATE` (já está no script)

---

## 🔄 Resetar o Banco (Se Necessário)

Execute este comando:
```sql
DROP DATABASE programa_cantina;
```

Depois execute novamente o `schema.sql`

