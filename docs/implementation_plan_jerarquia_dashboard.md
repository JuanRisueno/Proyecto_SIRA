# Plan de Mejora y Calidad Técnica - Proyecto SIRA

Este documento detalla las mejoras implementadas en la fase final del proyecto para asegurar que la aplicación cumple con los estándares de calidad exigidos en un Trabajo de Fin de Grado (TFG) de ASIR. El objetivo es tener un código limpio, modular y una interfaz fácil de usar.

---

## 1. Estructura de Navegación (Jerarquía de Datos)

Para evitar que el panel de control sea confuso cuando un cliente tiene muchos invernaderos en diferentes sitios, se ha organizado la navegación en tres niveles:
1. **Localidades**: Vista general de los municipios donde el cliente tiene tierras.
2. **Parcelas**: Listado de las fincas dentro de una localidad concreta.
3. **Invernaderos**: Acceso a los sensores y actuadores de cada nave.

Esta navegación se gestiona mediante parámetros en la URL (`GET`), permitiendo que el usuario sepa siempre dónde está gracias a un sistema de "migas de pan" (breadcrumbs) en la parte superior.

---

## 2. Modularidad del Código (Includes PHP)

Una de las mejoras más importantes a nivel de sistemas ha sido la modularización del frontend:
- Se ha extraído la cabecera (`header.php`) y el pie de página (`footer.php`) a archivos independientes.
- Todas las páginas del proyecto cargan estos archivos mediante la función `require_once`.
- Esto facilita mucho el mantenimiento: si se quiere cambiar un botón del menú, solo hay que editar un archivo y el cambio se aplica a toda la web.

---

## 3. Diseño y Usabilidad (CSS)

Se ha aplicado un diseño moderno usando solo CSS (Vanilla CSS), sin depender de librerías externas:
- **Tipografía**: Uso de fuentes legibles como Inter y Roboto.
- **Efectos visuales**: Se han añadido sombras suaves y bordes redondeados para que la interfaz se vea profesional.
- **Interactividad**: Los elementos de la interfaz reaccionan cuando el usuario pasa el ratón por encima (efecto hover), mejorando la experiencia de uso.

---

## 4. Tolerancia a Fallos (Resiliencia)

Como administrador de sistemas, es fundamental que la aplicación sepa qué hacer si un servicio falla:
- Se ha programado el frontend para que, si la API del backend no responde (por ejemplo, si el contenedor está parado), el usuario vea un mensaje de error claro en lugar de una página en blanco o un código de error de programación.
- Esto demuestra que el sistema está preparado para situaciones de error en el servidor.

---

## 5. Documentación de la API (Swagger)

Aprovechando las capacidades de FastAPI, se ha configurado la documentación automática de la API:
- Cada endpoint tiene su descripción y etiquetas correspondientes.
- Esto permite que cualquier otro desarrollador o el propio tribunal pueda probar la API de forma independiente al frontend.

---

**Plan de Calidad - SIRA**  
*Versión 1.0 Final - Abril 2026*
