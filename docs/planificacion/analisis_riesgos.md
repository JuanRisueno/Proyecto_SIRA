# Análisis de Riesgos y Medidas de Mitigación - Proyecto SIRA

En este documento se analizan los posibles fallos técnicos que podrían afectar al funcionamiento del proyecto SIRA y las soluciones que se han implementado para evitarlos. Al ser un proyecto de fin de grado, el objetivo es asegurar que el sistema sea estable y seguro durante la presentación.

---

## 1. Riesgos en la Base de Datos e Infraestructura

### Desajuste de Horarios (Timezones)
- **Problema**: Si los contenedores de Docker usan la hora UTC y el frontend de PHP usa la hora de España, las gráficas de sensores podrían salir vacías o con errores de tiempo.
- **Solución**: Se ha configurado la variable de entorno `TZ=Europe/Madrid` en todos los servicios del archivo `docker-compose.yml`. De esta forma, todo el sistema trabaja con la misma franja horaria.

### Seguridad en la Entrada de Datos IoT
- **Problema**: Si la API que recibe los datos de los sensores no es segura, cualquier persona podría enviar datos falsos para engañar al sistema de riego.
- **Solución**: Se ha implementado un sistema de autenticación mediante un token privado. Solo las peticiones que incluyan esta clave secreta en la cabecera serán aceptadas por el servidor.

---

## 2. Riesgos en la Interfaz Web

### Pérdida de Datos por Refresco Automático
- **Problema**: Para que los datos se actualicen solos sin usar mucho JavaScript, se usa la etiqueta `<meta refresh>`. Sin embargo, si el usuario está rellenando un formulario y la página se refresca, perderá lo que ha escrito.
- **Solución**: Se ha configurado el auto-refresco para que solo funcione en las pantallas de monitorización (donde solo se ven datos). En las páginas de edición y formularios, el refresco automático está desactivado.

---

## 3. Riesgos de Rendimiento y Funcionamiento

### Saturación del Servidor
- **Problema**: Si el simulador envía datos demasiado rápido (por ejemplo, cada segundo), el servidor podría saturarse y volverse lento.
- **Solución**: Se ha limitado el intervalo de envío de datos del simulador a un mínimo de 5 segundos. Esto asegura que la base de datos pueda procesar todo sin problemas.

### Errores de Configuración en Vivo
- **Problema**: Que durante la defensa del proyecto el sistema no funcione porque algún servicio se haya quedado parado.
- **Solución**: Se ha preparado un script de arranque que levanta todos los contenedores de Docker y el simulador de forma automática, asegurando que el entorno esté listo para la demo en pocos segundos.

---

## 4. Validación de Datos por el Usuario

### Introducción de Datos Incorrectos
- **Problema**: Que un usuario introduzca valores imposibles al configurar un cultivo (por ejemplo, una temperatura de 100 grados).
- **Solución**: Se han añadido validaciones en el servidor (PHP) que comprueban que los valores introducidos están dentro de unos rangos lógicos antes de guardarlos en la base de datos.

---

**Análisis de Riesgos - SIRA**  
*Versión 1.0 Final - Abril 2026*
