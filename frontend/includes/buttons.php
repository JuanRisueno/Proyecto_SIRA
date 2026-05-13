<?php
/**
 * buttons.php - Sistema de Componentes Visuales SIRA (v1.0.1)
 * Centraliza la creación de botones para asegurar coherencia en diseño e iconografía.
 */

/**
 * Renderiza un botón o un enlace con estilo de botón SIRA.
 * 
 * @param string $text    Texto del botón
 * @param string $variant Tipo: 'primary', 'secondary', 'error', 'warning', 'mini', 'helper'
 * @param string $icon    Hito visual: 'plus', 'save', 'search', 'shield', 'delete', 'edit', 'cancel', 'logout', 'account', 'calendar', 'gear', 'arrow', 'home', 'eye', 'list', 'grid', 'vfx', 'random'
 * @param array  $attr    Atributos HTML: ['type', 'name', 'id', 'href', 'class', 'style', 'onclick', 'target', 'formnovalidate', 'disabled', 'size', 'value']
 * @return string HTML formateado
 */
function sira_btn($text, $variant = 'primary', $icon = null, $attr = []) {
    $type = $attr['type'] ?? 'button';
    $name = isset($attr['name']) ? ' name="'.$attr['name'].'"' : '';
    $id = isset($attr['id']) ? ' id="'.$attr['id'].'"' : '';
    $onclick = isset($attr['onclick']) ? ' onclick="'.$attr['onclick'].'"' : '';
    $class = $attr['class'] ?? '';
    $style = isset($attr['style']) ? ' style="'.$attr['style'].'"' : '';
    $href = $attr['href'] ?? null;
    $target = $attr['target'] ?? null;
    $value = isset($attr['value']) ? ' value="'.$attr['value'].'"' : '';
    $formnovalidate = isset($attr['formnovalidate']) ? ' formnovalidate' : '';
    $disabled = (isset($attr['disabled']) && $attr['disabled']) ? ' disabled' : '';
    $title = isset($attr['title']) ? ' title="'.$attr['title'].'"' : '';
    $size = isset($attr['size']) && $attr['size'] === 'sm' ? 'btn-sm' : '';

    // Mapeo de Variantes -> Clases CSS de buttons.css
    $variants = [
        'primary'   => 'btn-primary',
        'secondary' => 'btn-secondary',
        'warning'   => 'btn-warning',
        'mini'      => 'mini-btn-opt',
        'helper'    => 'btn-secondary btn-helper',
        // Variantes de Simulación IoT
        'ideal'     => 'btn-preset btn-ideal',
        'storm'     => 'btn-preset btn-tormenta',
        'heat'      => 'btn-preset btn-calor',
        'frost'     => 'btn-preset btn-helada',
        'cloudy'    => 'btn-preset btn-nublado',
        'drought'   => 'btn-preset btn-sequia',
        'random'    => 'btn-random'
    ];
    $variant_class = $variants[$variant] ?? 'btn-primary';
    
    // Si es mini, no incluimos la clase base btn-sira para evitar conflictos de padding/flex
    $base_class = ($variant === 'mini') ? '' : 'btn-sira';
    $full_class = trim("$base_class $variant_class $size $class");

    // Diccionario SIRA de Iconos SVG Premium (Lucide-based style)
    $svgs = [
        'plus'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="M12 5v14"></path></svg>',
        'save'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>',
        'search'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>',
        'shield'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>',
        'delete'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>',
        'edit'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path></svg>',
        'cancel'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>',
        'logout'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>',
        'account'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>',
        'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>',
        'gear'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>',
        'arrow'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
        'home'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'eye'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
        'list'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>',
        'grid'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>',
        'vfx'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A5 5 0 0 0 8 8c0 1.3.5 2.6 1.5 3.5.8.8 1.3 1.5 1.5 2.5"></path><path d="M9 18h6"></path><path d="M10 22h4"></path></svg>',
        'random'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor"></circle><circle cx="15.5" cy="15.5" r="1.5" fill="currentColor"></circle><circle cx="15.5" cy="8.5" r="1.5" fill="currentColor"></circle><circle cx="8.5" cy="15.5" r="1.5" fill="currentColor"></circle><circle cx="12" cy="12" r="1.5" fill="currentColor"></circle></svg>',
        'sprout'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V7"/><path d="M12 10c-3.5 0-6-2-6-6 0 4 2.5 6 6 6Z"/><path d="M12 10c3.5 0 6-2 6-6 0 4-2.5 6-6 6Z"/></svg>',
        'tomato'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/><path d="M12 5V2"/><path d="m9 4 1.5 1.5"/><path d="m15 4-1.5 1.5"/></svg>',
        'clock'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>'
    ];

    $html_icon = $icon && isset($svgs[$icon]) ? $svgs[$icon] : '';
    
    // Si el texto es vacío y es mini, es un botón de solo icono
    $html_text = ($text && $variant !== 'mini') ? '<span>'.$text.'</span>' : '';
    $final_content = $html_icon . $html_text;

    if ($href) {
        $target_attr = $target ? ' target="'.$target.'"' : '';
        return '<a href="'.$href.'"'.$id.$name.$target_attr.$title.' class="'.$full_class.'"'.$style.'>'.$final_content.'</a>';
    } else {
        return '<button type="'.$type.'"'.$id.$name.$value.$title.' class="'.$full_class.'"'.$style.$formnovalidate.$disabled.$onclick.'>'.$final_content.'</button>';
    }
}
