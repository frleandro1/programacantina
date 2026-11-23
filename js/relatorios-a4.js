(() => {
  const PAGE_WIDTH_MM = 297 - 12;
  const PAGE_HEIGHT_MM = 210 - 12;
  const PX_PER_MM = 96 / 25.4;

  function calcularEscala() {
    document.documentElement.style.setProperty('--print-a4-scale', '1');
    const elementos = [document.documentElement, document.body, ...document.querySelectorAll('.container, main')];
    const largura = Math.max(...elementos.map(elemento => elemento.scrollWidth || elemento.offsetWidth || 0));
    const altura = Math.max(...elementos.map(elemento => elemento.scrollHeight || elemento.offsetHeight || 0));
    const escalaLargura = PAGE_WIDTH_MM * PX_PER_MM / Math.max(largura, 1);
    const escalaAltura = PAGE_HEIGHT_MM * PX_PER_MM / Math.max(altura, 1);
    const escala = Math.min(1, escalaLargura, escalaAltura);
    document.documentElement.style.setProperty('--print-a4-scale', Math.max(0.1, escala).toFixed(4));
  }

  window.addEventListener('beforeprint', calcularEscala);
  window.addEventListener('afterprint', () => {
    document.documentElement.style.removeProperty('--print-a4-scale');
  });
})();
