<?php
/**
 * view_cultivos.php - Dashboard Dual de Variedades Botánicas (V10.2 Failsafe)
 */

$modo_lista = (($_SESSION['dashboard_view'] ?? 'grid') === 'list');
$user_rol = $_SESSION['user_rol'] ?? '';
$mi_cliente_id = $_SESSION['cliente_id'] ?? null;

// --- MOTOR DE ORDENACIÓN ---
if (!empty($todos_los_cultivos)) {
    usort($todos_los_cultivos, function($a, $b) use ($user_rol, $mi_cliente_id) {
        $es_admin = in_array($user_rol, ['admin', 'root']);
        
        if ($es_admin) {
            $p_a = empty($a['cliente_id']) ? 1 : 2;
            $p_b = empty($b['cliente_id']) ? 1 : 2;
        } else {
            if ($a['cliente_id'] == $mi_cliente_id) $p_a = 1;
            elseif (!empty($a['cliente_id'])) $p_a = 2;
            else $p_a = 3;

            if ($b['cliente_id'] == $mi_cliente_id) $p_b = 1;
            elseif (!empty($b['cliente_id'])) $p_b = 2;
            else $p_b = 3;
        }

        if ($p_a == $p_b) {
            return strcasecmp($a['nombre_cultivo'], $b['nombre_cultivo']);
        }
        return $p_a <=> $p_b;
    });
}
?>

