# Guía de Estándares y Desarrollo - Proyecto SIRA

Este documento sirve como guía para asegurar que el desarrollo de SIRA (Sistema Integral de Riego Automático) sea coherente tanto en la parte del backend como en el frontend. Aquí se detallan las reglas de diseño y programación seguidas para el proyecto de fin de grado.

---

## 1. Diseño y Estética del Frontend

Para el diseño de la interfaz, se ha buscado un estilo limpio y profesional que facilite el uso al agricultor.

### Reglas de Diseño
- **Bordes redondeados**: Se usa un radio de 10px para los contenedores principales y 4px para botones e inputs, para que la interfaz se vea moderna pero sencilla.
- **Variables CSS**: No se escriben valores fijos (como 20px) directamente en los archivos de los módulos. Todo debe ir referenciado a las variables definidas en `modules/variables.css`.
- **Colores**: Se ha optado por un tema oscuro con fondos en azul marino oscuro y detalles en verde esmeralda para los botones de acción y confirmación.
- **Transiciones**: Para que la navegación no sea brusca, se han añadido efectos de transición suaves de 0.3s en los botones y menús.

---

## 2. Arquitectura del Sistema

### Backend (Python y FastAPI)
- **Base de datos**: Uso SQLAlchemy para gestionar la base de datos SQL y Pydantic para validar que los datos que llegan a la API son correctos.
- **Seguridad**: El acceso se gestiona mediante tokens JWT. Se han configurado tres roles: Root (para configuración total), Admin (gestión de clientes) y Cliente (uso del dashboard).
- **Configuración segura**: Las claves y contraseñas de la base de datos se guardan en un archivo `.env` que nunca se sube al repositorio.

### Frontend (PHP y CSS)
- **Separación de código**: El código PHP solo se encarga de la lógica y los datos. Todo lo que sea diseño debe ir en archivos CSS externos.
- **Estructura modular**: Por cada página PHP, se ha creado un archivo CSS con el mismo nombre en la carpeta de módulos para tenerlo todo organizado.
- **Uso mínimo de JavaScript**: Se ha priorizado el uso de PHP y CSS para que la aplicación sea más ligera y segura. Solo se usa JS cuando es estrictamente necesario para validaciones en el navegador.

---

## 3. Reglas de Programación

- **No repetir código (DRY)**: Si un diseño o una función se usa en varios sitios, se convierte en un componente o una variable global.
- **Uso de componentes**: Elementos como la barra de navegación o el pie de página están en archivos separados (`includes/`) para poder reutilizarlos fácilmente en todas las páginas.
- **Borrado lógico**: Cuando un cliente o invernadero se "elimina", en realidad solo se marca como inactivo en la base de datos. De esta forma no perdemos los datos históricos de los sensores.
- **Código limpio**: Se ha intentado comentar las partes más complejas del código explicando el porqué de cada decisión técnica. No se deja código comentado ni basura en el proyecto final.

---

## 4. Organización del Proyecto

- `backend/app/routers/`: Rutas de la API organizadas por funciones.
- `frontend/dashboard/vistas/`: Páginas principales del panel de control.
- `frontend/css/modules/`: Archivos de estilo organizados por componentes.
- `docs/`: Documentación del proyecto, planificación y manuales.

---

**Proyecto SIRA - Versión 1.0 Final**  
*Fecha: 30 de Abril de 2026*
