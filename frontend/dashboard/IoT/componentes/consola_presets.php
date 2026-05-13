<!-- SIRA Console: Escenarios de Simulación -->
<div class="console-panel">
    <?php if (!empty($estado_iot['_error'])): ?>
        <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 1.5rem; margin-bottom: 2rem; border-radius: 8px;">
            <h3 style="color: #fca5a5; margin-bottom: 0.5rem;">🔥 API Error <?= htmlspecialchars($estado_iot['_code']) ?></h3>
            <p style="color: white; font-family: monospace;"><?= htmlspecialchars($estado_iot['_response']) ?></p>
            <p style="color: gray; font-size: 0.8rem;"><?= htmlspecialchars($estado_iot['_curl_err']) ?></p>
        </div>
    <?php endif; ?>

    <div class="console-header">
        <div>
            <h2>SIRA Console | 🌱 <?= htmlspecialchars($nombre_inv) ?></h2>
            <div style="display: flex; gap: 10px; align-items: center; margin-top: 5px; flex-wrap: wrap;">
                <span style="background: rgba(16, 185, 129, 0.2); color: var(--color-primary); padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: 700;">
                    🍇 Cultivo Activo: <?= htmlspecialchars(strtoupper($nombre_cultivo)) ?>
                </span>
                
                <?php if ($parametros_optimos): ?>
                    <span style="background: rgba(14, 165, 233, 0.1); color: #38bdf8; padding: 5px 12px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; border: 1px solid rgba(14, 165, 233, 0.3);">
                        Fase: <b><?= htmlspecialchars($parametros_optimos['fase']) ?></b> &nbsp;|&nbsp; 
                        🌡️ <?= $parametros_optimos['temp_min'] ?> - <?= $parametros_optimos['temp_max'] ?>ºC &nbsp;|&nbsp; 
                        💧 <?= $parametros_optimos['hum_min'] ?> - <?= $parametros_optimos['hum_max'] ?>%
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <a href="formularios/formulario_jornada.php?inv_id=<?= $id_inv ?>&type=invernadero&from=sensores&localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal'] ?? '') ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?? '' ?>" 
               class="btn-sira btn-secondary btn-sm" 
               style="padding: 0.4rem 0.8rem; font-size: 0.75rem; display: flex; align-items: center; gap: 5px; height: 36px; border-radius: 50px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1);"
               title="Configurar jornada laboral de este invernadero">
               <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
               Configurar
            </a>
            <div class="jornada-badge <?= $jornada_activa ? 'jornada-on' : 'jornada-off' ?>">
                <?= $jornada_activa ? '☀ JORNADA LABORAL' : '🌙 FUERA DE HORARIO' ?>
            </div>
        </div>
    </div>
    
    <form method="POST" action="sensores.php?<?= http_build_query($_GET) ?>" class="preset-bar">
        <input type="hidden" name="invernadero_id" value="<?= htmlspecialchars($id_inv) ?>">
        
        <?= sira_btn('Ideal', 'ideal', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'ideal']) ?>
        <?= sira_btn('Tormenta', 'storm', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'tormenta']) ?>
        <?= sira_btn('Calor', 'heat', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'calor']) ?>
        <?= sira_btn('Helada', 'frost', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'helada']) ?>
        <?= sira_btn('Nublado', 'cloudy', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'nublado']) ?>
        <?= sira_btn('Sequía', 'drought', null, ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'sequia']) ?>
        
        <?php 
            $vfx_enabled = (!isset($_SESSION['vfx_enabled']) || $_SESSION['vfx_enabled']);
            $vfx_class = $vfx_enabled ? 'vfx-on' : 'vfx-off';
            $vfx_title = $vfx_enabled ? 'Apagar efectos climáticos' : 'Encender efectos climáticos';
        ?>
        <?= sira_btn('', 'secondary', 'vfx', [
            'type' => 'submit', 
            'name' => 'toggle_vfx', 
            'value' => '1', 
            'class' => "btn-vfx-toggle $vfx_class", 
            'title' => $vfx_title
        ]) ?>

        <?= sira_btn('RANDOMIZE', 'random', 'random', ['type' => 'submit', 'name' => 'simular_escenario', 'value' => 'random']) ?>
    </form>
</div>