<div class="cultivos-container" style="margin-top: 2rem;">

    <?php if ($modo_lista): ?>
        <div class="cultivos-list sira-table-container">
            <table class="sira-table">
            <thead>
                <tr>
                    <th style="width: 35%;">VARIEDAD</th>
                    <th>PROPIETARIO</th>
                    <th style="text-align: center;">PARÁMETROS (T/H)</th>
                    <th style="text-align: center;">ESTADO</th>
                    <th style="text-align: right;">ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todos_los_cultivos as $cult): 
                    $params = $cult['parametros'] ?? null;
                    $es_dueno = ($cult['cliente_id'] == $mi_cliente_id);
                    $es_admin_eff = in_array($user_rol, ['admin', 'root']);
                    $puede_editar = $es_admin_eff || $es_dueno;
                    $is_target = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $cult['cultivo_id']);
                ?>
                    <tr id="cultivo-card-<?= $cult['cultivo_id'] ?>" 
                        class="<?= $is_target ? 'highlight-glow' : '' ?> <?= empty($cult['activa']) ? 'row-inactive' : '' ?>">
                        <td>
                            <div class="list-cell-main">
                                <span class="list-main-icon"><?= get_crop_icon($cult['nombre_cultivo']) ?></span>
                                <div class="list-main-stack">
                                    <strong class="list-title"><?= htmlspecialchars($cult['nombre_cultivo']) ?></strong>
                                    <span class="cult-scientific" style="font-size: 0.75rem; color: var(--color-text-muted);">
                                        <?= htmlspecialchars($cult['nombre_cientifico'] ?? '') ?>
                                    </span>
                                </div>
                            </div>
                        </td>
                         <td>
                              <span class="list-badge-tech <?= !empty($cult['cliente_id']) ? 'badge-muted' : '' ?>">
                                  <?php 
                                     if ($es_dueno) {
                                         echo 'TU CULTIVO';
                                     } elseif (empty($cult['cliente_id'])) {
                                         echo 'SIRA';
                                     } elseif ($es_admin_eff) {
                                         echo 'CLIENTE #' . htmlspecialchars($cult['cliente_id']);
                                     } else {
                                         echo 'COMUNIDAD';
                                     }
                                  ?>
                              </span>
                         </td>
                        <td style="text-align: center;">
                            <?php if ($params): ?>
                                <div class="list-data-pair">
                                    <span class="list-accent-temp"><?= (int)($params['temp_optima_min'] ?? 0) ?>°-<?= (int)($params['temp_optima_max'] ?? 0) ?>°</span>
                                    <span class="list-separator">|</span>
                                    <span class="list-accent-hum"><?= (int)($params['humedad_optima_min'] ?? 0) ?>-<?= (int)($params['humedad_optima_max'] ?? 0) ?>%</span>
                                </div>
                            <?php else: ?>
                                <span class="list-subtitle" style="font-style: italic;">N/D</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="list-status-dot <?= !empty($cult['activa']) ? 'status-online' : 'status-offline' ?>"></span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <?php if ($puede_editar): ?>
                                    <?= sira_btn('', 'mini', 'edit', ['href' => "formularios/formulario_cultivo.php?id=".$cult['cultivo_id'], 'title' => "Editar cultivo"]) ?>
                                <?php endif; ?>
                                <?php if ($es_admin_eff): ?>
                                    <?= sira_btn('', 'mini', !empty($cult['activa']) ? 'delete' : 'eye', [
                                        'href' => "dashboard.php?seccion=cultivos&accion=status_cultivo&estado=".(!empty($cult['activa']) ? 'desactivar' : 'activar')."&id=".$cult['cultivo_id'],
                                        'style' => "color: ".(!empty($cult['activa']) ? 'var(--color-warning)' : 'var(--color-primary)').";",
                                        'title' => (!empty($cult['activa']) ? 'Ocultar del catálogo' : 'Mostrar en el catálogo')
                                    ]) ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <!-- VISTA MOSAICO (Tarjetas Premium) -->
        <div class="infra-grid-container">
            <?php foreach ($todos_los_cultivos as $cult): 
                $params = $cult['parametros'] ?? null;
                $es_dueno = ($cult['cliente_id'] == $mi_cliente_id);
                $es_admin_eff = in_array($user_rol, ['admin', 'root']);
                $puede_editar = $es_admin_eff || $es_dueno;
                $is_target = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $cult['cultivo_id']);
            ?>
                <div id="cultivo-card-<?= $cult['cultivo_id'] ?>" 
                     class="inv-smart-card <?= $is_target ? 'highlight-glow' : '' ?> <?= empty($cult['activa']) ? 'sira-item-archived' : '' ?>">
                    
                    <!-- NIVEL 1: CABECERA TÉCNICA (Nombre + Badge Origen) -->
                    <div class="card-nivel-header">
                        <div class="card-title-group">
                            <h3 title="<?= htmlspecialchars($cult['nombre_cultivo']) ?>">
                                <?= mb_convert_case($cult['nombre_cultivo'], MB_CASE_TITLE, "UTF-8") ?>
                            </h3>
                            <div class="card-subtitle">
                                <span>🧬 <?= htmlspecialchars($cult['nombre_cientifico'] ?? 'Variedad Botánica') ?></span>
                                <span style="opacity: 0.3;">|</span>
                                <span>#<?= str_pad($cult['cultivo_id'], 3, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>

                        <div class="badge-iot-live" style="background: <?= !empty($cult['activa']) ? 'rgba(16, 185, 129, 0.1)' : 'rgba(100, 116, 139, 0.1)' ?>;">
                            <span class="badge-text-premium" style="color: <?= !empty($cult['activa']) ? 'var(--color-primary)' : '#64748b' ?>;">
                                <?php 
                                    if ($es_dueno) echo 'TU CULTIVO';
                                    elseif (empty($cult['cliente_id'])) echo 'SIRA';
                                    elseif ($es_admin_eff) echo 'CLIENTE #' . $cult['cliente_id'];
                                    else echo 'COMUNIDAD';
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- NIVEL 2: CORAZÓN TÉCNICO -->
                    <div class="card-nivel-tecnico">
                        
                        <!-- Identidad Botánica -->
                        <div class="tecnico-bloque-identidad">
                            <div class="tecnico-avatar-icon">
                                <?= get_crop_icon($cult['nombre_cultivo']) ?>
                            </div>
                            <div class="tecnico-datos-group">
                                <span class="tecnico-label">Entorno Óptimo</span>
                                <span class="tecnico-valor-main">Cielo Abierto</span>
                            </div>
                        </div>

                        <!-- Parámetros Vitales (Mini) -->
                        <div class="tecnico-datos-derecha">
                            <?php if ($params): ?>
                                <div class="tecnico-item-mini">
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #ffab00;"><?= (int)$params['temp_optima_min'] ?>°-<?= (int)$params['temp_optima_max'] ?>°</span>
                                        <span class="tecnico-label">Temp.</span>
                                    </div>
                                </div>
                                <div class="tecnico-item-mini">
                                    <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                        <span style="font-size: 0.85rem; font-weight: 800; color: #00d1ff;"><?= (int)$params['humedad_optima_min'] ?>-<?= (int)$params['humedad_optima_max'] ?>%</span>
                                        <span class="tecnico-label">Hum.</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="list-subtitle" style="font-style: italic; font-size: 0.65rem;">S/D</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- NIVEL 3: ACCIONES ESTÁNDAR -->
                    <?php if ($puede_editar): ?>
                        <a href="formularios/formulario_cultivo.php?id=<?= $cult['cultivo_id'] ?>" class="stretched-link" style="position: absolute;"></a>
                    <?php endif; ?>

                    <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                        <div style="display: flex; gap: 8px; align-items: center; min-height: 32px;">
                            <?php if ($puede_editar): ?>
                                <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_cultivo.php?id=".$cult['cultivo_id'], 'title' => "Editar parámetros"]) ?>
                            <?php endif; ?>
                            
                            <?php if ($es_admin_eff): ?>
                                <span style="opacity: 0.2;">|</span>
                                <?= sira_btn('', 'mini', !empty($cult['activa']) ? 'delete' : 'eye', [
                                    'href' => "dashboard.php?seccion=cultivos&accion=status_cultivo&estado=".(!empty($cult['activa']) ? 'desactivar' : 'activar')."&id=".$cult['cultivo_id'],
                                    'style' => "color: ".(!empty($cult['activa']) ? 'var(--color-warning)' : 'var(--color-primary)').";",
                                    'title' => (!empty($cult['activa']) ? 'Ocultar del catálogo' : 'Mostrar en el catálogo')
                                ]) ?>
                            <?php endif; ?>
                        </div>
                        
                        <div style="text-align: right;">
                             <span class="list-subtitle" style="font-size: 0.70rem; opacity: 0.5;">GUÍA TÉCNICA ➜</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($todos_los_cultivos)): ?>
        <div style="text-align: center; padding: 6rem 2rem; background: var(--color-bg-card); border-radius: var(--radius-container); border: 2px dashed rgba(255,255,255,0.05);">
            <div style="font-size: 4rem; margin-bottom: 2rem; opacity: 0.2;">🌾</div>
            <h3 style="color: var(--color-text-main);">Catálogo de Variedades Vacío</h3>
        </div>
    <?php endif; ?>

</div>