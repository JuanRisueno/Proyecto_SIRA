<?php
/**
 * dashboard_handlers.php - Procesamiento de Acciones GET/POST del Dashboard
 */

// 1. Manejador de Status de Cliente (Admin)
if (isset($_GET['accion']) && isset($_GET['id']) && in_array($_GET['accion'], ['ocultar', 'activar'])) {
    $id_user = (int) $_GET['id'];
    $nuevo_status = ($_GET['accion'] === 'activar');
    setClienteStatus($token, $id_user, $nuevo_status);
    
    // Anclado para UX
    header("Location: dashboard.php?highlight_id=$id_user#cli-card-$id_user");
    exit();
}

// [NUEVO V14.0] Manejador de Reseteo de Jornadas (Reset Maestro)
if (isset($_GET['accion']) && $_GET['accion'] === 'reset_jornada_maestra' && isset($_GET['cliente_id'])) {
    $cid = (int)$_GET['cliente_id'];
    
    // Seguridad: Solo admin o el propio dueño
    if (!$es_admin && $_SESSION['cliente_id'] != $cid) {
        header("Location: dashboard.php?error=no_autorizado");
        exit();
    }
    
    $res = sira_api_call($token, "/api/v1/config/jornada/cliente/$cid/reset", "DELETE");
    
    if ($res['code'] == 200) {
        header("Location: dashboard.php?seccion=jornadas_resumen&cliente_id=$cid&msg=reset_jornada_ok");
    } else {
        header("Location: dashboard.php?seccion=jornadas_resumen&cliente_id=$cid&error=error_al_resetear");
    }
    exit();
}

// [NUEVO V14.5] Manejador de Configuración de Redes Sociales (Zero-JS)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] === 'update_social_links') {
    if (!$es_admin) {
        header("Location: dashboard.php?error=no_autorizado");
        exit();
    }
    
    $nuevos_links = [
        "twitter" => trim($_POST['twitter'] ?? ""),
        "instagram" => trim($_POST['instagram'] ?? ""),
        "facebook" => trim($_POST['facebook'] ?? ""),
        "whatsapp" => trim($_POST['whatsapp'] ?? ""),
        "email_soporte" => trim($_POST['email_soporte'] ?? "sira@sira.es")
    ];

    $res = guardarConfiguracionSocial($token, $nuevos_links);
    
    if ($res['code'] == 200) {
        header("Location: dashboard.php?msg=social_actualizado");
    } else {
        header("Location: dashboard.php?error=error_guardar_social");
    }
    exit();
}


// 3. Manejador de Status de Cultivo
if (isset($_GET['accion']) && $_GET['accion'] === 'status_cultivo' && isset($_GET['id'])) {
    $activa = ($_GET['estado'] === 'activar');
    setCultivoStatus($token, $_GET['id'], $activa);
    header("Location: dashboard.php?seccion=cultivos&highlight_id=" . $_GET['id'] . "#cultivo-card-" . $_GET['id']);
    exit();
}

