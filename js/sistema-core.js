(function () {
  const NOME_SISTEMA = 'Cantina IBFC';
  const LABELS_PERFIL = {
    admin: 'Administrador',
    vendedor: 'Ministério',
    administrador: 'Administrador',
    gestor: 'Administrador',
    atendente: 'Ministério',
    producao: 'Ministério',
    usuario: 'Ministério'
  };

  function normalizarTipoUsuario(tipo) {
    const valor = String(tipo || '').trim().toLowerCase();
    if (['admin', 'administrador', 'gestor'].includes(valor)) {
      return 'admin';
    }
    return 'vendedor';
  }

  function isAdminTipo(tipo) {
    return normalizarTipoUsuario(tipo) === 'admin';
  }

  function rotuloTipoUsuario(tipo) {
    const valor = String(tipo || '').trim().toLowerCase();
    return LABELS_PERFIL[valor] || LABELS_PERFIL[normalizarTipoUsuario(tipo)] || 'Ministério';
  }

  function traduzirUsuario(tipo) {
    return rotuloTipoUsuario(tipo);
  }

  function nomeSistemaCompleto() {
    return NOME_SISTEMA;
  }

  window.CantinaSistema = {
    nomeSistema: NOME_SISTEMA,
    nomeSistemaCompleto,
    normalizarTipoUsuario,
    isAdminTipo,
    rotuloTipoUsuario,
    traduzirUsuario,
    perfilLabel: function perfilLabel(tipo) {
      return rotuloTipoUsuario(tipo);
    }
  };
})();
