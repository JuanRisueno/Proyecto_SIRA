<!-- Cabeceras Alineadas con Fondo Glass -->
<div class="iot-headers">
    <div>
        <h3 class="iot-col-title">Lectura de Sensores</h3>
    </div>
    <div>
        <h3 class="iot-col-title">Control de Actuadores</h3>
        <span style="font-size: 0.75rem; color: var(--color-text-muted); display: block; margin-top: 4px; font-weight: 600;">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="vertical-align: sub; margin-right: 4px; opacity: 0.7;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Modo Automático coordinado por SIRA Intelligence
        </span>
    </div>
</div>

<!-- Grid Layout IoT Dinámico y Alineado -->
<div class="iot-grid">
    <?php 
    $max_rows = max(count($sensores_final), count($actuadores_final));
    for ($i = 0; $i < $max_rows; $i++):
        $s = $sensores_final[$i] ?? null;
        $a = $actuadores_final[$i] ?? null;
    ?>
        <!-- COLUMNA SENSOR -->
        <?php if ($s): 
            $color = "var(--color-primary)";
            if(stripos($s['tipo'], 'temp') !== false) $color = "#f59e0b";
            if(stripos($s['tipo'], 'humedad') !== false || stripos($s['tipo'], 'suelo') !== false) $color = "#3b82f6";
            if(stripos($s['tipo'], 'viento') !== false) $color = "#a855f7";
            if(stripos($s['tipo'], 'lluvia') !== false) $color = "#60a5fa";
        ?>
            <div id="sen-card-<?= $s['sensor_id'] ?>" class="iot-card sensor-card">
                <div class="iot-card-header">
                    <span class="iot-label"><?= htmlspecialchars($s['tipo']) ?></span>
                    <span class="iot-tag live">
                        <span class="led-indicator led-on" style="width: 8px; height: 8px; margin-right: 5px;"></span>
                        En vivo
                    </span>
                </div>
                <div class="iot-value-block" style="color: <?= $color ?>; margin-bottom: 15px;">
                    <span class="iot-value"><?= $s['valor'] !== null ? htmlspecialchars($s['valor']) : '--' ?></span>
                    <span class="iot-unit"><?= htmlspecialchars($s['unidad']) ?></span>
                </div>
                <div class="iot-chart-box" style="margin-top: auto;">
                    <?= render_svg_chart($s['sensor_id'], $token, $color) ?>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-placeholder"></div>
        <?php endif; ?>

        <!-- COLUMNA ACTUADOR -->
        <?php if ($a): 
            $estado = strtoupper($a['estado'] ?? 'APAGADO');
            $is_on = (stripos($estado, 'ENCENDI') !== false || stripos($estado, 'ABIERT') !== false);
            $es_led = (stripos($a['tipo'], 'luz') !== false || stripos($a['tipo'], 'led') !== false || stripos($a['tipo'], 'ilumina') !== false);
        ?>
            <div id="act-card-<?= $a['actuador_id'] ?>" class="iot-card actuator-card">
                <div class="iot-card-header">
                    <span class="iot-label"><?= htmlspecialchars($a['tipo']) ?></span>
                    <?php if ($es_led && !$jornada_configurada): ?>
                        <span class="iot-tag warning">⚠️ Sin Jornada</span>
                    <?php elseif ($a['modo_manual']): ?>
                        <span class="iot-tag manual">✋ Manual</span>
                    <?php else: ?>
                        <span class="iot-tag auto">⚙ Auto</span>
                    <?php endif; ?>
                </div>

                <?php if ($es_led && !$jornada_configurada): ?>
                    <div class="iot-warning-notice">
                        No se automatiza porque no está configurada una jornada laboral.
                    </div>
                <?php endif; ?>
                
                <div class="actuator-status <?= $is_on ? 'status-on' : 'status-off' ?>" style="flex-grow: 1; display:flex; align-items:center; justify-content:center; margin-bottom: 20px;">
                    <?= htmlspecialchars($estado) ?>
                </div>

                <form method="POST" class="actuator-controls" style="display: flex; flex-direction: column; gap: 0.5rem; flex-grow: 1;">
                    <input type="hidden" name="override_actuador" value="1">
                    <input type="hidden" name="actuador_id" value="<?= $a['actuador_id'] ?>">
                    <!-- Estado actual como fallback (si se pulsa persistencia) -->
                    <input type="hidden" name="nuevo_estado" value="<?= htmlspecialchars($estado) ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <?php if(stripos($a['tipo'], 'ventana') !== false): ?>
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.3rem; width: 100%;">
                                <button type="submit" name="nuevo_estado" value="CERRADO" class="btn-sira btn-secondary btn-sm" style="font-size: 0.65rem; padding: 0.4rem 0;" title="Cerrar ventana">0%</button>
                                <button type="submit" name="nuevo_estado" value="ABIERTO 20%" class="btn-sira btn-primary btn-sm" style="font-size: 0.65rem; padding: 0.4rem 0;" title="Ventilación mínima">20%</button>
                                <button type="submit" name="nuevo_estado" value="ABIERTO 50%" class="btn-sira btn-primary btn-sm" style="font-size: 0.65rem; padding: 0.4rem 0;" title="Ventilación media">50%</button>
                                <button type="submit" name="nuevo_estado" value="ABIERTO 100%" class="btn-sira btn-primary btn-sm" style="font-size: 0.65rem; padding: 0.4rem 0;" title="Apertura total">100%</button>
                            </div>
                        <?php else: ?>
                            <button type="submit" name="nuevo_estado" value="ENCENDIDO" class="btn-sira btn-primary btn-sm" style="width: 100%;">ON</button>
                            <button type="submit" name="nuevo_estado" value="APAGADO" class="btn-sira btn-secondary btn-sm" style="width: 100%;">OFF</button>
                        <?php endif; ?>
                    </div>

                        <?php if ($a['modo_manual'] && !($es_led && !$jornada_configurada)): 
                            $curr_p = $_SESSION['iot_persistence_' . $a['actuador_id']] ?? 'perm';
                        ?>
                            <!-- Opciones de Persistencia Manual (Tono Naranja SIRA con Feedback Visual) -->
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem; margin-top: 8px;">
                                <button type="submit" name="set_persistence" value="2h" class="btn-sira btn-warning btn-sm" 
                                    style="font-size: 0.65rem; padding: 0.5rem; border-radius: 4px; transition: all 0.3s; <?= $curr_p === '2h' ? 'opacity: 1; border: 2px solid #b45309;' : 'opacity: 0.4; filter: grayscale(0.5); border: 2px solid transparent;' ?>" 
                                    title="Volver a automático tras 2 horas">🕒 Auto (2h)</button>
                                
                                <button type="submit" name="set_persistence" value="perm" class="btn-sira btn-warning btn-sm" 
                                    style="font-size: 0.65rem; padding: 0.5rem; border-radius: 4px; transition: all 0.3s; <?= $curr_p === 'perm' ? 'opacity: 1; border: 2px solid #b45309;' : 'opacity: 0.4; filter: grayscale(0.5); border: 2px solid transparent;' ?>" 
                                    title="Mantener modo manual indefinidamente">🔒 Mantener</button>
                            </div>

                            <button type="submit" name="nuevo_estado" value="AUTO" class="btn-sira btn-secondary btn-sm" style="width: 100%; margin-top: 8px; background: transparent; border-color: var(--color-primary); color: var(--color-primary); letter-spacing: 0;">Volver a modo AUTO</button>
                        <?php endif; ?>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-placeholder"></div>
        <?php endif; ?>

    <?php endfor; ?>
</div>
