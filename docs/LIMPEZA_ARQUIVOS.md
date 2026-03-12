# Auditoria de limpeza de arquivos

Data: 2026-03-11

## Criterios usados
- Referencias internas entre HTML/JS/PHP/docs
- Arquivos sem nenhuma referencia (possivel legado)
- Sobreposicao funcional (mesma finalidade em mais de um arquivo)
- Duplicidade exata por hash

## Resultado rapido
- Duplicados exatos por conteudo: nenhum
- Ha muitos arquivos de teste/diagnostico sem uso no fluxo principal
- Ha variantes de login com sobreposicao funcional

## Fluxo principal recomendado (manter)
- index.html
- login-menu.html
- login.html
- admin.html
- admin-imagens.html
- vendas.html
- dashboard.html
- relatorios.html
- customizador-cupom.html
- api/auth.php
- api/produtos.php
- api/vendas.php
- api/relatorios.php
- api/configuracoes.php
- api/imagens.php
- api/cupom-config.php
- db/config.php
- db/schema.sql
- css/theme.css
- js/dashboard.js
- js/vendas.js
- js/vendas-config.js
- js/relatorios.js
- js/configuracoes.js

## Candidatos para limpeza segura (nao usados pelo app principal)
- refresh.js
- diagnostico.html
- diagnostico-vendas.html
- diagnostico-api.php
- diagnostico-login.php
- diagnostico-senha.php
- diagnostico-sistema.php
- teste-conexao.html
- teste-conexao-db.php
- teste-simples.html
- teste-bd.html
- teste-api-login.php
- teste-api-completo.php
- testar-api-imagens.php
- teste-sistema-imagens.html
- teste-customizacao.html
- teste-numeracao-vendas.html
- verificar-bd.php
- verificar-estrutura.php
- verificar-senha.php
- listar-usuarios.php
- quebrar-hash.php

## Candidatos para revisao manual antes de remover
- monitoramento.html (pagina util, mas nao referenciada)
- criar-usuario-vendas.php (referenciada por admin.html)
- atualizar-campo-imagem.php (aparece so nela mesma; confirmar se ainda usa)
- config-numeracao-vendas.php (usado por teste-numeracao-vendas.html; validar se regra diaria ainda e necessaria)

## Sobreposicao funcional (consolidar)
- Login:
  - login-menu.html (mais completo e escolhido no index)
  - login.html (tambem usado por redirecionamentos)
  - login-simples.html (nao referenciado)
  - login-vendedor.html (nao referenciado)

Sugestao: manter login-menu.html e login.html; arquivar/remover login-simples.html e login-vendedor.html.

## Instalacao e banco (evitar redundancia)
- install.php
- criar-tabelas.php
- criar-bd.php

Sugestao: manter install.php como instalador oficial + db/schema.sql.
Arquivar scripts alternativos de criacao apos validar instalacao em ambiente limpo.

## Duplicidade de documentacao (texto)
Existem varios .txt sobre customizacao de cupom e numeracao diaria com conteudo sobreposto.
Sugestao: manter docs/CUSTOMIZACAO.md e um unico guia rapido no raiz.

## Plano de limpeza em 2 fases
1) Fase segura
- Mover candidatos de limpeza segura para uma pasta de quarentena: _archive_2026_03_11/
- Testar login, vendas, admin, relatorios, imagens e customizador.

2) Fase consolidacao
- Remover variantes de login nao usadas
- Padronizar instalacao em install.php
- Consolidar documentacao redundante em docs/

## Status de execucao
- Fase 1 executada em 2026-03-11: arquivos de teste/diagnostico movidos para _archive_2026_03_11/
- Fase 2 (parcial) executada em 2026-03-11: movidos para _archive_2026_03_11/
  - login-simples.html
  - login-vendedor.html
  - criar-bd.php
  - criar-tabelas.php
- Fase 2 (higienizacao docs raiz) executada em 2026-03-11: movidos para _archive_2026_03_11/
  - CUSTOMIZAR-CUPOM.txt
  - COMECE-AQUI-CUPOM.txt
  - RESOLVE-VENDAS-CUSTOMIZACAO.txt
  - README-NUMERACAO-DIARIA.txt
  - README-MUDAR-CUPOM.txt
  - NUMERACAO-VENDAS-DIARIA.txt
  - MUDAR-DADOS-CUPOM-RAPIDO.txt
  - LEIA-ME-CUSTOMIZACAO.txt
  - SETUP_CUSTOMIZACAO.txt
  - RESUMO-FINAL.txt

Fluxo consolidado atual:
- Entrada: index.html -> login-menu.html
- Login alternativo mantido: login.html
- Instalacao oficial: install.php + db/schema.sql

## Comando sugerido (PowerShell)
```powershell
New-Item -ItemType Directory -Path .\_archive_2026_03_11 -Force | Out-Null
```

Depois mover arquivo por arquivo com Move-Item para facilitar rollback.
