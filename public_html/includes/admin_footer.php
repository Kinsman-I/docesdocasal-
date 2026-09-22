    </main>
</div>

<nav class="admin-bottom-nav" aria-label="Navegação rápida">
    <a class="<?=admin_active('/admin/', $currentPath)?>" href="/admin/"><span>⌂</span><small>Início</small></a>
    <a class="<?=admin_active('/admin/pedidos/', $currentPath)?>" href="/admin/pedidos/"><span>▣</span><small>Pedidos</small></a>
    <a class="<?=admin_active('/admin/encomendas/', $currentPath)?>" href="/admin/encomendas/">
        <span>✦</span><small>Encomendas</small>
        <?php if (($encomendasPendentes ?? 0) > 0): ?>
            <b class="admin-bottom-badge"><?=($encomendasPendentes ?? 0) > 99 ? '99+' : (int)$encomendasPendentes?></b>
        <?php endif; ?>
    </a>
    <a class="<?=admin_active('/admin/produtos/', $currentPath)?>" href="/admin/produtos/"><span>◇</span><small>Produtos</small></a>
    <button type="button" data-admin-menu-open><span>☰</span><small>Menu</small></button>
</nav>

<div class="toast" id="toast"></div>
<script src="/assets/js/app.js?v=<?=@filemtime(__DIR__.'/../assets/js/app.js')?>" defer></script>
<script src="/assets/js/admin-app.js?v=<?=@filemtime(__DIR__.'/../assets/js/admin-app.js')?>" defer></script>
</body>
</html>
