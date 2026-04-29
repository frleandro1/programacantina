<?php
/**
 * Sistema de Permissões - Programa Cantina
 * 
 * Define as permissões de cada tipo de usuário
 */

// Definir permissões por tipo de usuário
$PERMISSOES = [
    'admin' => [
        'ver_dashboard' => true,
        'ver_visao_geral' => true,
        'ver_producao' => true,
        'registrar_producao' => true,
        'ver_vendas' => true,
        'registrar_venda' => true,
        'ver_historico_vendas' => true,
        'ver_relatorios' => true,
        'gerenciar_usuarios' => true,
        'gerenciar_produtos' => true,
        'acessar_estoque' => true
    ],
    'gerente' => [
        'ver_dashboard' => true,
        'ver_visao_geral' => true,
        'ver_producao' => true,
        'registrar_producao' => false,
        'ver_vendas' => true,
        'registrar_venda' => false,
        'ver_historico_vendas' => true,
        'ver_relatorios' => true,
        'gerenciar_usuarios' => false,
        'gerenciar_produtos' => false,
        'acessar_estoque' => true
    ],
    'atendente' => [
        'ver_dashboard' => false,
        'ver_visao_geral' => false,
        'ver_producao' => false,
        'registrar_producao' => false,
        'ver_vendas' => true,
        'registrar_venda' => true,
        'ver_historico_vendas' => false,
        'ver_relatorios' => false,
        'gerenciar_usuarios' => false,
        'gerenciar_produtos' => false,
        'acessar_estoque' => false
    ],
    'produtor' => [
        'ver_dashboard' => false,
        'ver_visao_geral' => false,
        'ver_producao' => true,
        'registrar_producao' => true,
        'ver_vendas' => false,
        'registrar_venda' => false,
        'ver_historico_vendas' => false,
        'ver_relatorios' => false,
        'gerenciar_usuarios' => false,
        'gerenciar_produtos' => false,
        'acessar_estoque' => true
    ]
];

/**
 * Verificar se usuário tem permissão para uma ação
 * 
 * @param string $acao - Nome da ação a verificar
 * @return bool - True se tem permissão, false caso contrário
 */
function temPermissaoAcao($acao) {
    global $PERMISSOES;
    
    // Verificar se usuário está logado
    if (!isset($_SESSION['usuario_tipo'])) {
        return false;
    }
    
    $tipo_usuario = $_SESSION['usuario_tipo'];
    
    // Verificar se o tipo de usuário existe nas permissões
    if (!isset($PERMISSOES[$tipo_usuario])) {
        return false;
    }
    
    // Verificar se a ação existe
    if (!isset($PERMISSOES[$tipo_usuario][$acao])) {
        return false;
    }
    
    return $PERMISSOES[$tipo_usuario][$acao];
}

/**
 * Obter todas as permissões do usuário logado
 * 
 * @return array - Array com todas as permissões
 */
function obterPermissoesUsuario() {
    global $PERMISSOES;
    
    if (!isset($_SESSION['usuario_tipo'])) {
        return [];
    }
    
    $tipo_usuario = $_SESSION['usuario_tipo'];
    
    if (!isset($PERMISSOES[$tipo_usuario])) {
        return [];
    }
    
    return $PERMISSOES[$tipo_usuario];
}

/**
 * Obter descrição do tipo de usuário
 * 
 * @param string $tipo - Tipo de usuário
 * @return string - Descrição do tipo
 */
function obterDescricaoTipo($tipo) {
    $descricoes = [
        'admin' => 'Administrador',
        'gerente' => 'Gerente',
        'atendente' => 'Vendedor',
        'produtor' => 'Produtor'
    ];
    
    return $descricoes[$tipo] ?? 'Usuário';
}

/**
 * Obter cor do tipo de usuário
 * 
 * @param string $tipo - Tipo de usuário
 * @return string - Cor em hex
 */
function obterCorTipo($tipo) {
    $cores = [
        'admin' => '#ef4444',      // Vermelho
        'gerente' => '#f59e0b',    // Âmbar
        'atendente' => '#0891b2',  // Cyan
        'produtor' => '#10b981'    // Verde
    ];
    
    return $cores[$tipo] ?? '#6b7280';
}

/**
 * Obter ícone do tipo de usuário
 * 
 * @param string $tipo - Tipo de usuário
 * @return string - Ícone emoji
 */
function obterIconeTipo($tipo) {
    $icones = [
        'admin' => '👑',
        'gerente' => '📊',
        'atendente' => '🛒',
        'produtor' => '🏭'
    ];
    
    return $icones[$tipo] ?? '👤';
}

/**
 * Verificar se usuário é administrador
 * 
 * @return bool
 */
function ehAdmin() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin';
}

/**
 * Verificar se usuário é gerente
 * 
 * @return bool
 */
function ehGerente() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'gerente';
}

/**
 * Verificar se usuário é atendente/vendedor
 * 
 * @return bool
 */
function ehAtendente() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'atendente';
}

/**
 * Verificar se usuário é produtor
 * 
 * @return bool
 */
function ehProdutor() {
    return isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'produtor';
}
?>
