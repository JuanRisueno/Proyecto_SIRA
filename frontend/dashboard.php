<?php
/**
 * ===============================================================================================
 *                              SIRA - DASHBOARD ORQUESTADOR
 * ===============================================================================================
 * Este archivo centraliza la visualización de la infraestructura y gestión de usuarios.
 * Ha sido MODULARIZADO para facilitar el mantenimiento preventivo y correctivo (ASIR Architecture).
 * Piezas incluidas:
 * - dashboard/logic.php             -> API, Funciones, Sesión y Acciones.
 * - dashboard/header.php            -> Migas de pan, Título y Botones.
 * - dashboard/search_bar.php        -> Buscador PHP + SQL.
 * - dashboard/view_clients.php      -> Grid/Lista de agricultores.
 * - dashboard/view_infrastructure.php-> Localidades, Parcelas e Invernaderos.
 * - dashboard/view_infrastructure.php-> Localidades, Parcelas e Invernaderos.
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/icons_helper.php';

// 1. Cargar Lógica, API y Preparación de Datos
require_once 'dashboard/logic.php';

// 2. Cargar Cabecera HTML Estándar (Meta, CSS, JWT)
$page_title = "SIRA - Panel de Control";
$page_css = "dashboard";
require_once 'includes/header.php';

// ── Error Crítico: Si la API no responde ──
if ($arbol === null): ?>
    <div class='container'>
        <div class='error-panel'>
            <h2>⚠️ Servicio Temporalmente Caído</h2>
            <p>No se pudo conectar con los servidores de SIRA. Por favor, inténtalo de nuevo en unos minutos.</p>
        </div>
    </div>
    <?php require_once 'includes/footer.php'; exit(); ?>
<?php endif; ?>

<!-- 3. Sistema de Alertas (UX feedback) -->
<?php 
    $mensajes_map = [
        'borrado_ok' => '✅ Localidad eliminada del catálogo.',
        'parcela_archivada' => '🗑️ Parcela archivada correctamente.',
        'invernadero_archivado' => '🗑️ Invernadero archivado correctamente.',
        'asset_restaurado' => '✅ Elemento restaurado con éxito.',
        'msg_ok' => '✅ Operación realizada con éxito.',
        'nombre_actualizado' => '📝 Nombre actualizado correctamente.',
        'siembra_actualizada' => '🌱 Variedad sembrada con éxito.',
        'reset_jornada_ok' => '🧹 Configuración de jornadas reseteada correctamente.',
        'social_actualizado' => '🌐 Redes sociales actualizadas con éxito.'
    ];

    $errores_map = [
        'solo_root_puede_editar_admins' => '⚠️ Solo el usuario Root tiene permisos para gestionar cuentas de administración.',
        'usuario_no_encontrado' => '⚠️ El usuario solicitado no existe o no ha podido ser cargado.'
    ];
?>
<?php if ((isset($_GET['msg']) && isset($mensajes_map[$_GET['msg']])) || isset($_GET['error'])): ?>
<div class="container" style="margin-top: 1rem; margin-bottom: -1.5rem;">
    <?php if (isset($_GET['msg']) && isset($mensajes_map[$_GET['msg']])): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 12px; padding: 1rem 1.5rem; background: rgba(52, 211, 153, 0.1); border: 1px solid var(--color-primary); border-radius: 10px; color: var(--color-primary); font-weight: 600; font-size: 0.9rem;">
            <span><?= $mensajes_map[$_GET['msg']] ?></span>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): 
        $err_key = $_GET['error'];
        $err_msg = $errores_map[$err_key] ?? "⚠️ Error: " . htmlspecialchars($err_key);
    ?>
        <div class="alert alert-error" style="display: flex; align-items: center; gap: 12px; padding: 1rem 1.5rem; background: rgba(248, 113, 113, 0.1); border: 1px solid #f87171; border-radius: 10px; color: #f87171; font-weight: 600; font-size: 0.9rem;">
            <span><?= $err_msg ?></span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 4. Modal de Confirmación de Agricultores (Archivar) -->
<?php if ($cliente_a_confirmar): ?>
    <div class="confirm-overlay">
        <div class="confirm-card highlight-glow confirm-card-warning">
            <div class="confirm-header">
                <span class="confirm-icon" style="font-size: 3rem; margin-bottom: 1rem;">⚠️</span>
                <h2 class="confirm-title">Archivar Agricultor</h2>
            </div>
            <div class="confirm-body">
                <p>Estás a punto de ocultar del panel principal al agricultor:<br><strong><?= htmlspecialchars($cliente_a_confirmar['nombre_empresa']) ?></strong></p>
                <p style="font-size: 0.85rem; opacity: 0.7;">Esta acción no borrará sus datos, pero impedirá el acceso operativo hasta que sea restaurado.</p>
            </div>
            <div class="confirm-actions" style="margin-top: 2rem;">
                <a href="dashboard.php?accion=ocultar&id=<?= $cliente_a_confirmar['cliente_id'] ?>" class="btn-archive">Confirmar Archivado</a>
                <a href="dashboard.php" class="confirm-btn-no">Cancelar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Modal de Localidades (Consulta / Detalle) -->
<?php if ($loc_detalle_target): ?>
    <div class="confirm-overlay">
        <div class="confirm-card">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🗺️</div>
            <h2 style="color: var(--color-primary);">Consulta de Localidad</h2>
            
            <div class="confirm-msg-box" style="text-align: left;">
                <p style="margin-bottom: 1rem;">
                    <strong><?= htmlspecialchars($loc_detalle_target['municipio']) ?> (<?= htmlspecialchars($loc_detalle_target['codigo_postal']) ?>)</strong>
                    tiene los siguientes activos registrados:
                </p>
                
                <strong style="font-size: 0.75rem; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.05em;">Parcelas detectadas (<?= count($parcelas_bloqueantes) ?>):</strong>
                
                <div style="max-height: 220px; overflow-y: auto; background: rgba(0,0,0,0.2); border-radius: var(--radius-container); padding: 0.8rem; border: 1px solid rgba(255,255,255,0.1); margin-top: 0.5rem;">
                    <?php if (!empty($parcelas_bloqueantes)): ?>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <?php foreach ($parcelas_bloqueantes as $p): 
                                $is_archived = !($p['activa'] ?? true);
                            ?>
                                <li style="position: relative; padding: 0.70rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 12px; transition: all 0.2s;" 
                                    class="<?= $is_archived ? 'sira-item-archived' : '' ?>"
                                    onmouseover="this.style.background='rgba(52, 211, 153, 0.08)';" 
                                    onmouseout="this.style.background='transparent';">
                                    
                                    <!-- Enlace Maestro (Stretched) - Solo si está activa -->
                                    <?php if (!$is_archived): ?>
                                        <a href="dashboard.php?cliente_id=<?= $p['cliente_id'] ?>&localidad_cp=<?= urlencode($p['codigo_postal']) ?>&highlight_id=<?= $p['parcela_id'] ?>#parc-card-<?= $p['parcela_id'] ?>" class="stretched-link"></a>
                                    <?php endif; ?>
                                    
                                    <div style="font-size: 1.2rem; opacity: 0.8;">🚜</div>
                                    <div style="display: flex; flex-direction: column; flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 2px;">
                                            <strong style="color: white; font-size: 0.9rem;"><?= htmlspecialchars($p['nombre'] ?: 'Finca #' . $p['parcela_id']) ?></strong>
                                            <?php if ($is_archived): ?>
                                                <span style="font-size: 0.55rem; background: rgba(100, 116, 139, 0.2); color: #94a3b8; padding: 1px 6px; border-radius: 4px; font-weight: 800; border: 1px solid rgba(100, 116, 139, 0.3); text-transform: uppercase;">Archivado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-size: 0.6rem; background: rgba(52, 211, 153, 0.1); color: var(--color-primary); padding: 1px 6px; border-radius: 4px; font-weight: 800; opacity: 0.7;">
                                                CLI: <?= htmlspecialchars($p['cliente_id']) ?>
                                            </span>
                                            <code style="font-size: 0.75rem; opacity: 0.5; color: white; font-family: 'Roboto Mono', monospace;"><?= htmlspecialchars($p['ref_catastral']) ?></code>
                                        </div>
                                    </div>

                                    <!-- Acciones de Gestión (Solo Admin/Root) -->
                                    <?php if ($es_admin): ?>
                                        <div style="position: relative; z-index: 10; display: flex; gap: 8px;">
                                            <?php if ($is_archived): ?>
                                                <a href="dashboard.php?accion=restaurar_asset&target=parcela&id=<?= $p['parcela_id'] ?>&ver_detalle_loc=1&cp=<?= urlencode($cp_target) ?><?= $url_query_cliente ?>" 
                                                   class="mini-btn-opt" style="color: var(--color-primary); font-size: 1.1rem; text-decoration: none;" title="Restaurar Parcela">👁️</a>
                                            <?php else: ?>
                                                <a href="dashboard.php?confirmar_borrar_parc=1&id=<?= $p['parcela_id'] ?>&ver_detalle_loc=1&cp=<?= urlencode($cp_target) ?><?= $url_query_cliente ?>" 
                                                   class="mini-btn-opt" style="color: var(--color-warning); font-size: 1.1rem; text-decoration: none;" title="Archivar Parcela">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$is_archived): ?>
                                        <div style="opacity: 0.3; font-size: 0.8rem;">➜</div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div style="text-align: center; padding: 1.5rem; opacity: 0.6;">
                            <p>No hay parcelas registradas para esta localidad.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="confirm-actions" style="margin-top: 1.5rem;">
                <a href="dashboard.php?seccion=localidades" class="btn-sira btn-primary" style="width: 100%; text-decoration: none !important;">Cerrar Consulta</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 5. Modal de Confirmación de Parcelas (SIRA Style) -->
<?php if ($parc_a_borrar_target): ?>
    <div class="confirm-overlay">
        <div class="confirm-card highlight-glow confirm-card-warning">
            <div class="confirm-header">
                <span class="confirm-icon">🗑️</span>
                <h2 class="confirm-title">Archivar Parcela</h2>
            </div>
            <div class="confirm-body">
                <p style="margin-bottom: 0.8rem;">Estás a punto de <strong>ocultar y archivar</strong> la parcela:</p>
                <div class="asset-preview-box">
                    <strong><?= htmlspecialchars($parc_a_borrar_target['nombre'] ?: 'Finca sin nombre') ?></strong><br>
                    <small style="opacity: 0.7;"><?= htmlspecialchars($parc_a_borrar_target['direccion']) ?></small>
                </div>
                <p style="margin-top: 1rem; font-size: 0.85rem; line-height: 1.4; opacity: 0.8;">
                    Esta acción <strong>no borrará los datos</strong>. La parcela y sus invernaderos se ocultarán del panel principal pero el histórico de sensores y cultivos se conservará para futuras consultas.
                </p>


            </div>
            <div class="confirm-actions" style="margin-top: 2.5rem;">
                <a href="dashboard.php?accion=borrar_parc&id=<?= $parc_a_borrar_target['parcela_id'] ?>&localidad_cp=<?= urlencode($parc_a_borrar_target['codigo_postal']) ?><?= $url_query_cliente ?>" 
                   id="btn_confirm_borrar_parc"
                   class="btn-archive" style="white-space: nowrap;">Confirmar Archivado</a>
                <a href="dashboard.php?localidad_cp=<?= urlencode($parc_a_borrar_target['codigo_postal']) ?><?= $url_query_cliente ?>" class="confirm-btn-no">Cancelar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (isset($_GET['confirmar_borrar_inv']) && $inv_a_borrar_target): ?>
    <div class="confirm-overlay">
        <div class="confirm-card highlight-glow confirm-card-warning">
            <div class="confirm-header">
                <span class="confirm-icon">🗑️</span>
                <h2 class="confirm-title">Archivar Invernadero</h2>
            </div>
            <div class="confirm-body">
                <p style="margin-bottom: 0.8rem;">Vas a ocultar el invernadero:</p>
                <div class="asset-preview-box">
                    <strong><?= htmlspecialchars($inv_a_borrar_target['nombre'] ?? ('Estructura #' . $inv_a_borrar_target['invernadero_id'])) ?></strong>
                </div>
                <p style="margin-top: 1rem; font-size: 0.85rem; opacity: 0.8;">
                    Los datos de sensores y el historial de cultivos se conservarán, pero no aparecerá en el listado activo.
                </p>
            </div>
            <div class="confirm-actions" style="margin-top: 2rem;">
                <a href="dashboard.php?accion=borrar_inv&id=<?= $inv_a_borrar_target['invernadero_id'] ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?>&localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>" 
                   class="btn-archive" style="white-space: nowrap;">Confirmar Archivado</a>
                <a href="dashboard.php?parcela_id=<?= $parc_seleccionada['parcela_id'] ?>&localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>" class="confirm-btn-no">Cancelar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

    <!-- MODO RESTAURAR (Asset Restore) -->
    <?php if (isset($_GET['accion']) && $_GET['accion'] === 'restaurar_asset' && isset($_GET['id'])): ?>
        <?php
            $is_parc = (isset($_GET['target']) && $_GET['target'] === 'parcela');
            $id = (int)$_GET['id'];
            $asset = obtenerDetalleAsset($token, $is_parc, $id);
            if ($asset):
                $asset['activa'] = true;
                actualizarAsset($token, $is_parc, $id, $asset);
                
                // Construcción de redirección con contexto y ancla
                $redir = "dashboard.php?msg=asset_restaurado" . $url_query_cliente;
                if (isset($_GET['localidad_cp'])) $redir .= "&localidad_cp=" . urlencode($_GET['localidad_cp']);
                if (isset($_GET['parcela_id'])) $redir .= "&parcela_id=" . (int)$_GET['parcela_id'];
                
                $anchor = $is_parc ? "#parc-card-$id" : "#inv-card-$id";
                header("Location: " . $redir . "&highlight_id=$id" . $anchor);
                exit();
            endif;
        ?>
    <?php endif; ?>

<!-- 3.5 Modal de Siembra Rápida (Plantar Cultivo) -->
<?php if ($inv_a_plantar): ?>
    <div class="confirm-overlay">
        <div class="confirm-card" style="max-width: 600px; border-color: var(--color-primary);">
            <div style="font-size: 3.5rem; margin-bottom: 1rem;">🌱</div>
            <h2 style="margin-bottom: 0.5rem;">Siembra Rápida</h2>
            <p style="margin-bottom: 1.5rem;">Selecciona el cultivo para el invernadero:<br><strong><?= htmlspecialchars($inv_a_plantar['nombre']) ?></strong></p>
            
            <?php
                // Reconstruir la URL de cancelación (eliminando plant_inv_id)
                $params_cancel = $_GET;
                unset($params_cancel['plant_inv_id']);
                $url_cancel = "dashboard.php?" . http_build_query($params_cancel);
            ?>

            <form action="dashboard.php?<?= http_build_query($_GET) ?>" method="POST">
                <input type="hidden" name="invernadero_id" value="<?= $inv_a_plantar['invernadero_id'] ?>">
                
                <div class="siembra-grid">
                    
                    <!-- Opción Barbecho -->
                    <label class="siembra-option">
                        <input type="radio" name="cultivo_id" value="0" <?= !$inv_a_plantar['cultivo_id'] ? 'checked' : '' ?>>
                        <div class="siembra-opt-card">
                            <span class="siembra-icon">🚜</span>
                            <span class="siembra-name">Barbecho</span>
                        </div>
                    </label>

                    <?php foreach ($lista_cultivos_siembra as $c): ?>
                        <label class="siembra-option">
                            <input type="radio" name="cultivo_id" value="<?= $c['cultivo_id'] ?>" <?= ($inv_a_plantar['cultivo_id'] == $c['cultivo_id']) ? 'checked' : '' ?>>
                            <div class="siembra-opt-card">
                                <span class="siembra-icon"><?= get_crop_icon($c['nombre_cultivo']) ?></span>
                                <span class="siembra-name"><?= mb_convert_case($c['nombre_cultivo'], MB_CASE_TITLE, "UTF-8") ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="form-footer-actions">
                    <button type="submit" name="btn_quick_plant" class="btn-sira btn-primary">Confirmar Siembra</button>
                    <button type="submit" name="btn_set_almacen" class="btn-sira btn-warning" style="background: #334155; border-color: rgba(255,255,255,0.1); color: #cbd5e1;">📦 Almacén</button>
                    <a href="<?= $url_cancel ?>" class="btn-sira btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Modal de Restauración Jerárquica (Invernadero dentro de Parcela Archivada) -->
<?php if ($inv_a_restaurar_jerarquico): ?>
    <div class="confirm-overlay">
        <div class="confirm-card highlight-glow confirm-card-info">
            <div class="confirm-header">
                <span class="confirm-icon" style="font-size: 3rem; margin-bottom: 1rem;">🌳</span>
                <h2 class="confirm-title">Restauración Jerárquica</h2>
            </div>
            <div class="confirm-body">
                <p>Estás restaurando el invernadero: <strong><?= htmlspecialchars($inv_a_restaurar_jerarquico['nombre']) ?></strong></p>
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid var(--color-primary); padding: 1rem; border-radius: 10px; margin: 1rem 0;">
                    <p style="font-size: 0.9rem; margin: 0;">⚠️ <strong>Nota:</strong> Este invernadero pertenece a una parcela que está archivada (<strong><?= htmlspecialchars($inv_a_restaurar_jerarquico['parcela']['nombre'] ?: 'Finca #'.$inv_a_restaurar_jerarquico['parcela_id']) ?></strong>).</p>
                    <p style="font-size: 0.85rem; opacity: 0.8; margin-top: 0.5rem;">Al activar el invernadero, se restaurará automáticamente la parcela para mantener la integridad de la infraestructura.</p>
                </div>
            </div>
            <div class="confirm-actions" style="margin-top: 1.5rem;">
                <a href="dashboard.php?accion=restaurar_jerarquico&inv_id=<?= $inv_a_restaurar_jerarquico['invernadero_id'] ?>&parc_id=<?= $inv_a_restaurar_jerarquico['parcela_id'] ?>&cliente_id=<?= $cliente_id_seleccionado ?>&seccion=mis_invernaderos" 
                   class="btn-sira btn-primary" style="padding: 0.8rem 2rem;">Confirmar y Restaurar Todo</a>
                <a href="dashboard.php?seccion=mis_invernaderos&cliente_id=<?= $cliente_id_seleccionado ?>" class="confirm-btn-no">Cancelar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 4. Contenedor Principal de la Interfaz -->
<div class="container" style="margin-top: 1rem;">
    <?php 
        // Renderizar Cabecera de Contenido (Breadcrumbs + Título + Botones)
        require_once 'dashboard/componentes/header.php';
        
        // Renderizar Buscador (Sólo si estamos en vista de agricultores)
        require_once 'dashboard/componentes/search_bar.php';
        
        // Renderizar el contenido según la vista activa
        if ($vista_actual === 'selector_cliente') {
            require_once 'dashboard/vistas/view_clients.php';
        } elseif ($vista_actual === 'gestion_localidades') {
            require_once 'dashboard/vistas/view_localidades.php';
        } elseif ($vista_actual === 'gestion_cultivos') {
            require_once 'dashboard/vistas/view_cultivos.php';
        } elseif ($vista_actual === 'gestion_parcelas_total') {
            require_once 'dashboard/vistas/view_all_parcelas.php';
        } elseif ($vista_actual === 'gestion_invernaderos_total') {
            require_once 'dashboard/vistas/view_all_invernaderos.php';
        } elseif ($vista_actual === 'jornadas_resumen') {
            require_once 'dashboard/vistas/view_jornadas_resumen.php';
        } else {
            require_once 'dashboard/vistas/view_infrastructure.php';
        }
    ?>
</div>

<!-- 5. Pie de página -->
<?php if ($confirmar_reset_jornada_active): ?>
    <div class="confirm-overlay">
        <div class="confirm-card highlight-glow confirm-card-warning">
            <div class="confirm-header">
                <span class="confirm-icon">🗑️</span>
                <h2 class="confirm-title">¿ESTÁS SEGURO?</h2>
            </div>
            <div class="confirm-body">
                <p>Esta acción <strong>borrará la política maestra</strong> y todas las configuraciones individuales de las naves de este cliente.</p>
                <div class="asset-preview-box" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2);">
                    <small style="color: #f87171; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">⚠️ Acción Irreversible</small>
                </div>
                <p style="margin-top: 1rem; font-size: 0.85rem; opacity: 0.8;">
                    Los horarios volverán a su estado predeterminado de 24h laborables hasta que se definan nuevas políticas.
                </p>
            </div>
            <div class="confirm-actions" style="margin-top: 2rem;">
                <a href="dashboard.php?accion=reset_jornada_maestra&cliente_id=<?= $cliente_id_seleccionado ?>" 
                   class="btn-archive" style="background: var(--color-error); border-color: var(--color-error);">Confirmar Reset Maestro</a>
                <a href="dashboard.php?seccion=jornadas_resumen&cliente_id=<?= $cliente_id_seleccionado ?>" class="confirm-btn-no">Cancelar</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>