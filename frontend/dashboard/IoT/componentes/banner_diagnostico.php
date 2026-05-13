<!-- Banner: Diagnóstico Inteligente SIRA -->
<div class="intelligence-banner">
    <div class="intelligence-icon-box">
        <svg width="28" height="28" fill="none" stroke="white" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
        </svg>
    </div>
    <div class="intelligence-content">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 4px;">
            <h4>SIRA Intelligence | Estado del Cultivo</h4>
            <?php if ($hora_virtual_api && $hora_virtual_api !== '--:--'): ?>
                <span class="virtual-time-badge">
                    🕒 HORA VIRTUAL: <?= htmlspecialchars($hora_virtual_api) ?>
                </span>
            <?php endif; ?>
        </div>
        <p>
            <?= htmlspecialchars($diagnostico_humano) ?>
        </p>
    </div>
</div>