// 4. Manejador de Borrado de Parcelas (ADMIN ONLY)
if (isset($_GET['accion']) && $_GET['accion'] === 'borrar_parc' && isset($_GET['id'])) {
    if (!$es_admin) {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
    
    if (borrarParcela($token, $_GET['id'])) {

        $redir = "dashboard.php?msg=parcela_archivada";
        if (isset($_GET['localidad_cp'])) $redir .= "&localidad_cp=" . $_GET['localidad_cp'];
        if (isset($_GET['cliente_id'])) $redir .= "&cliente_id=" . $_GET['cliente_id'];
        
        // Ancla para volver al punto exacto (aunque ya no esté la parcela, servirá para el contexto)
        $redir .= "#parc-card-" . $_GET['id'];
        
        header("Location: $redir");
    } else {
        header("Location: dashboard.php?error=borrado_fallido");
    }
    exit();
}

// 5. Manejador de Borrado de Invernaderos (ADMIN ONLY)
if (isset($_GET['accion']) && $_GET['accion'] === 'borrar_inv' && isset($_GET['id'])) {
    if (!$es_admin) {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
    if (borrarInvernadero($token, $_GET['id'])) {
        $redir = "dashboard.php?msg=invernadero_archivado";
        if (isset($_GET['localidad_cp'])) $redir .= "&localidad_cp=" . $_GET['localidad_cp'];
        if (isset($_GET['parcela_id'])) $redir .= "&parcela_id=" . $_GET['parcela_id'];
        if (isset($_GET['cliente_id'])) $redir .= "&cliente_id=" . $_GET['cliente_id'];
        header("Location: $redir");
    } else {
        header("Location: dashboard.php?error=borrado_inv_fallido");
    }
    exit();
}

// 5.5. Manejador de Restauración de Assets (Invernaderos y Parcelas) - ADMIN ONLY
if (isset($_GET['accion']) && $_GET['accion'] === 'restaurar_asset' && isset($_GET['id']) && isset($_GET['target'])) {
    if (!$es_admin) {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
    $id = (int)$_GET['id'];
    $is_parc = ($_GET['target'] === 'parcela');
    
    $data = ["activa" => true];
    if (actualizarAsset($token, $is_parc, $id, $data)) {
        $redir = "dashboard.php?msg=asset_restaurado";
        if (isset($_GET['localidad_cp'])) $redir .= "&localidad_cp=" . $_GET['localidad_cp'];
        if (isset($_GET['cliente_id'])) $redir .= "&cliente_id=" . $_GET['cliente_id'];
        if (isset($_GET['seccion'])) $redir .= "&seccion=" . $_GET['seccion'];
        
        header("Location: $redir#".($is_parc ? "parc" : "inv")."-card-$id");
    } else {
        header("Location: dashboard.php?error=restauracion_fallida");
    }
    exit();
}

// 5.7. Manejador de Restauración Total (Parcela + Todos sus Invernaderos) - ADMIN ONLY
if (isset($_GET['accion']) && $_GET['accion'] === 'restaurar_parcela_total' && isset($_GET['id'])) {
    if (!$es_admin) {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
    
    $id = (int)$_GET['id'];
    
    // 1. Restaurar la Parcela
    $res_parc = actualizarAsset($token, true, $id, ["activa" => true]);
    
    // 2. Restaurar todos los invernaderos vinculados
    $res_invs = restaurarInvernaderosEnCascada($token, $id);
    
    if ($res_parc) {
        $redir = "dashboard.php?msg=restauracion_total_ok";
        if (isset($_GET['cliente_id'])) $redir .= "&cliente_id=" . $_GET['cliente_id'];
        if (isset($_GET['seccion'])) $redir .= "&seccion=" . $_GET['seccion'];
        if (isset($_GET['localidad_cp'])) $redir .= "&localidad_cp=" . $_GET['localidad_cp'];
        
        header("Location: $redir#parc-card-$id");
    } else {
        header("Location: dashboard.php?error=restauracion_fallida");
    }
    exit();
}

// 5.6. Manejador de Restauración Jerárquica (Invernadero + Parcela) - ADMIN ONLY
if (isset($_GET['accion']) && $_GET['accion'] === 'restaurar_jerarquico' && isset($_GET['inv_id']) && isset($_GET['parc_id'])) {
    if (!$es_admin) {
        header("Location: dashboard.php?error=acceso_denegado");
        exit();
    }
    $inv_id = (int)$_GET['inv_id'];
    $parc_id = (int)$_GET['parc_id'];
    
    // 1. Restaurar Parcela
    actualizarAsset($token, true, $parc_id, ["activa" => true]);
    // 2. Restaurar Invernadero
    actualizarAsset($token, false, $inv_id, ["activa" => true]);
    
    $redir = "dashboard.php?msg=restauracion_jerarquica_ok&cliente_id=" . ($_GET['cliente_id'] ?? "");
    if (isset($_GET['seccion'])) $redir .= "&seccion=" . $_GET['seccion'];
    
    header("Location: $redir#inv-card-$inv_id");
    exit();
}


// 7. Manejador de Siembra Rápida (Asignación de Cultivo)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_quick_plant'])) {
    $inv_id = (int)$_POST['invernadero_id'];
    $cultivo_id = (int)$_POST['cultivo_id'];
    
    // 1. Obtener datos actuales del invernadero
    $data = obtenerDetalleAsset($token, false, $inv_id);
    if ($data) {
        // 2. Actualizar el cultivo y la fecha de plantación (hoy)
        if ($cultivo_id > 0) {
            $data['cultivo_id'] = $cultivo_id;
            $data['fecha_plantacion'] = date('Y-m-d'); // La fecha de inicio es hoy
        } else {
            $data['cultivo_id'] = null;
            $data['fecha_plantacion'] = null; // Barbecho
        }
        actualizarAsset($token, false, $inv_id, $data);
    }

    $query_cliente = isset($_GET['cliente_id']) ? "&cliente_id=" . (int)$_GET['cliente_id'] : "";
    $parc_query = isset($_GET['parcela_id']) ? "&parcela_id=" . (int)$_GET['parcela_id'] : "";
    $loc_query = isset($_GET['localidad_cp']) ? "&localidad_cp=" . urlencode($_GET['localidad_cp']) : "";
    
    $seccion = (isset($_GET['seccion']) && $_GET['seccion'] === 'mis_invernaderos') ? "mis_invernaderos" : "";
    $url = "dashboard.php?msg=siembra_actualizada";
    if ($seccion) $url .= "&seccion=$seccion";
    
    // Redirigir con Ancla y Highlight
    header("Location: " . $url . $query_cliente . $parc_query . $loc_query . "&highlight_id=$inv_id#inv-card-$inv_id");
    exit();
}

// 7.1. Manejador de Modo Almacén (Reseteo + Desactivación de Jornada)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['btn_set_almacen'])) {
    $inv_id = (int)$_POST['invernadero_id'];
    
    // 1. Obtener datos actuales del invernadero
    $data = obtenerDetalleAsset($token, false, $inv_id);
    if ($data) {
        // 2. Limpiar cultivo (Modo Almacén no tiene cultivo activo)
        $data['cultivo_id'] = null;
        $data['fecha_plantacion'] = null;
        actualizarAsset($token, false, $inv_id, $data);
        
        // 3. Desactivar Jornada Laboral (Marcar como no laborable/Almacén)
        $config_almacen = [
            "es_laborable" => false,
            "heredar_de_global" => false,
            "default" => []
        ];
        sira_api_call($token, "/api/v1/config/jornada/invernadero/$inv_id", "POST", $config_almacen);
    }

    $query_cliente = isset($_GET['cliente_id']) ? "&cliente_id=" . (int)$_GET['cliente_id'] : "";
    $parc_query = isset($_GET['parcela_id']) ? "&parcela_id=" . (int)$_GET['parcela_id'] : "";
    $loc_query = isset($_GET['localidad_cp']) ? "&localidad_cp=" . urlencode($_GET['localidad_cp']) : "";

    header("Location: dashboard.php?msg=modo_almacen_ok" . $query_cliente . $parc_query . $loc_query . "&highlight_id=$inv_id#inv-card-$inv_id");
    exit();
}

// 8. Manejadores de Estado de Interfaz (Toggles)
if (isset($_GET['toggle_view']) || isset($_GET['toggle_ocultos'])) {
    if (isset($_GET['toggle_view'])) {
        $current_view = $_SESSION['dashboard_view'] ?? 'grid';
        $_SESSION['dashboard_view'] = ($current_view === 'grid') ? 'list' : 'grid';
    }
    
    if (isset($_GET['toggle_ocultos'])) {
        $_SESSION['ver_ocultos'] = !($_SESSION['ver_ocultos'] ?? false);
    }
    
    // Limpiar los parámetros de control para la redirección
    $params = $_GET;
    unset($params['toggle_view'], $params['toggle_ocultos']);
    
    $query = !empty($params) ? "?" . http_build_query($params) : "";
    header("Location: dashboard.php" . $query);
    exit();
}
