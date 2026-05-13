<?php
/**
 * header.php - Cabecera del Contenido
 * Migas de pan, Título y Botones de acción.
 */
?>

<!-- Migas de pan -->
<div class="breadcrumbs">
    <?php if ($cliente_id_seleccionado): ?>
        <a href="formularios/formulario_usuario.php?id=<?= $cliente_id_seleccionado ?>" class="account-breadcrumb-btn">👤 Mi Cuenta</a>
        <span class="breadcrumb-separator">/</span>
    <?php endif; ?>
    <a href="dashboard.php?reset_ocultos=1<?= $cliente_id_seleccionado ? '&cliente_id=' . $cliente_id_seleccionado : '' ?>">💼
        <?= htmlspecialchars($titulo_seccion ?? $arbol['nombre_empresa']) ?></a>
    <?php if ($loc_seleccionada): ?>
        <span>/</span>
        <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?><?= $url_query_cliente ?>">
            <?= htmlspecialchars($loc_seleccionada['municipio']) ?>
        </a>
    <?php endif; ?>
    <?php if ($parc_seleccionada): ?>
        <span>/</span>
        <a href="dashboard.php?localidad_cp=<?= urlencode($loc_seleccionada['codigo_postal']) ?>&parcela_id=<?= $parc_seleccionada['parcela_id'] ?><?= $url_query_cliente ?>">
            Parcela <?= htmlspecialchars($parc_seleccionada['ref_catastral']) ?>
        </a>
    <?php endif; ?>
</div>

<!-- Título y Acciones -->
<div style="margin-bottom: 2.5rem;">
    <!-- Fila 1: Título -->
    <div style="margin-bottom: 1.5rem;">
        <h1 class="dashboard-title" style="margin-bottom: 0.5rem; font-size: 2.2rem;">
            <?php
            if ($vista_actual === 'selector_cliente') {
                echo ($_SESSION['user_rol'] === 'root') ? "Control Global de Infraestructura" : "Lista de Agricultores";
            } elseif ($vista_actual === 'gestion_localidades') {
                echo "Catálogo de Localidades";
            } elseif ($vista_actual === 'gestion_cultivos') {
                echo "Catálogo de Cultivos";
            } elseif ($vista_actual === 'gestion_parcelas_total') {
                echo "Mis Parcelas";
            } elseif ($vista_actual === 'gestion_invernaderos_total') {
                echo "Mis Invernaderos";
            } elseif ($vista_actual === 'jornadas_resumen') {
                echo "Gestión de Jornadas Laborales";
            } elseif ($vista_actual === 'localidades')
                echo "Tus Zonas Geográficas";
            elseif ($vista_actual === 'parcelas')
                echo "Parcelas en " . htmlspecialchars($loc_seleccionada['municipio'] ?? '');
            else {
                $dir_limpia = explode(' - ', $parc_seleccionada['direccion'] ?? '')[0];
                echo "Invernaderos en " . htmlspecialchars($loc_seleccionada['municipio'] ?? '') . " — " . htmlspecialchars($dir_limpia);
                
                if ($es_admin && isset($parc_seleccionada['parcela_id'])) {
                    echo ' <a href="dashboard.php?confirmar_borrar_parc=1&id='.$parc_seleccionada['parcela_id'].'" style="font-size: 1.2rem; margin-left: 10px; text-decoration: none; opacity: 0.6; transition: opacity 0.2s;" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'0.6\'" title="Archivar Parcela">🗑️</a>';
                }
            }
            ?>
        </h1>
        <p class="dashboard-subtitle" style="font-size: 1rem; opacity: 0.8;">
            <?php
            if ($vista_actual === 'selector_cliente') {
                echo ($_SESSION['user_rol'] === 'root') ? "Acceso total a clientes y administradores del sistema." : "Selecciona un cliente para supervisar su actividad.";
            } elseif ($vista_actual === 'gestion_localidades') {
                echo "Supervisa y edita el listado global de municipios y provincias del sistema.";
            } elseif ($vista_actual === 'gestion_cultivos') {
                echo "Gestiona el catálogo de especies y variedades disponibles para plantación.";
            } elseif ($vista_actual === 'gestion_parcelas_total') {
                echo "Listado completo de todas tus fincas registradas en el sistema.";
            } elseif ($vista_actual === 'gestion_invernaderos_total') {
                echo "Inventario maestro de tus estructuras de protección y cultivos asociados.";
            } elseif ($vista_actual === 'jornadas_resumen') {
                echo "Supervisión de operatividad y turnos por unidad de producción.";
            } else
                echo "Selecciona un elemento para navegar por tu infraestructura.";
            ?>
        </p>
    </div>

    <!-- CSS Base: Variables globales, reset y componentes compartidos -->
    <link rel="stylesheet" href="<?= $base_url ?>/css/style.css">
    <link rel="stylesheet" href="<?= $base_url ?>/css/navbar.css?v=<?= time() ?>">

    <!-- Fila 2: Barra de Navegación Modular (V6.8) -->
    <?php require_once 'navbar.php'; ?>
</div>
