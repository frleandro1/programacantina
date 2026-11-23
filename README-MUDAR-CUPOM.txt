════════════════════════════════════════════════════════════════
         🎫 MUDAR DADOS DO CUPOM DE VENDA
════════════════════════════════════════════════════════════════

✅ TAREFA SIMPLES! Você pode fazer de 3 formas:


┌─ FORMA 1: INTERFACE VISUAL (RECOMENDADO) ⭐⭐⭐
│
│  🌐 ACESSE NO NAVEGADOR:
│     http://localhost/Cantina/customizador-cupom.html
│
│  ✓ Sem código, sem programação!
│  ✓ Pré-visualização em tempo real
│  ✓ Templates prontos
│  ✓ Super fácil!
│
│  PROCESSO:
│  1. Abra o link acima
│  2. Preencha os campos que deseja
│  3. Veja a pré-visualização ao lado
│  4. Clique "ATUALIZAR CUPOM"
│  5. Pronto! ✓
│
└───────────────────────────────────────────────────────────────


┌─ FORMA 2: ARRASTANDO CUSTOMIZAÇÕES PRONTAS ⭐⭐
│
│  🎨 NA PÁGINA customizador-cupom.html:
│     Clique em um dos templates:
│     • Template Simples
│     • Template Completo
│     • Template Moderno
│     • Template Minimalista
│
│  RESULTADO: Cupom pronto para usar!
│
└───────────────────────────────────────────────────────────────


┌─ FORMA 3: EDITAR CÓDIGO (Para T.I) ⭐
│
│  📝 ARQUIVO: js/vendas.js
│     LINHAS: 185-220
│     FUNÇÃO: mostrarCupom()
│
│  EXEMPLOS DE MUDANÇA:
│
│  Mudar nome da cantina (linha 196):
│    ANTES: cupom += '         PROGRAMA CANTINA\n';
│    DEPOIS: cupom += '         CANTINA DO JOÃO\n';
│
│  Mudar mensagem final (linha 213):
│    ANTES: cupom += '    Obrigado pela preferência!\n';
│    DEPOIS: cupom += '    Muito obrigado!\n';
│
└───────────────────────────────────────────────────────────────


═══════════════════════════════════════════════════════════════

🎯 O QUE VOCÊ PODE MUDAR:

  ┌─ IDENTIDADE
  │  • 🏪 Nome da cantina/empresa
  │  • 👤 Nome do vendedor/operador
  │  • 💼 Número do caixa
  │
  ├─ RÓTULOS
  │  • "Venda Nº" → "Cupom" / "Recibo"
  │  • "PRODUTOS" → "ITENS" / "COMPRAS"
  │  • "TOTAL" → "TOTAL A PAGAR" / "VALOR"
  │
  ├─ MENSAGENS
  │  • Mensagem 1: "Obrigado pela preferência!"
  │  • Mensagem 2: "Volte sempre!"
  │  • Mensagem 3: "Tel: (11) 99999-9999"
  │
  └─ ESTILO
     • Separadores: "====" / "----" / "◆◆◆"
     • Tamanho das linhas
     • Espaçamento


═══════════════════════════════════════════════════════════════

📋 COMO FICAM OS CUPONS DEPOIS:

ANTES (padrão):
  ========================================
           PROGRAMA CANTINA
  ========================================
  Venda Nº: 1
  Data: 05/03/2026  Hora: 14:30:45
  ========================================
  PRODUTOS
  ----------------------------------------
  Coxinha
    1 x R$ 5.50 = R$ 5.50
  ========================================
  TOTAL: R$ 5.50
  ========================================
  Obrigado pela preferência!
  ========================================

DEPOIS (customizado):
  ========================================
    🍕 CANTINA DO JOÃO 🍕
  ========================================
  Cupom Nº: 1
  Data: 05/03/2026 às 14:30:45
  Operador: Maria
  Caixa 01
  ========================================
  ITENS VENDIDOS
  ----------------------------------------
  Coxinha
    1 unidades x R$ 5.50 = R$ 5.50
  ========================================
  TOTAL A PAGAR: R$ 5.50
  ========================================
  Muito obrigado pela sua compra!
  Volte sempre!
  Tel: (11) 98765-4321
  ========================================


═══════════════════════════════════════════════════════════════

🚀 PASSO A PASSO - FORMA RÁPIDA (VISUAL):

1. ABRA NO NAVEGADOR:
   http://localhost/Cantina/customizador-cupom.html

2. VEJA O FORMULÁRIO COM CAMPOS:
   □ Nome da Cantina
   □ Operador
   □ Caixa
   □ Rótulo do Cupom
   [campos de mensagens...]

3. PREENCHA O QUE DESEJA:
   Nome da Cantina: MINHA CANTINA
   Operador: João Silva
   Mensagem Final: "Obrigado pela compra!"

4. VISUALIZE MUDANÇAS:
   À direita você vê o cupom em tempo real
   (está atualizando enquanto você digita!)

5. ESTÁ BOM?
   ✓ Clique "ATUALIZAR CUPOM"
   ✓ Faça uma venda em vendas.html
   ✓ Veja o novo cupom!

6. QUAL ERA A ANTERIOR NOVAMENTE?
   Clique "PADRÃO" para voltar


═══════════════════════════════════════════════════════════════

📚 DOCUMENTAÇÃO COMPLETA:

Se quiser aprender mais:

  • CUSTOMIZAR-CUPOM.txt
    → Guia detalhado com todos os campos

  • MUDAR-DADOS-CUPOM-RAPIDO.txt
    → Exemplos de mudanças comuns

  • customizador-cupom.html
    → Ferramenta interativa (use no navegador!)

  • js/vendas.js (linhas 185-220)
    → Código comentado da função mostrarCupom()


═══════════════════════════════════════════════════════════════

⚡ DICAS RÁPIDAS:

✓ Máximo 40 caracteres por linha (padrão térmico)
✓ Use emojis: 🍕 🍔 🥤 ✓ ★ etc
✓ Não deixe mensagens em branco desnecessárias
✓ Faça backup antes de editar código
✓ Recarregue o navegador após editar (F5 ou Ctrl+F5)
✓ Teste sempre antes de usar em produção


═══════════════════════════════════════════════════════════════

❌ ERROS COMUNS:

Problema: "Cupom não mudou"
→ Solução: Recarregue vendas.html (Ctrl+F5)

Problema: "Caracteres estranhos aparecem"
→ Solução: Use emojis de fontes confiáveis

Problema: "Não sei editar código"
→ Solução: Use customizador-cupom.html ⭐

Problema: "Quebrou algo"
→ Solução: Delete js/vendas.js → Crie backup → Restaure


═══════════════════════════════════════════════════════════════

🎉 RESUMO:

FORMA MAIS FÁCIL:
  ➜ http://localhost/Cantina/customizador-cupom.html

FORMA MAIS RÁPIDA:
  ➜ Edite linha 196 em js/vendas.js (o nome só)

FORMA MAIS COMPLETA:
  ➜ Leia CUSTOMIZAR-CUPOM.txt


═══════════════════════════════════════════════════════════════

Qualquer dúvida, está tudo documentado! 📚

Bom uso! 🎫✨
