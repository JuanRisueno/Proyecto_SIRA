<?php
/**
 * search_bar.php - Buscador PHP
 */
?>

<?php if ($vista_actual === 'selector_cliente' || $vista_actual === 'gestion_localidades'): ?>
<div class="search-container" style="margin-bottom: 2rem;">
    <form action="dashboard.php" method="GET" class="search-box">
        <!-- Mantener la sección si estamos en localidades -->
        <?php if ($vista_actual === 'gestion_localidades'): ?>
            <input type="hidden" name="seccion" value="localidades">
        <?php endif; ?>

        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="search-icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" name="buscar" placeholder="<?= ($vista_actual === 'gestion_localidades') ? 'Buscar por municipio, provincia o CP...' : 'Buscar por nombre, empresa o CIF...' ?>" value="<?= htmlspecialchars($busqueda ?? '') ?>" autocomplete="off">
        <button type="submit" class="card-btn" style="margin-top:0; width:auto; border-radius: var(--radius-interactive); font-size: 0.8rem; padding: 0.5rem 1rem;">Buscar</button>
        <?php if ($busqueda): ?>
            <a href="dashboard.php<?= ($vista_actual === 'gestion_localidades') ? '?seccion=localidades' : '' ?>" class="card-btn" style="margin-top:0; width:auto; border-radius: var(--radius-interactive); font-size: 0.8rem; padding: 0.5rem 1rem; background: var(--color-bg-input); border: 1px solid var(--border-color); color: var(--color-text-main);">Limpiar</a>
        <?php endif; ?>
    </form>
</div>
<?php endif; ?>
