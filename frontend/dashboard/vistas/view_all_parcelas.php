<?php
/**
 * view_all_parcelas.php - Listado maestro de parcelas (SIRA Standard V12.0)
 * Refactorizado para usar el sistema nativo de tarjetas premium.
 */
?>

<div class="infra-grid-container">
    
    <?php if (empty($todas_las_parcelas)): 
        $nombre_sujeto = ($es_admin && (isset($arbol['nombre_empresa']) || isset($arbol['nombre_completo']))) ? ($arbol['nombre_empresa'] ?? $arbol['nombre_completo']) : 'tu cuenta';
    ?>
        <div class="user-form-container card" style="text-align: center; padding: 4rem; grid-column: 1 / -1;">
            <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">🚜</div>
            <p><?= $_SESSION['ver_ocultos'] ? "No hay parcelas ocultas registradas a $nombre_sujeto." : "No hay parcelas registradas aún a $nombre_sujeto." ?></p>
        </div>
    <?php else: ?>
        <?php foreach ($todas_las_parcelas as $parc): 
            $is_target = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $parc['parcela_id']);
            $is_archived = !($parc['activa'] ?? true);
            $puede_editar_parc = ($es_admin || $user_rol === 'cliente');
        ?>
            <div id="parcela-card-<?= $parc['parcela_id'] ?>" 
                 class="inv-smart-card <?= $is_target ? 'highlight-glow' : '' ?> <?= $is_archived ? 'sira-item-archived' : '' ?>">
                
                <!-- NIVEL 1: CABECERA DE IDENTIDAD (Nombre + ID) -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <h3 title="<?= htmlspecialchars($parc['nombre'] ?: 'Finca #' . $parc['parcela_id']) ?>">
                            <?= mb_convert_case($parc['nombre'] ?: 'Finca #' . $parc['parcela_id'], MB_CASE_TITLE, "UTF-8") ?>
                        </h3>
                        <div class="card-subtitle">
                            <span>#<?= $parc['parcela_id'] ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span>📍 <?= mb_convert_case($parc['localidad']['municipio'] ?? 'S/L', MB_CASE_TITLE, "UTF-8") ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span>CP <?= $parc['localidad']['codigo_postal'] ?></span>
                        </div>
                    </div>

                    <?php if ($is_archived): ?>
                        <div style="background: rgba(100, 116, 139, 0.1); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(100, 116, 139, 0.2);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: #64748b; letter-spacing: 0.1em;">ARCHIVADO</span>
                        </div>
                    <?php else: ?>
                        <div style="background: rgba(16, 185, 129, 0.05); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(16, 185, 129, 0.15);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: var(--color-primary); letter-spacing: 0.1em;">FINCA ACTIVA</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NIVEL 2: CORAZÓN TÉCNICO -->
                <div class="card-nivel-tecnico">
                    <!-- BLOQUE IZQUIERDO: Infraestructura -->
                    <div class="tecnico-bloque-identidad">
                        <div class="tecnico-avatar-icon">🚜</div>
                        <div class="tecnico-datos-group">
                            <span class="tecnico-label">Operativa</span>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php 
                                    $num_inv_activos = count(array_filter($parc['invernaderos'] ?? [], fn($i) => ($i['activa'] ?? true)));
                                ?>
                                <strong class="tecnico-valor-main"><?= $num_inv_activos ?></strong>
                                <span style="font-size: 0.65rem; background: rgba(16, 185, 129, 0.15); color: var(--color-primary); padding: 2px 8px; border-radius: 6px; font-weight: 800;">INV.</span>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE DERECHO: Datos Catastrales -->
                    <div class="tecnico-datos-derecha">
                        <div class="tecnico-item-mini">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <span class="tecnico-label" style="opacity: 0.7;">Ref. Catastral</span>
                                <span style="font-size: 0.9rem; font-weight: 800; color: var(--color-text-main); font-family: 'Roboto Mono', monospace; letter-spacing: -0.01em;"><?= htmlspecialchars($parc['ref_catastral'] ?: 'S/REF') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIONES ESTÁNDAR SIRA -->
                <a href="dashboard.php?localidad_cp=<?= urlencode($parc['localidad']['codigo_postal'] ?? '') ?>&parcela_id=<?= $parc['parcela_id'] ?><?= $url_query_cliente ?>" 
                   class="stretched-link"></a>

                <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <?php if ($is_archived): ?>
                             <?php if ($es_admin): ?>
                                <a href="dashboard.php?accion=restaurar_parcela_total&id=<?= $parc['parcela_id'] ?>&seccion=mis_parcelas<?= $url_query_cliente ?>#parcela-card-<?= $parc['parcela_id'] ?>" 
                                   class="mini-btn-opt" style="color: var(--color-primary);" title="Restaurar Parcela (con todos sus invernaderos)">
                                    👁️
                                </a>
                             <?php endif; ?>
                        <?php else: ?>
                            <?php if ($es_admin): ?>
                                <a href="dashboard.php?confirmar_borrar_parc=1&id=<?= $parc['parcela_id'] ?>&seccion=mis_parcelas<?= $url_query_cliente ?>" 
                                   class="mini-btn-opt" style="color: var(--color-warning);" title="Archivar Parcela">
                                    🗑️
                                </a>
                                <span style="opacity: 0.2;">|</span>
                            <?php endif; ?>

                            <?php if ($puede_editar_parc): ?>
                                <a href="formularios/formulario_parcela.php?id=<?= $parc['parcela_id'] ?>&from=lista" class="mini-btn-opt" title="Editar parcela">
                                    ⚙️
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div style="text-align: right;">
                        <span class="list-subtitle" style="font-size: 0.7rem; opacity: 0.5;">GESTIONAR INVERNADEROS ➜</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
