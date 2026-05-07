# Resumo da Limpeza - Cantina

Data: 2026-04-13

## Objetivo
Manter na pasta principal apenas arquivos operacionais da aplicacao, sem apagar historico util.

## Estrategia aplicada
- Itens nao essenciais foram movidos para a pasta de backup:
- `_backup_nao_usados_2026_04_13`

## Itens movidos para backup

### Pastas
- `_archive_2026_03_11`
- `_archive_2026_03_12`
- `docs`
- `scripts`

### Arquivos
- `CHECKLIST-PENDRIVE.md`
- `criar-movimentacoes.php`
- `criar-tabela-configuracoes.php`
- `criar-usuario-form.php`
- `criar-vendedores.php`
- `debug-logo.php`
- `install.php`
- `LOGO_F_backup.txt`
- `README-PENDRIVE.md`
- `testar-conexao-bd.php`
- `TESTAR-SISTEMA-PENDRIVE.bat`
- `TESTE-CUSTOMIZACAO-CUPOM.md`

## Validacao apos limpeza
Endpoints principais testados com retorno 200:
- /Cantina/login-menu.html
- /Cantina/login.html
- /Cantina/vendas.html
- /Cantina/admin.html
- /Cantina/api/system-status.php

## Observacoes
- Nenhum item foi apagado definitivamente.
- Caso precise restaurar algo, basta mover de `_backup_nao_usados_2026_04_13` para a raiz da pasta Cantina.

---

## Limpeza adicional

Data: 2026-05-06

### Objetivo
Remover da raiz e modulos ativos arquivos sem referencia no codigo atual.

### Estrategia aplicada
- Itens sem uso foram movidos para backup datado:
- `_backup_nao_usados_2026_05_06`

### Arquivos movidos

#### API
- `api/upload.php`
- `api/diagnostico.php`
- `api/diagnostico-pendrive.php`

#### JavaScript
- `js/auth.js`
- `js/utils.js`

### Validacao
- Pasta ativa `api/` permanece com endpoints operacionais principais.
- Pasta ativa `js/` permanece com modulos utilizados pelas telas principais.
- Nenhum arquivo foi apagado definitivamente.
