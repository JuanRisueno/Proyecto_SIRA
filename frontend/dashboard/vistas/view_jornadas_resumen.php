<?php
/**
 * view_jornadas_resumen.php - Resumen de Jornadas (Premium UI V13.0)
 * Rediseño industrial basado en el sistema de tarjetas inteligentes de SIRA.
 */
?>

<div class="jornadas-resumen-wrapper" style="margin-top: 3.5rem;">
    <!-- El título de sección y botones de retorno ahora están gestionados centralizadamente por dashboard/componentes/header.php -->

    <!-- SECCIÓN DE CONFIGURACIÓN MAESTRA (GLOBAL) -->
    <div class="master-policy-banner">
        <div class="master-content">
            <div class="master-icon">🌍</div>
            <div class="master-text">
                <h3>Política Horaria <span>Global</span></h3>
                <p>Configura el turno maestro para todos los invernaderos sincronizados.</p>
            </div>
        </div>
        <div class="master-actions">
            <a href="formularios/formulario_jornada.php?type=global&cliente_id=<?= $cliente_id_seleccionado ?>&from=jornadas_resumen" class="btn-master">
                ⚙️ CONFIGURAR MAESTRO
            </a>
            <a href="dashboard.php?seccion=jornadas_resumen&confirmar_reset_jornada=1&cliente_id=<?= $cliente_id_seleccionado ?>" 
               class="btn-reset-master">
                🗑️ RESETEAR TODO
            </a>
        </div>
    </div>

    <?php if (empty($resumen_jornadas)): ?>
        <div class="card empty-state-premium" style="padding: 5rem; text-align: center; background: rgba(255,255,255,0.01); border: 1px dashed rgba(255,255,255,0.1);">
            <div style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.2;">📅</div>
            <h3 style="font-size: 1.5rem; opacity: 0.8;">No se detectaron infraestructuras</h3>
            <p style="opacity: 0.5;">Asegúrate de haber registrado invernaderos en tus parcelas para configurar sus jornadas.</p>
        </div>
    <?php else: ?>
        
        <!-- GRID DE TARJETAS INTELIGENTES -->
        <div class="infra-grid-container">
            <?php foreach ($resumen_jornadas as $j): 
                $is_conf = $j['configurado'];
                $is_lab = $j['es_laborable'];
                $is_sync = $j['heredar_de_global'] ?? false;
                
                // Definición de Estética por Estado
                $card_class = "jornada-card-active";
                $status_icon = "🕒";
                $status_label = "Configurada";
                $glow_color = "rgba(52, 211, 153, 0.15)";
                $border_color = "rgba(52, 211, 153, 0.2)";

                if (!$is_lab) {
                    $status_icon = "📦";
                    $status_label = "Almacén";
                    $glow_color = "rgba(148, 163, 184, 0.1)";
                    $border_color = "rgba(148, 163, 184, 0.2)";
                } elseif ($is_sync) {
                    $status_icon = "🔗";
                    $status_label = "Sincronizado";
                    $glow_color = "rgba(59, 130, 246, 0.15)";
                    $border_color = "rgba(59, 130, 246, 0.3)";
                } elseif (!$is_conf) {
                    $status_icon = "⚠️";
                    $status_label = "Pendiente";
                    $glow_color = "rgba(239, 68, 68, 0.15)";
                    $border_color = "rgba(239, 68, 68, 0.3)";
                }
            ?>
                <div class="inv-smart-card" style="border-top: 3px solid <?= $border_color ?>; background: linear-gradient(180deg, <?= $glow_color ?> 0%, rgba(0,0,0,0) 100%);">
                    
                    <!-- HEADER: Identidad -->
                    <div class="card-nivel-header" style="margin-bottom: 1.5rem;">
                        <div class="card-title-group">
                            <h3 style="font-size: 1.2rem;"><?= htmlspecialchars($j['nombre']) ?></h3>
                            <div class="card-subtitle">
                                <span>#<?= str_pad($j['invernadero_id'], 3, '0', STR_PAD_LEFT) ?></span>
                                <span style="opacity: 0.3;">|</span>
                                <span><?= $is_lab ? 'PRODUCTIVO' : 'NO LABORABLE' ?></span>
                            </div>
                        </div>
                        <div style="font-size: 1.5rem; opacity: 0.8;"><?= $status_icon ?></div>
                    </div>

                    <!-- CUERPO: Datos Técnicos -->
                    <div class="card-nivel-tecnico" style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.03);">
                        <div style="display: flex; flex-direction: column; gap: 10px; width: 100%;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="tecnico-label">Finca / Parcela</span>
                                <span style="font-size: 0.8rem; font-weight: 700; color: white;">🚜 <?= htmlspecialchars($j['parcela_nombre']) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="tecnico-label">Estado Planificación</span>
                                <span class="badge-text-premium" style="font-size: 0.65rem; background: <?= $border_color ?>; color: white; padding: 2px 8px; border-radius: 4px;">
                                    <?= strtoupper($status_label) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIÓN PRINCIPAL -->
                    <div style="margin-top: auto;">
                        <a href="formularios/formulario_jornada.php?inv_id=<?= $j['invernadero_id'] ?>&from=jornadas_resumen&cliente_id=<?= $cliente_id_seleccionado ?>" 
                           class="btn-sira" 
                           style="width: 100%; justify-content: center; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 0.8rem; font-weight: 800; font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase; transition: all 0.3s ease;">
                            ⚙️ Configurar Horarios
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <!-- PIE DE PAGINA / INFO -->
    <div style="margin-top: 3rem; padding: 1.5rem; background: rgba(59, 130, 246, 0.05); border-radius: var(--radius-container); border: 1px solid rgba(59, 130, 246, 0.1); display: flex; align-items: center; gap: 15px;">
        <span style="font-size: 1.5rem;">💡</span>
        <p style="font-size: 0.9rem; opacity: 0.7; margin: 0; line-height: 1.5;">
            <strong>Nota Técnica:</strong> La jornada laboral configurada aquí afecta directamente a los algoritmos de automatización de iluminación y clima. Si un invernadero se usa solo como <strong>almacén</strong>, desactiva su modo laborable para evitar alertas innecesarias.
        </p>
    </div>

</div>


