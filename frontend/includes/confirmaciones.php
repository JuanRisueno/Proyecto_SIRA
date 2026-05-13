<?php
/**
 * confirmaciones.php - Componente Centralizado de Confirmación SIRA (Zero-JS)
 * 
 * Este componente estandariza la respuesta visual tras operaciones exitosas o fallidas,
 * incluyendo la cuenta atrás técnica y visual para la redirección.
 * 
 * Parámetros esperados (o valores por defecto):
 * @var string $conf_icon  Emoji o icono visual (default: ✅)
 * @var string $conf_title Título de la tarjeta (default: Operación Completada)
 * @var string $conf_msg   Mensaje detallado para el usuario
 * @var string $conf_redir URL de destino para la redirección
 * @var string $conf_type  Tipo de alerta: 'success', 'error', 'warning' (default: success)
 */

$conf_icon  = $conf_icon  ?? '✅';
$conf_title = $conf_title ?? 'Operación Completada';
$conf_msg   = $conf_msg   ?? 'Los cambios se han guardado correctamente.';
$conf_redir = $conf_redir ?? '../dashboard.php';
$conf_type  = $conf_type  ?? 'success';

// Mapeo dinámico de estilos según el tipo
switch ($conf_type) {
    case 'error':
        $border_color = '#ef4444'; $title_color = '#f87171'; $card_class = 'confirm-card-error'; break;
    case 'warning':
        $border_color = '#f59e0b'; $title_color = '#fbbf24'; $card_class = 'confirm-card-warning'; break;
    case 'success':
    default:
        $border_color = '#10b981'; $title_color = '#34d399'; $card_class = ''; break;
}
?>

<!-- Inyección Técnica de Redirección -->
<script>window.scrollTo(0, 0);</script>
<meta http-equiv="refresh" content="3;url=<?= $conf_redir ?>">

<!-- Interfaz Visual Premium -->
<div class="confirm-overlay">
    <div class="confirm-card <?= $card_class ?>" style="border-color: <?= $border_color ?>;">
        <div style="font-size: 3.5rem; margin-bottom: 1rem;"><?= $conf_icon ?></div>
        <h2 style="color: <?= $title_color ?>; margin-top: 0;"><?= $conf_title ?></h2>
        
        <div class="confirm-msg-box">
            <p style="margin: 0; color: var(--color-text-main); font-weight: 500; font-size: 1.05rem;">
                <?= htmlspecialchars($conf_msg) ?>
            </p>
        </div>
        
        <div class="sira-countdown-text">
            Volviendo al panel en 
            <div class="sira-countdown-number">
                <span class="n-3">3</span>
                <span class="n-2">2</span>
                <span class="n-1">1</span>
            </div>
        </div>
    </div>
</div>
