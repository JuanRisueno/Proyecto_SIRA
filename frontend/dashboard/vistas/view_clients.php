<?php
/**
 * view_clients.php - Gestión de Agricultores (Refactorización Estándar SIRA V12.0)
 * Sincronizado con el sistema de tarjetas de infraestructura premium.
 */

// Variables de rol y permisos ya definidas en logic.php
$puede_editar = ($es_admin || $user_rol === 'root');
?>

<?php 
// 1. Lógica de Filtrado Exclusivo
$ver_ocultos = $_SESSION['ver_ocultos'] ?? false;
$todos_los_clientes = array_filter($todos_los_clientes, function($c) use ($ver_ocultos) {
    $is_active = (bool)($c['activa'] ?? true);
    return $ver_ocultos ? !$is_active : $is_active;
});
?>

<?php if ($vista_grid_activa): ?>
    <div class="infra-grid-container">
        <?php foreach ($todos_los_clientes as $cli): 
            // Lógica de permisos de edición
            $puede_editar = ($user_rol === 'root') || ($es_admin && $cli['rol'] === 'cliente') || ($cli['cliente_id'] == $_SESSION['cliente_id']);
        ?>
            <div class="inv-smart-card <?= !$cli['activa'] ? 'sira-item-archived' : '' ?>">
                
                <!-- NIVEL 1: CABECERA ESTÁNDAR (Empresa + Badge Rol) -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <h3 title="<?= htmlspecialchars($cli['nombre_empresa']) ?>">
                                <?= htmlspecialchars($cli['nombre_empresa']) ?>
                            </h3>
                        </div>
                        <div class="card-subtitle">
                            <span>🏢 SIRA CLIENTE</span>
                            <span style="opacity: 0.3;">|</span>
                            <span>#<?= str_pad($cli['cliente_id'], 4, '0', STR_PAD_LEFT) ?></span>
                        </div>
                    </div>

                    <div class="badge-iot-live" style="display: flex; align-items: center; gap: 8px;">
                        <?php 
                            $last_act = $cli['ultima_actividad'] ?? null;
                            $is_online = !empty($cli['session_id']) && $last_act && (time() - strtotime($last_act) < 1800); // 30 min max
                            if ($is_online): 
                        ?>
                            <span class="status-indicator-online" title="En línea ahora"></span>
                        <?php endif; ?>
                        <span class="badge-text-premium"><?= strtoupper($cli['rol']) ?></span>
                    </div>
                </div>

                <!-- NIVEL 2: CORAZÓN TÉCNICO (Icono Identidad + Datos) -->
                <div class="card-nivel-tecnico">
                    <div class="tecnico-bloque-identidad">
                        <div class="tecnico-avatar-icon">👤</div>
                        <div class="tecnico-datos-group">
                            <span class="tecnico-label">Responsable</span>
                            <span class="tecnico-valor-main"><?= htmlspecialchars($cli['persona_contacto']) ?></span>
                        </div>
                    </div>

                    <div class="tecnico-datos-derecha">
                        <div class="tecnico-item-mini">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <span class="tecnico-label">CIF / NIF</span>
                                <span style="font-size: 0.9rem; font-weight: 800; color: var(--color-text-main); font-family: monospace;"><?= htmlspecialchars($cli['cif']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIONES ESTÁNDAR (Navegación + Gestión) -->
                <?php 
                    // El enlace principal de la tarjeta depende del rol
                    // Clientes -> Dashboard | Root/Admin -> Editar
                    $url_principal = ($cli['rol'] === 'cliente') 
                        ? "dashboard.php?cliente_id=" . $cli['cliente_id'] 
                        : "formularios/formulario_usuario.php?id=" . $cli['cliente_id'];
                ?>
                <a href="<?= $url_principal ?>" class="stretched-link"></a>
                
                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <?php if ($puede_editar): ?>
                            <!-- Icono de Gestión de Estado (Archivar / Restaurar) -->
                            <?php if ($cli['activa']): ?>
                                <?php if ($cli['rol'] !== 'root'): ?>
                                    <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_ocultar=1&id=".$cli['cliente_id']."#cli-card-".$cli['cliente_id'], 'style' => "color: var(--color-warning);", 'title' => "Archivar/Ocultar Agricultor"]) ?>
                                    <span style="opacity: 0.2; margin: 0 4px;">|</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= sira_btn('', 'mini', 'eye', ['href' => "dashboard.php?accion=activar&id=".$cli['cliente_id']."#cli-card-".$cli['cliente_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar/Mostrar Agricultor"]) ?>
                                <span style="opacity: 0.2; margin: 0 4px;">|</span>
                            <?php endif; ?>

                            <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_usuario.php?id=".$cli['cliente_id'], 'title' => "Editar perfil y datos"]) ?>

                            <?php if ($cli['rol'] === 'cliente'): ?>
                                <?= sira_btn('', 'mini', 'calendar', ['href' => "dashboard.php?seccion=jornadas_resumen&cliente_id=".$cli['cliente_id'], 'title' => "Ver Resumen de Jornadas"]) ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($cli['rol'] === 'cliente'): ?>
                        <div style="text-align: right;">
                            <span class="list-subtitle" style="font-size: 0.70rem; opacity: 0.5;">ENTORNO PRODUCTIVO ➜</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php else: ?>
    <!-- VISTA LISTA (Table) - Mantiene el estándar de tablas.css -->
    <div class="list-container sira-table-container">
        <table class="sira-table">
            <thead>
                <tr>
                    <th style="width: 50px;"></th>
                    <th>ID</th>
                    <th>Empresa / Agricultor</th>
                    <th>CIF</th>
                    <th>Contacto</th>
                    <th>Rol</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todos_los_clientes as $cli): 
                    $puede_editar = ($user_rol === 'root') || ($es_admin && $cli['rol'] === 'cliente') || ($cli['cliente_id'] == $_SESSION['cliente_id']);
                ?>
                    <?php 
                        $url_click = ($cli['rol'] === 'cliente') 
                            ? "dashboard.php?cliente_id=".$cli['cliente_id'] 
                            : "formularios/view_perfil.php?id=".$cli['cliente_id'];
                    ?>
                    <tr class="<?= !$cli['activa'] ? 'sira-item-archived' : '' ?> sira-table-row-clickable"
                        onclick="window.location='<?= $url_click ?>'">
                        <td style="text-align: center;" onclick="event.stopPropagation()">
                             <?php if ($puede_editar): ?>
                                <?php if ($cli['activa']): ?>
                                    <?php if ($cli['rol'] !== 'root'): ?>
                                        <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_ocultar=1&id=".$cli['cliente_id'], 'style' => "color: var(--color-warning);", 'title' => "Archivar"]) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= sira_btn('', 'mini', 'eye', ['href' => "dashboard.php?accion=activar&id=".$cli['cliente_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar"]) ?>
                                <?php endif; ?>
                             <?php endif; ?>
                        </td>
                        <td><span class="list-badge-tech badge-muted"><?= $cli['cliente_id'] ?></span></td>
                        <td>
                            <div class="list-cell-main">
                                <span class="list-main-icon">🏢</span>
                                <div class="list-main-stack">
                                    <strong class="list-title" title="<?= htmlspecialchars($cli['nombre_empresa']) ?>" style="line-height: 1.3; margin-bottom: 2px;">
                                        <?= htmlspecialchars($cli['nombre_empresa']) ?>
                                    </strong>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="list-subtitle" style="margin: 0;">Entorno Productivo</span>
                                        <?php 
                                            $last_act = $cli['ultima_actividad'] ?? null;
                                            $is_online = !empty($cli['session_id']) && $last_act && (time() - strtotime($last_act) < 1800); // 30 min max
                                            if ($is_online): 
                                        ?>
                                            <span style="opacity: 0.3; color: white; font-size: 0.6rem;">|</span>
                                            <span class="status-indicator-online mini" title="En línea" style="box-shadow: 0 0 5px var(--color-primary);"></span>
                                            <span style="color: var(--color-primary); font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em;">Online</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td><code><?= htmlspecialchars($cli['cif']) ?></code></td>
                        <td><span class="list-subtitle contact-name"><?= htmlspecialchars($cli['persona_contacto']) ?></span></td>
                        <td><span class="list-badge-tech"><?= strtoupper($cli['rol']) ?></span></td>
                        <td class="table-actions-cell" onclick="event.stopPropagation()">
                             <div class="table-actions-wrapper">
                                <?php if ($puede_editar): ?>
                                    <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_usuario.php?id=".$cli['cliente_id'], 'title' => "Editar Perfil"]) ?>
                                    <?php if ($cli['rol'] === 'cliente'): ?>
                                        <?= sira_btn('', 'mini', 'calendar', ['href' => "dashboard.php?seccion=jornadas_resumen&cliente_id=".$cli['cliente_id'], 'title' => "Resumen Jornadas"]) ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Mensaje No Resultados -->
<?php if (empty($todos_los_clientes)): ?>
<div class="card empty-state-premium" style="padding: 3.5rem 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: var(--radius-container); margin: 2rem 0;">
    
    <div class="empty-visual-wrapper" style="margin-bottom: 1.5rem; position: relative;">
        <span style="font-size: 3.8rem; display: block; filter: drop-shadow(0 8px 15px rgba(0,0,0,0.4));"><?= $ver_ocultos ? '📂' : '👥' ?></span>
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 70px; height: 70px; background: var(--color-primary); filter: blur(35px); opacity: 0.15; z-index: -1;"></div>
    </div>

    <div class="empty-text-stack" style="margin-bottom: 2rem; max-width: 650px;">
        <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--color-text-main); margin-bottom: 0.5rem; letter-spacing: -0.02em;">
            <?= $ver_ocultos ? 'No hay agricultores archivados' : ($busqueda ? 'Sin coincidencias de búsqueda' : 'Registro de clientes vacío') ?>
        </h2>
        
        <p style="opacity: 0.6; line-height: 1.6; font-size: 1rem; font-weight: 500;">
            <?= $ver_ocultos 
                ? 'Actualmente todos los registros de clientes y administradores se encuentran activos en el sistema principal.' 
                : ($busqueda 
                    ? 'No se han encontrado registros que coincidan con "<span style="color: var(--color-primary);">' . htmlspecialchars($busqueda) . '</span>". Pruebe con otros términos o revise la ortografía.' 
                    : 'Aún no se han registrado agricultores en la base de datos global de SIRA.') ?>
        </p>
    </div>

    <div class="empty-actions-row" style="display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center;">
        <?= sira_btn('Ver todos los activos', 'primary', 'eye', ['href' => "dashboard.php?reset_ocultos=1"]) ?>
        
        <?php if (!$ver_ocultos && ($es_admin || $user_rol === 'root')): ?>
             <?= sira_btn('Consultar Histórico', 'secondary', 'list', ['href' => "dashboard.php?toggle_ocultos=1"]) ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
