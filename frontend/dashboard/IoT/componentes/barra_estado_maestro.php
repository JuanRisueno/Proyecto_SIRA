<!-- Barra de Estado Maestro IoT -->
<div class="master-status-bar">
    <div class="status-group">
        <span class="status-label" style="font-size: 0.65rem; margin-right: 5px;">📡 SENSORES:</span>
        <?php foreach (array_slice($sensores_final, 0, 5) as $s): ?>
            <div class="status-item">
                <span title="<?= htmlspecialchars($s['tipo']) ?>"><?= mb_substr(htmlspecialchars($s['tipo']), 0, 4) ?>.</span>
                <span style="color: var(--color-primary);"><?= number_format($s['valor'] ?? 0, 1) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="v-divider"></div>

    <div class="status-group">
        <span class="status-label" style="font-size: 0.65rem; margin-right: 5px;">⚙️ ACTUADORES:</span>
        <?php foreach (array_slice($actuadores_final, 0, 5) as $a): ?>
            <?php $is_on = (stripos($a['estado'], 'ON') !== false || stripos($a['estado'], 'ENCENDID') !== false || stripos($a['estado'], 'ABIERTO') !== false); ?>
            <div class="status-item" title="<?= htmlspecialchars($a['tipo']) ?>: <?= htmlspecialchars($a['estado']) ?>">
                <div class="led-indicator <?= $is_on ? 'led-on' : 'led-off' ?>"></div>
                <span><?= (stripos($a['tipo'], 'riego') !== false) ? 'RIEGO' : mb_substr(htmlspecialchars($a['tipo']), 0, 6) ?>.</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
