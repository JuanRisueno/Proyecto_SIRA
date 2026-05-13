<?php
$modo_lista = !$vista_grid_activa;
$ver_ocultos = $_SESSION['ver_ocultos'] ?? false;

// Lógica de Filtrado Exclusivo para Parcelas e Invernaderos
if (isset($parcelas_data)) {
    $parcelas_data = array_filter($parcelas_data, function($p) use ($ver_ocultos) {
        $is_active = (bool)($p['activa'] ?? true);
        return $ver_ocultos ? !$is_active : $is_active;
    });
}

if (isset($invernaderos_data)) {
    $invernaderos_data = array_filter($invernaderos_data, function($i) use ($ver_ocultos) {
        $is_active = (bool)($i['activa'] ?? true);
        return $ver_ocultos ? !$is_active : $is_active;
    });
}
?>

<div class="<?= $modo_lista ? 'infra-list-container' : 'infra-grid-container' ?>">
    <?php if ($vista_actual === 'localidades'): ?>
        <?php if (empty($localidades_data)): ?>
            <div class="card empty-state-premium" style="grid-column: 1 / -1; padding: 2.5rem 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: var(--radius-container);">
                
                <div class="empty-visual-wrapper" style="margin-bottom: 1.2rem; position: relative;">
                    <span style="font-size: 3.5rem; display: block; filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));">🚜</span>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: var(--color-primary); filter: blur(30px); opacity: 0.12; z-index: -1;"></div>
                </div>

                <div class="empty-text-stack" style="margin-bottom: 1.8rem; max-width: 600px;">
                    <h2 style="font-size: 1.7rem; font-weight: 800; color: var(--color-text-main); margin-bottom: 0.2rem; letter-spacing: -0.02em;">
                        <?= $ver_ocultos ? 'No hay zonas archivadas' : 'Sin infraestructura registrada' ?>
                    </h2>
                    
                    <?php if ($es_admin && isset($arbol['nombre_empresa'])): ?>
                        <p style="font-size: 1rem; opacity: 0.6; font-weight: 500; margin-bottom: 0.8rem;">
                             a nombre de <span style="color: var(--color-primary);"><?= $arbol['nombre_empresa'] ?></span>
                        </p>
                    <?php endif; ?>

                    <p style="opacity: 0.5; line-height: 1.5; font-size: 0.9rem;">
                        <?= $ver_ocultos 
                            ? 'Actualmente no existen parcelas o invernaderos ocultos para este agricultor.' 
                            : ($cliente_id_seleccionado 
                                ? 'Este entorno productivo aún no cuenta con parcelas activas mapeadas.' 
                                : 'Aún no se han registrado agricultores en la base de datos global.') ?>
                    </p>
                </div>

                <div class="empty-actions-row" style="display: flex; gap: 0.8rem; flex-wrap: wrap; justify-content: center;">
                    <?php if ($es_admin): ?>
                        <?php if ($cliente_id_seleccionado): ?>
                            <?= sira_btn('Añadir Parcela', 'primary', 'plus', ['href' => "formularios/formulario_parcela.php?cliente_id=".$cliente_id_seleccionado]) ?>
                        <?php else: ?>
                            <?= sira_btn('Añadir Agricultor', 'primary', 'plus', ['href' => "formularios/formulario_usuario.php"]) ?>
                        <?php endif; ?>

                        <?php if (!$ver_ocultos): ?>
                             <?= sira_btn('Consultar Histórico', 'secondary', 'list', ['href' => "dashboard.php?toggle_ocultos=1".$url_query_cliente]) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($localidades_data as $loc): ?>
                <div class="inv-smart-card">
                    
                    <!-- NIVEL 1: CABECERA REGIONAL (Municipio + IoT Badge) -->
                    <div class="card-nivel-header">
                        <div class="card-title-group">
                            <h3>
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

                    <!-- NIVEL 2: RESUMEN OPERATIVO -->
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
                                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--color-text-main);"><?= $loc['num_parcelas'] ?></span>
                                        <span style="font-size: 0.85rem; opacity: 0.5;">🚜</span>
                                    </div>
                                    <span class="tecnico-label">Parcelas</span>
                                </div>
                            </div>

                            <div class="tecnico-item-mini">
                                <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--color-primary);"><?= $loc['num_invernaderos_total'] ?></span>
                                        <span style="font-size: 0.85rem; opacity: 0.5;">🌱</span>
                                    </div>
                                    <span class="tecnico-label">Invernaderos</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- NIVEL 3: ACCIÓN DE EXPLORACIÓN (EXPANDIDA) -->
                    <a href="dashboard.php?localidad_cp=<?= urlencode($loc['codigo_postal']) ?><?= $url_query_cliente ?>" 
                       class="stretched-link"></a>
                    
                    <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                        <div>
                            <!-- Localidad Read-Only -->
                        </div>
                        <div style="text-align: right;">
                            <span class="list-subtitle" style="font-size: 0.7rem; opacity: 0.5;">PULSAR PARA EXPLORAR ➜</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($vista_actual === 'parcelas'): ?>
        <?php if (empty($parcelas_data)): ?>
            <div class="card empty-state" style="grid-column: 1 / -1; padding: 3rem 1rem;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.6;">🚜</div>
                <p style="font-weight: 500;"><?= $ver_ocultos ? 'No hay parcelas archivadas en esta localidad.' : 'No hay parcelas registradas en esta localidad.' ?></p>
                <small style="opacity: 0.5;"><?= $ver_ocultos ? 'Todos los activos vinculados están actualmente en estado activo.' : 'Pulse (+) para añadir su primera parcela operativa.' ?></small>
            </div>
        <?php endif; ?>

        <?php if ($modo_lista): ?>
            <!-- VISTA LISTA: PARCELAS -->
            <div class="sira-table-container" style="grid-column: 1 / -1; margin-top: 1rem;">
                <table class="sira-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>ID</th>
                            <th>Finca / Parcela</th>
                            <th>Referencia Catastral</th>
                            <th style="text-align: center;">Invernaderos</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelas_data as $parc): 
                            $is_archived = !($parc['activa'] ?? true);
                            $is_highlight = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $parc['parcela_id']);
                        ?>
                            <tr id="parc-card-<?= $parc['parcela_id'] ?>" 
                                class="<?= $is_archived ? 'sira-item-archived' : '' ?> <?= $is_highlight ? 'highlight-glow' : '' ?>">
                                 <td style="text-align: center;">
                                    <?php if ($is_archived): ?>
                                        <?= sira_btn('', 'mini', 'eye', ['href' => "dashboard.php?accion=restaurar_parcela_total&id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente."#parc-card-".$parc['parcela_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar Parcela (con sus invernaderos)"]) ?>
                                    <?php elseif ($es_admin || $user_rol === 'cliente'): ?>
                                        <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_borrar_parc=1&id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'style' => "color: var(--color-warning);", 'title' => "Archivar Parcela"]) ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="list-badge-tech badge-muted"><?= $parc['parcela_id'] ?></span></td>
                                <td>
                                    <div class="list-cell-main">
                                        <span class="list-main-icon">🚜</span>
                                        <div class="list-main-stack">
                                            <strong class="list-title"><?= mb_convert_case($parc['nombre'] ?: 'Finca #' . $parc['parcela_id'], MB_CASE_TITLE, "UTF-8") ?></strong>
                                            <span class="list-subtitle">Parcela Operativa</span>
                                        </div>
                                        <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?>&parcela_id=<?= $parc['parcela_id'] ?><?= $url_query_cliente ?>" class="stretched-link"></a>
                                    </div>
                                </td>
                                <td><code style="font-size: 0.85rem; opacity: 0.8;"><?= htmlspecialchars($parc['ref_catastral']) ?></code></td>
                                <td style="text-align: center;">
                                    <div class="list-data-pair">
                                        <span class="list-status-dot status-online"></span>
                                        <?php 
                                            $num_inv_activos = count(array_filter($parc['invernaderos'] ?? [], fn($i) => ($i['activa'] ?? true)));
                                        ?>
                                        <strong><?= $num_inv_activos ?></strong>
                                    </div>
                                </td>
                                 <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end; position: relative; z-index: 10;">
                                        <?php if (!$is_archived && ($es_admin || $user_rol === 'cliente')): ?>
                                            <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_parcela.php?id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => "Editar"]) ?>
                                        <?php endif; ?>
                                        <?= sira_btn('', 'mini', 'arrow', ['href' => "dashboard.php?localidad_cp=".urlencode($loc_seleccionada['codigo_postal'])."&parcela_id=".$parc['parcela_id'].$url_query_cliente, 'title' => "Ver Invernaderos", 'style' => "color: var(--color-primary);"]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- VISTA MOSAICO: PARCELAS (Existente) -->
            <?php foreach ($parcelas_data as $parc): 
                $is_archived = !($parc['activa'] ?? true);
                $is_highlight = (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $parc['parcela_id']);
            ?>
            <div id="parc-card-<?= $parc['parcela_id'] ?>" 
                 class="inv-smart-card <?= $is_archived ? 'sira-item-archived' : '' ?> <?= $is_highlight ? 'highlight-glow' : '' ?>">
                
                <!-- NIVEL 1: CABECERA DE IDENTIDAD (Nombre + ID) -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <h3>
                            <?= mb_convert_case($parc['nombre'] ?: 'Finca #' . $parc['parcela_id'], MB_CASE_TITLE, "UTF-8") ?>
                        </h3>
                        <div class="card-subtitle">
                            <span>ID REGISTRO #<?= $parc['parcela_id'] ?></span>
                        </div>
                    </div>

                    <?php if ($is_archived): ?>
                        <div style="background: rgba(100, 116, 139, 0.1); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(100, 116, 139, 0.2);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: #64748b; letter-spacing: 0.1em;">ARCHIVADA</span>
                        </div>
                    <?php else: ?>
                        <div style="background: rgba(16, 185, 129, 0.05); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(16, 185, 129, 0.15);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: var(--color-primary); letter-spacing: 0.1em;">ACTIVA</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- NIVEL 2: CORAZÓN TÉCNICO -->
                <div class="card-nivel-tecnico" style="display: flex; justify-content: space-between; align-items: center; gap: 0.8rem; padding: 1rem;">
                    
                    <!-- BLOQUE IZQUIERDO: Infraestructura (Compacto) -->
                    <div class="tecnico-bloque-identidad" style="flex: 0 1 auto; gap: 0.8rem;">
                        <div class="tecnico-avatar-icon">
                            🚜
                        </div>

                        <div style="display: flex; align-items: center; gap: 6px;">
                            <?php 
                                $num_inv_activos = count(array_filter($parc['invernaderos'] ?? [], fn($i) => ($i['activa'] ?? true)));
                            ?>
                            <strong class="tecnico-valor-main" style="<?= $num_inv_activos === 0 ? 'color: var(--color-error);' : '' ?> font-size: 1rem;">
                                <?= $num_inv_activos ?>
                            </strong>
                            <span style="font-size: 0.6rem; background: rgba(16, 185, 129, 0.15); color: var(--color-primary); padding: 1px 6px; border-radius: 4px; font-weight: 800; white-space: nowrap;">INV.</span>
                        </div>
                    </div>

                    <!-- BLOQUE DERECHO: Datos Catastrales (Compacto) -->
                    <div class="tecnico-datos-derecha" style="flex: 0 0 auto;">
                        <div class="tecnico-item-mini" style="gap: 5px;">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <span class="tecnico-label" style="opacity: 0.6; font-size: 0.55rem;">Ref. Catastral</span>
                                <span style="font-size: 0.8rem; font-weight: 800; color: var(--color-text-main); font-family: 'Roboto Mono', monospace; white-space: nowrap;"><?= htmlspecialchars($parc['ref_catastral']) ?></span>
                            </div>
                            <span style="font-size: 1rem; opacity: 0.4;">📋</span>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIONES PREMIUM (EXPANDIDAS) -->
                <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?>&parcela_id=<?= $parc['parcela_id'] ?><?= $url_query_cliente ?>" 
                   class="stretched-link"></a>

                 <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <!-- Icono de Gestión de Estado (Archivar / Restaurar) -->
                        <?php if ($is_archived): ?>
                            <?php if ($es_admin): ?>
                                <?= sira_btn('', 'mini', 'eye', ['href' => "dashboard.php?accion=restaurar_asset&target=parcela&id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente."#parc-card-".$parc['parcela_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar Parcela"]) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($es_admin): ?>
                                <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_borrar_parc=1&id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'style' => "color: var(--color-warning);", 'title' => "Archivar Parcela"]) ?>
                                <span style="opacity: 0.2;">|</span>
                            <?php endif; ?>

                            <?php if ($es_admin || $user_rol === 'cliente'): ?>
                                <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_parcela.php?id=".$parc['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => "Editar parcela"]) ?>
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
    <?php endif; ?>

    <?php if ($vista_actual === 'invernaderos'): ?>
        <?php if (empty($invernaderos_data)): ?>
            <div class="card empty-state-premium" style="grid-column: 1 / -1; padding: 2.5rem 1.5rem; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.1); border-radius: var(--radius-container);">
                
                <div class="empty-visual-wrapper" style="margin-bottom: 1.2rem; position: relative;">
                    <span style="font-size: 3.5rem; display: block; filter: drop-shadow(0 8px 12px rgba(0,0,0,0.3));">🏡</span>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 60px; height: 60px; background: var(--color-primary); filter: blur(30px); opacity: 0.12; z-index: -1;"></div>
                </div>

                <div class="empty-text-stack" style="margin-bottom: 1.8rem; max-width: 600px;">
                    <h2 style="font-size: 1.7rem; font-weight: 800; color: var(--color-text-main); margin-bottom: 0.2rem; letter-spacing: -0.02em;">
                        <?= $ver_ocultos ? 'Sin histórico de unidades' : 'Sin invernaderos registrados' ?>
                    </h2>
                    
                    <?php if ($es_admin && isset($arbol['nombre_empresa'])): ?>
                        <p style="font-size: 1rem; opacity: 0.6; font-weight: 500; margin-bottom: 0.8rem;">
                             en <span style="color: var(--color-primary);"><?= $parc_seleccionada['nombre'] ?: $parc_seleccionada['ref_catastral'] ?></span> 
                             de <?= $arbol['nombre_empresa'] ?>
                        </p>
                    <?php endif; ?>

                    <p style="opacity: 0.5; line-height: 1.5; font-size: 0.9rem;">
                        <?= $ver_ocultos 
                            ? 'No se han encontrado invernaderos o unidades operativas archivadas en esta ubicación.' 
                            : 'Esta parcela de cultivo aún no cuenta con unidades productivas asignadas en el sistema SIRA.' ?>
                    </p>
                </div>

                <div class="empty-actions-row" style="display: flex; gap: 0.8rem; flex-wrap: wrap; justify-content: center;">
                    <?php if ($es_admin): ?>
                        <?= sira_btn('Añadir Invernadero', 'primary', 'plus', ['href' => "formularios/formulario_invernadero.php?parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente]) ?>

                        <?php if (!$ver_ocultos): ?>
                             <?= sira_btn('Consultar Histórico', 'secondary', 'list', ['href' => "dashboard.php?toggle_ocultos=1&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente]) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($modo_lista): ?>
            <!-- VISTA LISTA: INVERNADEROS -->
            <div class="sira-table-container" style="grid-column: 1 / -1; margin-top: 1rem;">
                <table class="sira-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>ID</th>
                            <th>Estructura / Invernadero</th>
                            <th>DIMENSIONES</th>
                            <th style="text-align: center;">CULTIVO</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invernaderos_data as $inv): 
                            $is_inv_archived = !($inv['activa'] ?? true);
                        ?>
                            <tr id="inv-card-<?= $inv['invernadero_id'] ?>" 
                                class="<?= $is_inv_archived ? 'sira-item-archived' : '' ?>">
                                 <td style="text-align: center;">
                                    <?php if ($is_inv_archived): ?>
                                        <?php 
                                            $is_parent_parc_archived = !($parc_seleccionada['activa'] ?? true);
                                            $restore_url = "dashboard.php?accion=restaurar_asset&target=invernadero&id=" . $inv['invernadero_id'] . "&parcela_id=" . $parc_seleccionada['parcela_id'] . "&localidad_cp=" . urlencode($loc_seleccionada['codigo_postal']) . $url_query_cliente;
                                            
                                            if ($is_parent_parc_archived) {
                                                $restore_url = "dashboard.php?confirmar_restaurar_inv_jerarquico=1&id=" . $inv['invernadero_id'] . "&parcela_id=" . $parc_seleccionada['parcela_id'] . "&localidad_cp=" . urlencode($loc_seleccionada['codigo_postal']) . $url_query_cliente;
                                            }
                                        ?>
                                        <?= sira_btn('', 'mini', 'eye', ['href' => $restore_url."#inv-card-".$inv['invernadero_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar Invernadero"]) ?>
                                    <?php elseif ($es_admin || $user_rol === 'cliente'): ?>
                                        <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_borrar_inv=1&id=".$inv['invernadero_id']."&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'style' => "color: var(--color-warning);", 'title' => "Archivar Invernadero"]) ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="list-badge-tech badge-muted"><?= $inv['invernadero_id'] ?></span></td>
                                <td>
                                    <div class="list-cell-main">
                                        <span class="list-main-icon"><?= get_crop_icon($inv['cultivo'] ?? null) ?: '🏡' ?></span>
                                        <div class="list-main-stack">
                                            <strong class="list-title"><?= mb_convert_case($inv['nombre'], MB_CASE_TITLE, "UTF-8") ?></strong>
                                            <span class="list-subtitle">Unidad Productiva</span>
                                        </div>
                                        <a href="sensores.php?id=<?= $inv['invernadero_id'] ?>&nombre=<?= urlencode($inv['nombre']) ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?>&localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>" class="stretched-link"></a>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-size: 0.85rem; font-weight: 800;"><?= (float)$inv['largo_m'] ?>m × <?= (float)$inv['ancho_m'] ?>m</span>
                                        <small style="font-size: 0.65rem; opacity: 0.5;"><?= (int)($inv['largo_m'] * $inv['ancho_m']) ?> m²</small>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <div class="list-data-pair">
                                        <span class="list-status-dot <?= $inv['cultivo'] ? 'status-online' : 'status-offline' ?>"></span>
                                        <strong style="font-size: 0.8rem;"><?= $inv['cultivo'] ? mb_convert_case($inv['cultivo'], MB_CASE_TITLE, "UTF-8") : 'Barbecho' ?></strong>
                                    </div>
                                </td>
                                 <td style="text-align: right;">
                                    <div style="display: flex; gap: 8px; justify-content: flex-end; position: relative; z-index: 10;">
                                        <?php if (!$is_inv_archived): ?>
                                            <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_invernadero.php?id=".$inv['invernadero_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => "Editar"]) ?>
                                            
                                            <?php 
                                                $jinfo = $jornadas_map[$inv['invernadero_id']] ?? null;
                                                $is_conf = $jinfo['configurado'] ?? false;
                                                $is_lab = $jinfo['es_laborable'] ?? true;
                                                
                                                $j_icon = "clock"; $j_title = "Jornada pendiente de configurar"; $j_color = "var(--color-error)";
                                                if ($is_conf) {
                                                    if (!$is_lab) { $j_icon = "plus"; $j_title = "Uso como Almacén (No laborable)"; $j_color = "#64748b"; }
                                                    else { $j_icon = "calendar"; $j_title = "Jornada Laboral Configurada"; $j_color = "var(--color-primary)"; }
                                                }
                                            ?>
                                            <?= sira_btn('', 'mini', $j_icon, ['href' => "formularios/formulario_jornada.php?inv_id=".$inv['invernadero_id']."&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => $j_title, 'style' => "color: $j_color;"]) ?>
                                            <?= sira_btn('', 'mini', 'tomato', ['href' => "dashboard.php?localidad_cp=".urlencode($loc_seleccionada['codigo_postal'])."&parcela_id=".$parc_seleccionada['parcela_id']."&plant_inv_id=".$inv['invernadero_id'].$url_query_cliente."#inv-card-".$inv['invernadero_id'], 'title' => "Plantar", 'style' => "color: var(--color-primary);"]) ?>
                                        <?php endif; ?>
                                        <?= sira_btn('', 'mini', 'arrow', ['href' => "sensores.php?id=".$inv['invernadero_id']."&nombre=".urlencode($inv['nombre'])."&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => "Ver Sensores"]) ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- VISTA MOSAICO: INVERNADEROS (Existente) -->
            <?php foreach ($invernaderos_data as $inv): 
                $is_target = (isset($_GET['plant_inv_id']) && $_GET['plant_inv_id'] == $inv['invernadero_id']) || (isset($_GET['highlight_id']) && $_GET['highlight_id'] == $inv['invernadero_id']);
                $is_inv_archived = !($inv['activa'] ?? true);
                $puede_editar_inv = ($es_admin || $user_rol === 'cliente');

                // [V14.2] Detección de Estado Operativo (Almacén vs Producción)
                $jinfo = $jornadas_map[$inv['invernadero_id']] ?? null;
                $is_conf = $jinfo['configurado'] ?? false;
                $is_lab  = $jinfo['es_laborable'] ?? true;
                $es_almacen = ($is_conf && !$is_lab);
            ?>
            <div id="inv-card-<?= $inv['invernadero_id'] ?>" 
                 class="inv-smart-card <?= $is_target ? 'highlight-glow' : '' ?> <?= $is_inv_archived ? 'sira-item-archived' : '' ?>">
                
                <!-- NIVEL 1: CABECERA DE CONTROL (Identidad + Live IoT) -->
                <div class="card-nivel-header">
                    <div class="card-title-group">
                        <h3 title="<?= htmlspecialchars($inv['nombre']) ?>">
                            <?= mb_convert_case($inv['nombre'], MB_CASE_TITLE, "UTF-8") ?>
                        </h3>
                        <div class="card-subtitle">
                            <span>#<?= $inv['invernadero_id'] ?></span>
                            <span style="opacity: 0.3;">|</span>
                            <span>📦 <?= mb_convert_case($loc_seleccionada['municipio'], MB_CASE_TITLE, "UTF-8") ?></span>
                        </div>
                    </div>

                    <?php if ($is_inv_archived): ?>
                        <div style="background: rgba(100, 116, 139, 0.1); padding: 4px 12px; border-radius: var(--radius-container); border: 1px solid rgba(100, 116, 139, 0.2);">
                            <span style="font-size: 0.65rem; font-weight: 900; color: #64748b; letter-spacing: 0.1em;">ARCHIVADO</span>
                        </div>
                    <?php elseif ($es_almacen): ?>
                        <div class="status-live-container" title="Monitorización en espera (Modo Almacén)" style="background: rgba(100, 116, 139, 0.1); border-color: rgba(100, 116, 139, 0.2);">
                            <span class="status-pulse-dot" style="background: #64748b; box-shadow: none; animation: none; opacity: 0.5;"></span>
                            <span class="badge-text-premium" style="color: #94a3b8;">STANDBY</span>
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
                            <?= $es_almacen ? '📦' : (get_crop_icon($inv['cultivo'] ?? null) ?: '🏡') ?>
                        </div>
                        <div class="tecnico-datos-group">
                            <span class="tecnico-label">Producción Actual</span>
                            <strong class="tecnico-valor-main"><?php 
                                if ($es_almacen) echo "Almacén";
                                else echo $inv['cultivo'] ? mb_convert_case($inv['cultivo'], MB_CASE_TITLE, "UTF-8") : 'En Barbecho'; 
                            ?></strong>
                        </div>
                    </div>

                    <!-- BLOQUE DERECHO: Especificaciones -->
                    <div class="tecnico-datos-derecha">
                        <div class="tecnico-item-mini">
                            <div style="display: flex; flex-direction: column; align-items: flex-end;">
                                <span class="tecnico-label" style="opacity: 0.7;">Dimensiones</span>
                                <span style="font-size: 0.85rem; font-weight: 800; color: var(--color-text-main); white-space: nowrap;">
                                    <?= (float)$inv['largo_m'] ?>m × <?= (float)$inv['ancho_m'] ?>m
                                </span>
                                <small style="font-size: 0.65rem; opacity: 0.4; font-weight: bold;"><?= (int)($inv['largo_m'] * $inv['ancho_m']) ?> m² totales</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NIVEL 3: ACCIONES ESTÁNDAR SIRA -->
                <a href="sensores.php?id=<?= $inv['invernadero_id'] ?>&nombre=<?= urlencode($inv['nombre']) ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?>&localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>" 
                   class="stretched-link"></a>

                 <div style="margin-top: auto; display: flex; justify-content: space-between; align-items: center; position: relative; z-index: 10;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <!-- Icono de Gestión de Estado (Archivar / Restaurar) -->
                        <?php if ($is_inv_archived): ?>
                            <?php 
                                $is_parent_parc_archived = !($parc_seleccionada['activa'] ?? true);
                                $restore_url = "dashboard.php?accion=restaurar_asset&target=invernadero&id=" . $inv['invernadero_id'] . "&parcela_id=" . $parc_seleccionada['parcela_id'] . "&localidad_cp=" . urlencode($loc_seleccionada['codigo_postal']) . $url_query_cliente;
                                
                                if ($is_parent_parc_archived) {
                                    $restore_url = "dashboard.php?confirmar_restaurar_inv_jerarquico=1&id=" . $inv['invernadero_id'] . "&parcela_id=" . $parc_seleccionada['parcela_id'] . "&localidad_cp=" . urlencode($loc_seleccionada['codigo_postal']) . $url_query_cliente;
                                }
                            ?>
                            <?= sira_btn('', 'mini', 'eye', ['href' => $restore_url."#inv-card-".$inv['invernadero_id'], 'style' => "color: var(--color-primary);", 'title' => "Restaurar Invernadero"]) ?>
                        <?php else: ?>
                            <?php if ($es_admin): ?>
                                <?= sira_btn('', 'mini', 'delete', ['href' => "dashboard.php?confirmar_borrar_inv=1&id=".$inv['invernadero_id']."&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'style' => "color: var(--color-warning);", 'title' => "Archivar Invernadero"]) ?>
                                <span style="opacity: 0.2;">|</span>
                            <?php endif; ?>

                            <?php if ($es_admin || $user_rol === 'cliente'): ?>
                                <?= sira_btn('', 'mini', 'gear', ['href' => "formularios/formulario_invernadero.php?id=".$inv['invernadero_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => "Editar invernadero"]) ?>
                                
                                <?php 
                                    $j_icon = "clock"; $j_title = "Configuración de jornada pendiente"; $j_color = "var(--color-error)";
                                    if ($is_conf) {
                                        if (!$is_lab) { $j_icon = "plus"; $j_title = "Modo Almacén (Sin jornada)"; $j_color = "#64748b"; }
                                        else { $j_icon = "calendar"; $j_title = "Jornada Laboral Configurada"; $j_color = "var(--color-primary)"; }
                                    }
                                ?>
                                <?= sira_btn('', 'mini', $j_icon, ['href' => "formularios/formulario_jornada.php?inv_id=".$inv['invernadero_id']."&parcela_id=".$parc_seleccionada['parcela_id']."&localidad_cp=".urlencode($loc_seleccionada['codigo_postal']).$url_query_cliente, 'title' => $j_title, 'style' => "color: $j_color;"]) ?>
                            <?php endif; ?>
                            
                            <?= sira_btn('', 'mini', 'tomato', ['href' => "dashboard.php?localidad_cp=".urlencode($loc_seleccionada['codigo_postal'])."&parcela_id=".$parc_seleccionada['parcela_id']."&plant_inv_id=".$inv['invernadero_id'].$url_query_cliente."#inv-card-".$inv['invernadero_id'], 'title' => "Plantar o cambiar cultivo"]) ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="text-align: right;">
                        <span class="list-subtitle" style="font-size: 0.7rem; opacity: 0.5;">PANEL DE SENSORES ➜</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>