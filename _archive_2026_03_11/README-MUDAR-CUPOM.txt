╔════════════════════════════════════════════════════════════════╗
║      COMO MUDAR DADOS DO CUPOM DE VENDA - RESUMO               ║
╚════════════════════════════════════════════════════════════════╝


██████████  VÃO JÃ: 3 FORMAS DIFERENTES  ██████████

┌─────────────────────────────────────────────────────────────────┐
│ ⭐ FORMA 1: CUSTOMIZADOR VISUAL (SEM CÓDIGO - RECOMENDADO!)     │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  🌐 ACESSE NO NAVEGADOR:                                       │
│  http://localhost/Cantina/customizador-cupom.html          │
│                                                                  │
│  ✓ SEM MAIS NENHUMA AÇÃO NECESSÁRIA!                          │
│  ✓ Preencha campos, veja em tempo real                        │
│  ✓ Templates prontos: Simples, Completo, Moderno, Minimal  │
│  ✓ Clique "ATUALIZAR CUPOM"                                   │
│  ✓ Próxima venda usa novo cupom!                              │
│                                                                  │
│  CAMPOS QUE PODE MUDAR:                                        │
│  • Nome da Cantina: "PROGRAMA CANTINA" → seu nome            │
│  • Operador: (deixa vazio ou coloca nome vendedor)          │
│  • Caixa: "Caixa 01" (etc)                                    │
│  • Rótulo: "Venda Nº" → "Cupom" / "Recibo"                   │
│  • Títulos: "PRODUTOS" → "ITENS" / "COMPRAS"                │
│  • Mensagens: "Obrigado..." → sua mensagem                   │
│  • Separadores: "====" → "----" / "♦♦♦"                      │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│ ⭐ FORMA 2: EDITAR 1 LINHA (MUDAR SÓ O NOME)                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ARQUIVO: c:\xampp\htdocs\Cantina\js\vendas.js               │
│                                                                  │
│  PROCURE PELA LINHA 196:                                       │
│  cupom += '         PROGRAMA CANTINA\n';                      │
│                                                                  │
│  MUDE PARA (seu nome):                                         │
│  cupom += '         CANTINA DO JOÃO\n';                       │
│                                                                  │
│  SALVE (Ctrl+S)                                                │
│  RECARREGUE vendas.html (F5)                                  │
│  PRONTO! ✓                                                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│ ⭐ FORMA 3: EDITAR TODO O CÓDIGO (PARA T.I)                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ARQUIVO: js/vendas.js                                         │
│  FUNÇÃO: mostrarCupom() (linhas 185-220)                      │
│                                                                  │
│  O QUE PODE MUDAR EM CADA LINHA:                              │
│                                                                  │
│  195:  Separador principal (ex: "========")                   │
│  196:  Nome da cantina (ex: "MINHA CANTINA")                 │
│  197:  Separador                                              │
│  198:  Rótulo cupom (ex: "Cupom Nº:", "Recibo:")            │
│  199:  Data/Hora                                              │
│  201:  Título de produtos (ex: "ITENS", "COMPRAS")          │
│  202:  Separador de seção                                     │
│  206-207: Formato do item/quantidade (ex: "{qtd}x R${preco}") │
│  210:  Separador                                              │
│  211:  Rótulo total (ex: "TOTAL A PAGAR", "SUBTOTAL")       │
│  213:  Mensagem final (ex: "Obrigado!", "Volte sempre!")    │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  COMO EDITAR:                                                  │
│  1. Abra: c:\xampp\htdocs\Cantina\js\vendas.js              │
│  2. Procure: function mostrarCupom(idVenda, itens) {         │
│  3. Mude as linhas que deseja (veja exemplos abaixo)        │
│  4. Salve: Ctrl+S                                            │
│  5. Navegador: Recarregue vendas.html (Ctrl+F5)            │
│  6. Faça uma venda de teste                                  │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  EXEMPLOS DE MUDANÇAS:                                         │
│                                                                  │
│  NOME DA CANTINA:                                              │
│    cupom += '         CANTINA DO JOÃO\n';                    │
│                                                                  │
│  COM EMOJI:                                                    │
│    cupom += '      🍕 CANTINA DELÍCIA 🍕\n';               │
│                                                                  │
│  MUDAR RÓTULO DO CUPOM:                                       │
│    cupom += `Cupom: ${idVenda}\n`;                           │
│    cupom += `Recibo: ${idVenda}\n`;                          │
│    cupom += `Nº Pedido: ${idVenda}\n`;                       │
│                                                                  │
│  MUDAR MENSAGEM:                                              │
│    cupom += '    Muito obrigado pela compra!\n';           │
│    cupom += '    Que Deus abençoe sua compra!\n';          │
│    cupom += '    Volte sempre!\n';                           │
│                                                                  │
│  ADICIONAR INFORMAÇÕES:                                       │
│    cupom += `Tel: (11) 98765-4321\n`;                       │
│    cupom += `Instagram: @cantinajp\n`;                      │
│    cupom += `Seg-Sex: 7h às 20h\n`;                         │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘


════════════════════════════════════════════════════════════════════

📊 COMPARAÇÃO DAS 3 FORMAS:

┌─────────────────┬─────────────────┬──────────────┬──────────────┐
│ CRITÉRIO        │ FORMA 1 (VISUAL)│ FORMA 2 (1L) │ FORMA 3 (RES)│
├─────────────────┼─────────────────┼──────────────┼──────────────┤
│ DIFICULDADE     │ ⭐ Muito fácil  │ ⭐⭐ Normal  │ ⭐⭐⭐ Difícil │
│ Código?         │ Não             │ Pouco        │ Sim          │
│ Tempo estimado  │ 2 min           │ 30 seg       │ 10 min       │
│ Requer backup?  │ Não             │ Sim          │ Sim          │
│ Customizações   │ Muitas          │ Uma          │ Todas        │
│ Resultado       │ Imediato        │ Imediato     │ Imediato     │
└─────────────────┴─────────────────┴──────────────┴──────────────┘


════════════════════════════════════════════════════════════════════

🎯 MINHA RECOMENDAÇÃO:

PARA USAR AGORA:
  → Abra: customizador-cupom.html
  → Configure tudo visualmente
  → Veja pré-visualização
  → Pronto! ✓

PARA MUDAR SÓ UMA COISA RÁPIDO:
  → Edite 1 linha em js/vendas.js
  → Salve e recarregue
  → Pronto! ✓

PARA SER CRIATIVO:
  → Use o customizador
  → Experimente templates
  → Combine com edição de código
  → Seja criativo! ✓


════════════════════════════════════════════════════════════════════

📁 ARQUIVOS CRIADOS/MODIFICADOS:

NOVOS:
  ✓ customizador-cupom.html - Interface visual (USE ISTO!)
  ✓ api/cupom-config.php - Salva configs no banco
  ✓ CUSTOMIZAR-CUPOM.txt - Documentação completa
  ✓ MUDAR-DADOS-CUPOM-RAPIDO.txt - Exemplos práticos
  ✓ COMECE-AQUI-CUPOM.txt - Guia rápido inicial

MODIFICADOS:
  ✓ js/vendas.js - Onde o cupom é gerado


════════════════════════════════════════════════════════════════════

🚀 PRIMEIRO PASSO - AGORA:

1. ABRA NO NAVEGADOR:
   http://localhost/Cantina/customizador-cupom.html

2. CONFIGURE:
   - Nome da Cantina: seu nome
   - Mensagem final: sua mensagem
   - Outros campos conforme deseje

3. VEJA PRÉ-VISUALIZAÇÃO:
   Lado direito mostra exatamente como ficará!

4. TESTE:
   - Abra: http://localhost/Cantina/vendas.html
   - Faça uma venda de teste
   - Veja se cupom corresponde ao que customizou

5. PRONTO! ✓


════════════════════════════════════════════════════════════════════

❓ DÚVIDAS COMUNS:

P: Onde vejo o cupom?
R: Em vendas.html, clique "Finalizar Venda"

P: Posso adicionar emojis?
R: SIM! 🍕 🍔 🥤 ✓ ★ etc funcionam perfeitamente

P: Posso voltar ao padrão?
R: SIM! No customizador clique "PADRÃO"

P: Preciso editar código?
R: NÃO! O customizador faz tudo sem código

P: Quebrei algo, como restauro?
R: Delete js/vendas.js → Copie do backup ou redownload

P: Posso usar caracteres especiais?
R: SIM! ◆ ★ ━ ─ etc tudo funciona


════════════════════════════════════════════════════════════════════

💡 EXEMPLO FINAL - ANTES E DEPOIS:

CUPOM ANTES:
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

CUPOM DEPOIS (SEU CUSTOMIZADO):
  ========================================
    🍕 CANTINA DO JOÃO 🍕
  ========================================
  Cupom Nº: 1
  Data: 05/03/2026 às 14:30:45
  Operador: Maria
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
  @cantinajp
  ========================================


════════════════════════════════════════════════════════════════════

✅ TUDO PRONTO!

Você tem 3 formas para escolher:
  1. Visual + Fácil (customizador-cupom.html)
  2. Rápido (1 linha em vendas.js)
  3. Completo (editar tudo em vendas.js)

Qualquer uma funciona! Escolha a que se adequa melhor! 🎉


════════════════════════════════════════════════════════════════════
