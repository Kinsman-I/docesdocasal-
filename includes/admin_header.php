<?php
require_once __DIR__ . '/bootstrap.php';
$admin = require_admin();
$title = $title ?? 'Admin';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/admin/', PHP_URL_PATH) ?: '/admin/';

$encomendasPendentes = 0;
try {
    $encomendasPendentes = (int)db()->query(
        "SELECT COUNT(*) FROM encomendas WHERE status IN ('nova','em_analise')"
    )->fetchColumn();
} catch (Throwable $e) {
    $encomendasPendentes = 0;
}


function admin_active(string $path, string $currentPath): string {
    if ($path === '/admin/') {
        return rtrim($currentPath, '/') === '/admin' ? ' active' : '';
    }
    return str_starts_with($currentPath, rtrim($path, '/')) ? ' active' : '';
}

function admin_nav_link(string $href, string $label, string $icon, string $currentPath): void {
    $active = admin_active($href, $currentPath);
    echo '<a class="admin-nav-link'.$active.'" href="'.e($href).'">'
       . '<span class="admin-nav-icon" aria-hidden="true">'.$icon.'</span>'
       . '<span>'.$label.'</span></a>';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#4A2718">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?=e($title)?> | Doces do Casal</title>
    <link rel="stylesheet" href="/assets/css/style.css?v=<?=@filemtime(__DIR__.'/../assets/css/style.css')?>">
    <link rel="stylesheet" href="/assets/css/admin-app.css?v=<?=@filemtime(__DIR__.'/../assets/css/admin-app.css')?>">
</head>
<body class="admin-app-body">

<div class="admin-mobile-topbar">
    <button class="admin-icon-button" type="button" data-admin-menu-open aria-label="Abrir menu">
        <span></span><span></span><span></span>
    </button>
    <a class="admin-mobile-brand" href="/admin/">
        <img src="<?=e($config['app']['logo'])?>" alt="Doces do Casal">
        <div><small>Doces do Casal</small><strong><?=e($title)?></strong></div>
    </a>
    <a class="admin-icon-button admin-store-button" href="/" aria-label="Ver loja" target="_blank">↗</a>
</div>

<div class="admin-menu-backdrop" data-admin-menu-close></div>
<div class="admin-shell">
    <aside class="admin-side" id="adminSide">
        <div class="admin-side-head">
            <a class="admin-brand" href="/admin/">
                <img class="admin-logo" src="<?=e($config['app']['logo'])?>" alt="Doces do Casal">
                <div><strong>Doces do Casal</strong><small>Painel administrativo</small></div>
            </a>
            <button class="admin-side-close" type="button" data-admin-menu-close aria-label="Fechar menu">×</button>
        </div>

        <nav class="admin-nav">
            <span class="admin-menu-title">VISÃO GERAL</span>
            <?php admin_nav_link('/admin/', 'Dashboard', '⌂', $currentPath); ?>

            <span class="admin-menu-title">OPERAÇÃO</span>
            <?php admin_nav_link('/admin/pedidos/', 'Pedidos', '▣', $currentPath); ?>
            <a class="admin-nav-link<?=admin_active('/admin/encomendas/', $currentPath)?>" href="/admin/encomendas/">
                <span class="admin-nav-icon" aria-hidden="true">✦</span>
                <span>Encomendas</span>
                <?php if ($encomendasPendentes > 0): ?>
                    <span class="admin-nav-badge"><?=$encomendasPendentes > 99 ? '99+' : $encomendasPendentes?></span>
                <?php endif; ?>
            </a>
            <?php admin_nav_link('/admin/produtos/', 'Produtos', '◇', $currentPath); ?>

            <span class="admin-menu-title">ESTOQUE</span>
            <?php admin_nav_link('/admin/estoque/', 'Visão geral', '▤', $currentPath); ?>
            <?php admin_nav_link('/admin/estoque/movimentacoes.php', 'Movimentações', '↕', $currentPath); ?>
            <?php admin_nav_link('/admin/estoque/ajustes.php', 'Ajustes de estoque', '±', $currentPath); ?>

            <span class="admin-menu-title">PRODUÇÃO</span>
            <?php admin_nav_link('/admin/producao/', 'Ordens de produção', '◎', $currentPath); ?>
            <?php admin_nav_link('/admin/producao/fichas.php', 'Fichas técnicas', '≡', $currentPath); ?>
            <?php admin_nav_link('/admin/producao/etiquetas.php', 'Etiquetas', '▱', $currentPath); ?>

            <span class="admin-menu-title">COMPRAS</span>
            <?php admin_nav_link('/admin/compras/necessidades.php', 'Necessidade de compra', '!', $currentPath); ?>
            <?php admin_nav_link('/admin/compras/', 'Compras', '▰', $currentPath); ?>
            <?php admin_nav_link('/admin/compras/fornecedores.php', 'Fornecedores', '♙', $currentPath); ?>

            <span class="admin-menu-title">FINANCEIRO</span>
            <?php admin_nav_link('/admin/financeiro/', 'Dashboard financeiro', '◔', $currentPath); ?>
            <?php admin_nav_link('/admin/financeiro/lancamentos.php', 'Lançamentos', '$', $currentPath); ?>
            <?php admin_nav_link('/admin/financeiro/extrato.php', 'Extrato bancário', '≋', $currentPath); ?>
            <?php admin_nav_link('/admin/financeiro/conciliacao.php', 'Conciliação', '✓', $currentPath); ?>
            <?php admin_nav_link('/admin/financeiro/categorias.php', 'Categorias', '#', $currentPath); ?>

            <span class="admin-menu-title">SITE</span>
            <?php admin_nav_link('/admin/site/', 'Site / Campanhas', '◫', $currentPath); ?>

            <span class="admin-menu-title">ADMINISTRAÇÃO</span>
            <?php admin_nav_link('/admin/configuracoes/', 'Configurações', '⚙', $currentPath); ?>
            <a class="admin-nav-link" href="/" target="_blank"><span class="admin-nav-icon">↗</span><span>Ver loja</span></a>
            <a class="admin-nav-link admin-nav-logout" href="/auth/logout.php"><span class="admin-nav-icon">↪</span><span>Sair</span></a>
        </nav>
    </aside>

    <main class="admin-main">
        <div class="admin-head">
            <div>
                <span class="eyebrow">ADMINISTRAÇÃO</span>
                <h1><?=e($title)?></h1>
            </div>
            <div class="admin-user-chip"><span><?=strtoupper(substr((string)$admin['nome'],0,1))?></span><div><small>Conectado como</small><strong><?=e($admin['nome'])?></strong></div></div>
        </div>
