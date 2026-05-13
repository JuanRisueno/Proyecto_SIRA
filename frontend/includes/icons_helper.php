<?php
/**
 * icons_helper.php - Diccionario visual de cultivos para SIRA
 * Devuelve un emoji basado en palabras clave del nombre del cultivo.
 */

function get_crop_icon($nombre) {
    if (!$nombre) return '🌱'; // Icono por defecto si es nulo

    $nombre = mb_strtolower($nombre, 'UTF-8');

    // Diccionario de mapeo (Palabra clave => Emoji)
    $iconos = [
        'tomate'    => '🍅',
        'sandia'    => '🍉',
        'sandía'    => '🍉',
        'melon'     => '🍈',
        'melón'     => '🍈',
        'pimiento'  => '🫑',
        'pepino'    => '🥒',
        'calabacin' => '🥒',
        'calabacín' => '🥒',
        'judia'     => '🫛',
        'judía'     => '🫛',
        'lechuga'   => '🥬',
        'berenjena' => '🍆',
        'fresa'     => '🍓',
        'maiz'      => '🌽',
        'maíz'      => '🌽',
        'patata'    => '🥔',
        'cebolla'   => '🧅',
        'ajo'       => '🧄',
        'uva'       => '🍇',
        'barbecho'  => '🪴'
    ];

    foreach ($iconos as $key => $emoji) {
        if (strpos($nombre, $key) !== false) {
            return $emoji;
        }
    }

    return '🌱'; // Si no encuentra nada, mantiene el brote genérico
}
