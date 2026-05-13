<?php
/**
 * view_all_invernaderos.php - Listado maestro de invernaderos (SIRA Standard V12.0)
 * Refactorizado para usar el sistema nativo de tarjetas premium.
 */
?>

<div class="infra-grid-container">
    
    <?php if (empty($todos_los_invernaderos)): 
        $nombre_sujeto = ($es_admin && (isset($arbol['nombre_empresa']) || isset($arbol['nombre_completo']))) ? ($arbol['nombre_empresa'] ?? $arbol['nombre_completo']) : 'tu cuenta';
    ?>
        <div class="user-form-container card" style="text-align: center; padding: 4rem; grid-column: 1 / -1;">
            <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">🏡</div>
            <p><?= $_SESSION['ver_ocultos'] ? "No hay invernaderos ocultos registrados a $nombre_sujeto." : "No hay invernaderos registrados aún a $nombre_sujeto." ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($todos_los_invernaderos as $inv): 
            $is_target = (isset($_GET['plant_inv_id']) && $_GET['plant_inv_id'] == $inv['invernadero_id']) || (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $inv['invernadero_id']);
            $puede_editar_inv = ($es_admin || $user_rol === 'cliente');
            
            $is_inv_archived = !($inv['activa'] ?? true);
            $parc_archived = !($inv['parcela']['activa'] ?? true);
            $show_as_archived = ($is_inv_archived || $parc_archived);
        ?>
            <div id="inv-card-<?= $inv['invernadero_id'] ?>" 
                 class="inv-smart-card <?= $is_target ? 'highlight-glow' : '' ?> <?= $show_as_archived ? 'sira-item-archived' : '' ?>">
                
                <!-- NIVEL 1: CABECERA DE CONTROL -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <h3 title="<?= htmlspecialchars($inv['nombre']) ?>">
                            <?= mb_convert_case($inv['nombre'], MB_CASE_TITLE, "UTF-8") ?>
                        </h3>
                        <div class="card-subtitle">
                            <span>#<?= $inv['invernadero_id'] ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span>📍 <?= mb_convert_case($inv['parcela']['localidad']['municipio'] ?? 'S/L', MB_CASE_TITLE, "UTF-8") ?></span>
                        </div>
                    </div>

                    <?php if ($parc_archived): ?>
                        <div style="background: rgba(100, 116, 139, 0.1); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(100, 116, 139, 0.2);" title="La parcela que contiene este invernadero está archivada">
                            <span style="font-size: 0.65rem; font-weight: 900; color: #64748b; letter-spacing: 0.1em;">🚜 PARCELA ARCHIVADA</span>
                        </div>
                    <?php elseif ($is_inv_archived): ?>
                        <div style="background: rgba(100, 116, 139, 0.1); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(100, 116, 139, 0.2);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: #64748b; letter-spacing: 0.1em;">ARCHIVADO</span>
                        </div>
                    <?php else: ?>
                        <div class="status-live-container" title="Sincronización en tiempo real activa">
                            <span class="status-pulse-dot"></span>
                            <span class="badge-text-premium">LIVE</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NIVEL 2: CORAZÓN TÉCNICO -->
                <div class="card-nivel-tecnico">
                    <!-- BLOQUE IZQUIERDO: Identidad del Cultivo -->
                    <div class="tecnico-bloque-identidad">
                        <div class="tecnico-avatar-icon">
                            <?= get_crop_icon($inv['cultivo']['nombre_cultivo'] ?? null) ?: '🏡' ?>
                        </div>
                        <div class="tecnico-datos-group">
                            <span class="tecnico-label">Producción Actual</span>
                            <strong class="tecnico-valor-main"><?= $inv['cultivo'] ? mb_convert_case($inv['cultivo']['nombre_cultivo'], MB_CASE_TITLE, "UTF-8") : 'En Barbecho' ?></strong>
                        </div>
                    </div>

                    <!-- BLOQUE DERECHO: Especificaciones -->
                    <div class="tecnico-datos-derecha">
                        <div class="tecnico-item-mini">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <span class="tecnico-label" style="opacity: 0.7;">Finca Asociada</span>
                                <span style="font-size: 0.85rem; font-weight: 800; color: var(--color-text-main); white-space: nowrap;">
                                    <?= mb_convert_case($inv['parcela']['nombre'] ?: 'Finca #' . $inv['parcela_id'], MB_CASE_TITLE, "UTF-8") ?>
                                </span>
                                <small style="font-size: 0.65rem; opacity: 0.4; font-weight: bold;">ID #<?= $inv['parcela_id'] ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIONES ESTÁNDAR SIRA -->
                <a href="sensores.php?id=<?= $inv['invernadero_id'] ?>&nombre=<?= urlencode($inv['nombre']) ?><?= $url_query_cliente ?>" 
                   class="stretched-link"></a>

                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <?php if ($show_as_archived): ?>
                            <?php if ($es_admin): ?>
                                <?php 
                                    $restore_url = "dashboard.php?accion=restaurar_asset&target=invernadero&id=" . $inv['invernadero_id'] . "&seccion=mis_invernaderos" . $url_query_cliente;
                                    if ($parc_archived) {
                                        $restore_url = "dashboard.php?confirmar_restaurar_inv_jerarquico=1&id=" . $inv['invernadero_id'] . "&seccion=mis_invernaderos" . $url_query_cliente;
                                    }
                                ?>
                                <a href="<?= $restore_url ?>#inv-card-<?= $inv['invernadero_id'] ?>" 
                                   class="mini-btn-opt" style="color: var(--color-primary);" title="Restaurar Invernadero">
                                    👁️
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($es_admin): ?>
                                <a href="dashboard.php?confirmar_borrar_inv=1&id=<?= $inv['invernadero_id'] ?>&seccion=mis_invernaderos<?= $url_query_cliente ?>" 
                                   class="mini-btn-opt" style="color: var(--color-warning);" title="Archivar Invernadero">
                                    🗑️
                                </a>
                                <span style="opacity: 0.2;">|</span>
                            <?php endif; ?>

                            <?php if ($puede_editar_inv): ?>
                                <a href="formularios/formulario_invernadero.php?id=<?= $inv['invernadero_id'] ?>&from=mis_invernaderos" class="mini-btn-opt" title="Editar invernadero">
                                    ⚙️
                                </a>
                                
                                <?php 
                                    $jinfo = $jornadas_map[$inv['invernadero_id']] ?? null;
                                    $is_conf = $jinfo['configurado'] ?? false;
                                    $is_lab = $jinfo['es_laborable'] ?? true;
                                    
                                    $j_icon = "⚠️"; $j_title = "Configuración de jornada pendiente"; $j_color = "var(--color-error)";
                                    if ($is_conf) {
                                        if (!$is_lab) { $j_icon = "📦"; $j_title = "Modo Almacén (Sin jornada)"; $j_color = "#64748b"; }
                                        else { $j_icon = "🕒"; $j_title = "Jornada Laboral Configurada"; $j_color = "var(--color-primary)"; }
                                    }
                                ?>
                                <a href="formularios/formulario_jornada.php?inv_id=<?= $inv['invernadero_id'] ?><?= $url_query_cliente ?>" class="mini-btn-opt" title="<?= $j_title ?>" style="color: <?= $j_color ?>;">
                                    <?= $j_icon ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php 
                                $query_params = "dashboard.php?plant_inv_id=" . $inv['invernadero_id'] . $url_query_cliente;
                                if (isset($_GET['seccion'])) $query_params .= "&seccion=" . $_GET['seccion'];
                            ?>
                            <a href="<?= $query_params ?>#inv-card-<?= $inv['invernadero_id'] ?>" 
                               class="mini-btn-opt" title="Plantar o cambiar cultivo">
                                🌱
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: right;">
                        <span class="list-subtitle" style="font-size: 0.7rem; opacity: 0.5;">PANEL DE SENSORES ➜</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
