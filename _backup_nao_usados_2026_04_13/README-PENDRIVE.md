# 🚀 Guia de Execução - Programa Cantina no Pendrive

## 📋 Pré-requisitos

1. **XAMPP Portável** instalado na unidade E: (`E:\xampp`)
   - Se não tiver, baixe em: https://www.apachefriends.org/download.html
   - Escolha a versão "Portable"
   - Extraia em `E:\xampp`

2. **Pasta do Projeto** em `E:\htdocs\Cantina`
   - Já está aqui! ✓

3. **Banco de Dados MySQL** já criado
   - Execute `E:\htdocs\Cantina\db\schema.sql` no phpMyAdmin

## 🎯 Como Executar

### Opção 1: Programa de Vendas
```
E:\htdocs\Cantina\INICIAR_VENDAS.bat
```

### Opção 2: Programa Cantina (Admin)
```
E:\htdocs\Cantina\INICIAR_CANTINA.bat
```

## 🔧 Configurações Automáticas

✅ Os arquivos `.bat` foram atualizados para:
- Detectar a unidade E: automaticamente
- Iniciar XAMPP do pendrive
- Carregar o projeto de E:\htdocs\Cantina

✅ Novo arquivo de configuração:
- `db/config-pendrive.php` - Detecta automaticamente se está no pendrive

## 🧪 Diagnosti de Conexão

Após iniciar o programa, acesse:
```
http://localhost/Cantina/api/diagnostico-pendrive.php
```

Esto vai verificar:
- Se está rodando do pendrive
- Conexão com banco de dados
- Tabelas criadas
- Usuários cadastrados
- Estrutura de pastas

## 📂 Estrutura Esperada no Pendrive

```
E:\
├── xampp\              ← XAMPP portável
│   ├── apache\
│   ├── mysql\
│   ├── php\
│   └── xampp_start.exe
│
└── htdocs\             ← Projeto
    └── Cantina\
        ├── api\
        ├── css\
        ├── js\
        ├── db\
        ├── uploads\
        ├── logs\
        ├── INICIAR_VENDAS.bat
        ├── INICIAR_CANTINA.bat
        └── vendas.html
```

## ⚠️ Possíveis Problemas

### "XAMPP não encontrado"
- Verifique se XAMPP está em `E:\xampp`
- Execute `E:\xampp\xampp_start.exe`

### "Erro ao conectar ao banco"
- Abra `E:\xampp\phpMyAdmin`
- Crie o banco: `programa_cantina`
- Importe: `E:\htdocs\Cantina\db\schema.sql`

### "Página não carrega"
- Aguarde 10 segundos para XAMPP iniciar
- Verifique em: `http://localhost/Cantina/api/diagnostico-pendrive.php`

## 🛠️ Manutenção

### Alterar configurações do banco
Edite: `E:\htdocs\Cantina\db\config.php`

### Ver logs de erros
```
E:\htdocs\Cantina\logs\
```

### Acessar phpMyAdmin
```
http://localhost/phpmyadmin
```

---

**Criado em:** 2026-04-08 | **Versão:** 1.0 (Pendrive)
