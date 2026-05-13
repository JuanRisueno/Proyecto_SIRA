<?php
/**
 * view_localidades.php - Gestión Administrativa de Municipios y Provincias
 * Refactorizado V11.5: Soporte para vista dual (Lista/Mosaico) con responsividad forzada.
 */

$modo_lista = !$vista_grid_activa;
?>

<div class="list-container" style="margin-top: 1rem;">

    <!-- Avisos de Estado -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'borrado_ok'): ?>
        <div style="background: rgba(46, 204, 113, 0.1); border: 1px solid #2ecc71; color: #2ecc71; padding: 1rem; border-radius: var(--radius-container); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem;">
            <span>✅</span> Localidad eliminada correctamente.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div style="background: rgba(231, 76, 60, 0.1); border: 1px solid var(--color-error); color: var(--color-error); padding: 1rem; border-radius: var(--radius-container); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem;">
            <span>❌</span> <strong>Error:</strong> <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <?php if ($modo_lista): ?>
        <!-- VISTA LISTA (Tabla) -->
        <div class="localidades-list sira-table-container">
            <table class="sira-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Código Postal</th>
                        <th>Municipio</th>
                        <th>Provincia</th>
                        <th style="text-align: center;">Parcelas</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todas_las_localidades as $loc): ?>
                        <tr>
                            <td style="text-align: center;">
                                <?php if ($loc['num_parcelas'] == 0): ?>
                                <?php else: ?>
                                    <span style="opacity: 0.2; font-size: 1.1rem;" title="Localidad con parcelas activa">🏢</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="list-badge-tech badge-muted"><?= htmlspecialchars($loc['codigo_postal']) ?></span></td>
                            <td>
                                <div class="list-cell-main">
                                    <span class="list-main-icon">🏙️</span>
                                    <div class="list-main-stack">
                                        <strong class="list-title"><?= htmlspecialchars($loc['municipio']) ?></strong>
                                        <span class="list-subtitle">Municipio Registrado</span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="list-subtitle"><?= htmlspecialchars($loc['provincia']) ?></span></td>
                            <td style="text-align: center;">
                                <div class="list-data-pair">
                                    <span class="list-status-dot <?= $loc['num_parcelas'] == 0 ? 'status-offline' : 'status-online' ?>"></span>
                                    <strong style="<?= $loc['num_parcelas'] == 0 ? 'color: var(--color-error);' : '' ?>">
                                        <?= $loc['num_parcelas'] ?>
                                    </strong>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    <?php if ($loc['num_parcelas'] > 0): ?>
                                        <a href="dashboard.php?ver_detalle_loc=1&cp=<?= urlencode($loc['codigo_postal']) ?>" class="mini-btn-opt" style="color: var(--color-primary); text-decoration: none;" title="Consultar Activos en <?= htmlspecialchars($loc['municipio']) ?>">🗺️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- VISTA MOSAICO (Tarjetas Premium) -->
    <div class="infra-grid-container" style="<?= $modo_lista ? 'display: none;' : '' ?>">
        <?php foreach ($todas_las_localidades as $loc): ?>
            <div class="inv-smart-card">
                
                <!-- NIVEL 1: CABECERA REGIONAL (Municipio + Badge CP) -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <h3 title="<?= htmlspecialchars($loc['municipio']) ?>">
                            <?= mb_convert_case($loc['municipio'], MB_CASE_TITLE, "UTF-8") ?>
                        </h3>
                        <div class="card-subtitle">
                            <span>📍 <?= mb_convert_case($loc['provincia'], MB_CASE_TITLE, "UTF-8") ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span>España</span>
                        </div>
                    </div>

                    <div class="badge-iot-live">
                        <span class="badge-text-premium">CP <?= htmlspecialchars($loc['codigo_postal']) ?></span>
                    </div>
                </div>

                <!-- NIVEL 2: CORAZÓN TÉCNICO -->
                <div class="card-nivel-tecnico">
                    
                    <!-- Identidad Regional -->
                    <div class="tecnico-bloque-identidad">
                        <div class="tecnico-avatar-icon">🏙️</div>
                        <div class="tecnico-datos-group">
                            <span class="tecnico-label">Zonificación</span>
                            <span class="tecnico-valor-main">Regional SIRA</span>
                        </div>
                    </div>

                    <!-- Contadores Técnicos -->
                    <div class="tecnico-datos-derecha">
                        <div class="tecnico-item-mini">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span style="font-size: 1.1rem; font-weight: 800; color: var(--color-primary);"><?= $loc['num_parcelas'] ?></span>
                                    <span style="font-size: 0.85rem; opacity: 0.5;">🚜</span>
                                </div>
                                <span class="tecnico-label">Parcelas</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIÓN DE EXPLORACIÓN -->
                <a href="dashboard.php?ver_detalle_loc=1&cp=<?= urlencode($loc['codigo_postal']) ?>" 
                   class="stretched-link"></a>
                
                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div></div>
                    <div style="text-align: right;">
                        <span class="list-subtitle" style="font-size: 0.7rem; opacity: 0.5;">EXPLORAR ZONA ➜</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($todas_las_localidades)): ?>
        <div style="text-align: center; padding: 4rem; background: var(--color-bg-card); border-radius: var(--radius-lg); border: 1px dashed var(--border-color); margin-top: 1rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">📍</div>
            <h3 style="color: var(--color-text-main);">No hay localidades registradas</h3>
            <p style="color: var(--color-text-muted);">El catálogo está vacío actualmente.</p>
        </div>
    <?php endif; ?>
</div>
