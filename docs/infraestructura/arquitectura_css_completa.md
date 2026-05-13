# Documentación del Diseño CSS - Proyecto SIRA

Este documento explica cómo se han organizado los estilos CSS del proyecto SIRA. Se ha seguido una estructura modular para que el código sea limpio, fácil de entender y de mantener.

---

## 1. Organización de los Archivos CSS

Se ha utilizado un sistema de "archivos espejo": por cada página principal en PHP, existe un archivo CSS con el mismo nombre que contiene sus estilos específicos. Esto evita que los estilos de una página afecten a otra por error.

---

## 2. Archivos Base y Globales

Se han creado una serie de archivos fundamentales que se cargan en todas las páginas:

- **variables.css**: Aquí se definen los colores, sombras y radios de los bordes que se usan en todo el proyecto. Si se quiere cambiar un color, solo hay que hacerlo aquí.
- **base.css**: Contiene el "reset" para que la web se vea igual en todos los navegadores, además de la tipografía (se han usado Inter y Roboto).
- **layout.css**: Define la estructura principal de la página, como la barra de navegación superior y el cuerpo central.
- **style.css**: Es el archivo principal que importa todos los demás en el orden correcto.

---

## 3. Componentes de la Interfaz

Para que el diseño sea coherente, se han creado módulos para los elementos comunes:

- **buttons.css**: Estilos para todos los botones del sistema, con efectos suaves cuando pasas el ratón por encima.
- **confirmations.css**: Estilos para las ventanas emergentes (modales) de confirmación.
- **menus.css**: Se han implementado los menús de opciones usando solo CSS (mediante checkboxes ocultos), evitando así el uso innecesario de JavaScript.
- **header.css**: Controla los títulos de las secciones y el sistema de navegación por "migas de pan".

---

## 4. Efectos Visuales del Clima

Una de las partes más trabajadas del proyecto es la representación visual del clima en el dashboard. Estos estilos se cargan dinámicamente según el estado de los sensores:

- **cloudy.css / rain.css**: Añaden nubes y lluvia a la interfaz usando animaciones CSS.
- **heat.css**: Aplica un tono cálido y un efecto de sol radiante cuando la temperatura es alta.
- **snow.css / sequia.css**: Representan estados de frío extremo o sequía mediante filtros de color y partículas.

Para asegurar que estos efectos no molesten al usuario, se ha configurado la propiedad `pointer-events: none`, de modo que se puede hacer clic en los botones aunque haya nubes o lluvia por encima.

---

## 5. Estándares Seguidos

- **Bordes redondeados**: Se ha usado un radio de 10px en todos los contenedores para dar un aspecto moderno.
- **Rendimiento**: Las animaciones están optimizadas para que no consuman demasiada CPU/GPU, asegurando que la web sea fluida.
- **Modo oscuro**: El sistema usa una paleta de colores oscuros (azul marino oscuro) para cansar menos la vista en entornos de trabajo.

---

**Documentación de Diseño SIRA**  
*Versión 1.0 Final - Abril 2026*
