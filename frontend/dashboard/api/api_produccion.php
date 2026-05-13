<?php
/**
 * api_produccion.php - Métodos para Invernaderos y Cultivos
 */

require_once 'api_helper.php';

function borrarInvernadero($token, $id) {
    $res = sira_api_call($token, "/api/v1/invernaderos/$id", "DELETE");
    return ($res['code'] == 204);
}

function listarTodosLosCultivos($token, $q = null, $ver_inactivos = false) {
    $endpoint = "/api/v1/cultivos/";
    $params = [];
    if ($q) $params['q'] = $q;
    if ($ver_inactivos) $params['ver_inactivos'] = 'true';
    
    if (!empty($params)) {
        $endpoint .= "?" . http_build_query($params);
    }

    $res = sira_api_call($token, $endpoint);
    return ($res['code'] == 200) ? $res['data'] : [];
}

function listarTodosLosInvernaderosDelCliente($token, $cliente_id) {
    $res = sira_api_call($token, "/api/v1/invernaderos/cliente/" . $cliente_id);
    return ($res['code'] == 200) ? $res['data'] : [];
}

function setCultivoStatus($token, $id, $activa) {
    $status_str = $activa ? "true" : "false";
    $res = sira_api_call($token, "/api/v1/cultivos/$id/status?activa=$status_str", 'PATCH');
    return ($res['code'] == 200);
}

function obtenerDetalleAsset($token, $is_parc, $id) {
    $endpoint = $is_parc ? "/api/v1/parcelas/$id" : "/api/v1/invernaderos/$id";
    $res = sira_api_call($token, $endpoint);
    return ($res['code'] == 200) ? $res['data'] : null;
}

function actualizarAsset($token, $is_parc, $id, $data) {
    $endpoint = $is_parc ? "/api/v1/parcelas/$id" : "/api/v1/invernaderos/$id";
    $res = sira_api_call($token, $endpoint, "PUT", $data);
    return ($res['code'] == 200 || $res['code'] == 204);
}
