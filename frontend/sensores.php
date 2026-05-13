<?php
/**
 * SIRA — sensores.php
 * Panel de control y monitorización IoT para invernaderos.
 * Refactorizado v12.0: Arquitectura Modular (IoT Directory)
 */
session_start();
require_once 'includes/config.php';

// PARÁMETROS BASE
$id_inv     = $_GET['id'] ?? 1;
$nombre_inv = $_GET['nombre'] ?? 'Invernadero';
$token      = $_SESSION['jwt_token'];

// 1. CARGA DE LÓGICA Y DATOS (Ecosistema IoT)
require_once 'dashboard/api/api_infraestructura.php';
require_once 'dashboard/api/api_produccion.php';
require_once 'dashboard/IoT/logic.php';

// 2. CONFIGURACIÓN DE PÁGINA
$page_title = "SIRA Console | " . htmlspecialchars($nombre_inv);
$page_css   = "sensores";    
require_once 'includes/header.php';
?>
<?php
    // SIRA Weather Engine: Gestión dinámica de VFX (Nieve, Tormenta, Calor)
    include 'dashboard/IoT/clima/weather_engine.php';
?>

<?php 
    // [V14.5] Reconstrucción de Contexto para Breadcrumbs (Migas de Pan)
    $inv_detalle = obtenerDetalleAsset($token, false, $id_inv);
    $cliente_id_seleccionado = null;
    $loc_seleccionada = null;
    $parc_seleccionada = null;
    $arbol = ['nombre_empresa' => 'Mi Cuenta'];
    $url_query_cliente = "";

    if ($inv_detalle && isset($inv_detalle['parcela'])) {
        $p = $inv_detalle['parcela'];
        $cliente_id_seleccionado = $p['cliente_id'] ?? null;
        $url_query_cliente = $cliente_id_seleccionado ? "&cliente_id=$cliente_id_seleccionado" : "";
        
        $arbol['nombre_empresa'] = $p['cliente']['nombre_empresa'] ?? 'Agricultor';
        
        $loc_seleccionada = [
            'codigo_postal' => $p['codigo_postal'],
            'municipio' => $p['localidad']['municipio'] ?? 'Localidad'
        ];
        
        $parc_seleccionada = [
            'parcela_id' => $inv_detalle['parcela_id'],
            'ref_catastral' => $p['ref_catastral'] ?? 'Parcela'
        ];
    }
?>

<div class="container" style="padding-bottom: 0;">
    <div class="iot-header-container">
        <!-- Sistema de Navegación: Migas de Pan (SIRA Breadcrumbs) -->
        <div class="breadcrumbs">
            <?php if ($cliente_id_seleccionado): ?>
                <a href="formularios/formulario_usuario.php?id=<?= $cliente_id_seleccionado ?>" class="account-breadcrumb-btn">👤 Mi Cuenta</a>
                <span class="breadcrumb-separator">/</span>
            <?php endif; ?>
            
            <a href="dashboard.php?reset_ocultos=1<?= $cliente_id_seleccionado ? '&cliente_id=' . $cliente_id_seleccionado : '' ?>">💼 <?= htmlspecialchars($arbol['nombre_empresa']) ?></a>
            
            <?php if ($loc_seleccionada): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>">
                    <?= htmlspecialchars($loc_seleccionada['municipio']) ?>
                </a>
            <?php endif; ?>
            
            <?php if ($parc_seleccionada): ?>
                <span class="breadcrumb-separator">/</span>
                <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?><?= $url_query_cliente ?>">
                    Parcela <?= htmlspecialchars($parc_seleccionada['ref_catastral']) ?>
                </a>
            <?php endif; ?>

            <span class="breadcrumb-separator">/</span>
            <span style="color: var(--color-primary); font-weight: 700; opacity: 0.9;">🌱 <?= htmlspecialchars($nombre_inv) ?></span>
        </div>

        <!-- Título y Acciones -->
        <div class="header-content">
            <h1 class="dashboard-title sira-page-title" style="margin-bottom: 0.5rem;">
                Monitorización IoT — <?= htmlspecialchars($nombre_inv) ?>
            </h1>
            <p class="dashboard-subtitle" style="font-size: 1rem; opacity: 0.8;">
                Control de sensores y actuadores en tiempo real.
            </p>
        </div>
    </div>
</div>

<div class="container iot-console" style="padding-top: 0;">
    
    <!-- Módulos de Interfaz IoT -->
    <?php 
        // 1. Banner de Inteligencia SIRA (Diagnóstico)
        echo '<div id="iot-diagnostico">';
        include 'dashboard/IoT/componentes/banner_diagnostico.php'; 
        echo '</div>';
        
        // 2. Consola de Presets y Escenarios
        echo '<div id="iot-presets">';
        include 'dashboard/IoT/componentes/consola_presets.php'; 
        echo '</div>';

        // 3. Barra de Estado Maestro (LEDs rápidos)
        echo '<div id="iot-barra-estado">';
        include 'dashboard/IoT/componentes/barra_estado_maestro.php';
        echo '</div>';

        // 4. Parrilla Principal (Sensores y Actuadores)
        echo '<div id="iot-cuadricula">';
        include 'dashboard/IoT/componentes/cuadricula_dispositivos.php';
        echo '</div>';
    ?>

</div>

<?php require_once 'includes/footer.php'; ?>