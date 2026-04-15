# Guia de Customização - Painel de Administrador

## 🎨 Novas Funcionalidades

Você agora tem controle total sobre a aparência do sistema Programa Cantina! Na seção **⚙️ Configurações** do painel de administrador, você pode:

---

## 1. 🎨 Customizar Cores do Tema

No painel admin, clique em **⚙️ Configurações** > **🎨 Cores do Tema**

### Cores Disponíveis para Personalizar:

- **Cor Primária**: Cor base do sistema
- **Cor Secundária**: Cor complementar
- **Cor de Acentuação**: Cor de destaque (botões, links)
- **Cor do Texto**: Cor padrão do texto
- **Cor de Fundo dos Cards**: Fundo dos painéis

### Como Mudar:
1. Clique no **seletor de cores** (quadrado colorido)
2. Ou digite o código HEX diretamente (ex: #FF7A45)
3. Clique em **✓ Salvar Cores**
4. **Atualize a página** para ver as mudanças (F5 ou Ctrl+R)

### Restaurar Padrão:
- Clique em **↻ Restaurar Padrão** para voltar às cores originais

---

## 2. 🏢 Informações da Cantina

### Mudar Nome:
1. No painel admin > **⚙️ Configurações** > **ℹ️ Informações da Cantina**
2. Digite o novo nome (ex: "Cantina da Escola XYZ")
3. Clique em **✓ Salvar Nome**

Este nome aparecerá em:
- Cabeçalho do painel de vendas
- Título da página
- Identificação geral do sistema

---

## 3. 🏷️ Logo da Cantina

### Upload de Logo:
1. No painel admin > **⚙️ Configurações** > **🏷️ Logo da Cantina**
2. Clique em **🗂️ Selecionar Logo**
3. Escolha uma imagem (PNG, JPG, GIF, WebP)
4. **Recomendações**: Logo com fundo transparente (PNG), proporção 4:1

### Onde Aparece:
- ✅ Painel de vendas (topo, abaixo do cabeçalho)
- ✅ Carregada dinamicamente ao abrir a página

### Remover Logo:
- Clique em **🗑️ Remover Logo** (botão aparece quando há logo)

---

## 4. 🎪 Banner de Produtos

### Upload de Banner:
1. No painel admin > **⚙️ Configurações** > **🎪 Banner de Produtos**
2. Clique em **🗂️ Selecionar Banner**
3. Escolha uma imagem (PNG, JPG, GIF, WebP)
4. **Recomendações**: Largura 1200px, altura 250px

### Onde Aparece:
- ✅ Painel de vendas (abaixo do logo)
- ✅ Pode ser usado para promoções, horários ou informações

### Remover Banner:
- Clique em **🗑️ Remover Banner** (botão aparece quando há banner)

---

## 📱 Exemplo de Uso Completo

### Cenário: Customizar para a Semana de Eventos

**Passo 1:** Mudar cores para tema festivo
- Cor Primária: #E74C3C (vermelho)
- Cor de Acentuação: #F39C12 (laranja)

**Passo 2:** Atualizar nome
- De: "Programa Cantina"
- Para: "Cantina - Semana de Eventos 2 al 6 Março"

**Passo 3:** Adicionar banner promocional
- Foto: banner_semana_eventos.jpg
- Conteúdo: Promoção de 20% em bebidas

**Resultado:** 
- Sistema completamente customizado
- Vendedores veem as mudanças ao atualizar a página
- Clientes reconhecem a promoção imediatamente

---

## ⚡ Dicas Importantes

### Cores
- Use cores com **contraste suficiente** para legibilidade
- Teste em diferentes dispositivos/tamanhos de tela
- As mudanças de cor afetam **todo o sistema** (admin + vendas)

### Logo
- Transparência (PNG) oferece melhor integração visual
- Evite logos muito grandes (afeta performance)
- Logo padrão: altura máx 80px

### Banner
- Use imagens compactadas (otimize para web)
- Tamanho recomendado: máx 1MB por imagem
- Altura ideal: 250px (para melhor visualização em mobile)

### Performance
- Imagens são carregadas automaticamente
- Cache do navegador é ignorado (sempre versão mais recente)
- Mudanças aplicadas ao recarregar a página

---

## 🔄 Fluxo Completo de Customização

```
Admin > ⚙️ Configurações
    ├─ 🎨 Cores → Salvar → Recarregar página
    ├─ ℹ️ Nome → Salvar → Efeito imediato
    ├─ 🏷️ Logo → Upload → Aparece em Vendas
    └─ 🎪 Banner → Upload → Aparece em Vendas
```

---

## ❓ FAQ

**P: Mude de cores mas não vê as mudanças?**
- R: Limpe o cache do navegador (Ctrl+Shift+Delete) e recarregue (F5)

**P: Qual é o tamanho máximo de arquivo para logo/banner?**
- R: Até 5MB (recomendado: < 1MB)

**P: As cores afetam o painel de imagens também?**
- R: Não, apenas o painel principal de vendas e admin

**P: Posso usar caracteres especiais no nome da cantina?**
- R: Sim! Acentuação, símbolos: tudo funciona

**P: Logo/Banner desaparecem após atualizar?**
- R: Verifique se a imagem foi realmente enviada. Se o upload falhar, uma mensagem de erro aparecerá

---

## 📞 Suporte

Se tiver problemas:
1. Verifique o console do navegador (F12 > Console)
2. Teste com uma imagem diferente
3. Limpe o cache do navegador
4. Reinicie o XAMPP se as mudanças não persistirem

---

**Versão:** 1.0  
**Data:** 5 de Março de 2026  
**Sistema:** Programa Cantina v2.0
