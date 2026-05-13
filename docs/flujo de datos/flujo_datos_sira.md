# Flujo de Datos en el Backend - Proyecto SIRA

En este documento se explica cómo viaja la información dentro del servidor de SIRA (la API REST). Se ha diseñado esta arquitectura siguiendo los estándares de desarrollo con FastAPI para separar claramente las responsabilidades de cada parte del código.

---

## 1. Componentes del Backend

- **Cliente**: Es quien hace la petición (el navegador web, un dispositivo IoT o una herramienta de pruebas como Postman).
- **Routers**: Son los archivos que definen las rutas de la API (endpoints). Reciben las peticiones HTTP y controlan la seguridad.
- **Schemas (Pydantic)**: Se encargan de validar que los datos que llegan (en formato JSON) son correctos y de transformar los objetos del servidor a JSON para enviarlos de vuelta.
- **CRUD**: Contiene las funciones que realizan las operaciones de lectura y escritura en la base de datos.
- **Modelos (SQLAlchemy)**: Definen cómo son las tablas en la base de datos dentro del código Python.
- **Base de Datos**: El almacenamiento final en PostgreSQL.

---

## 2. Proceso de Escritura (Crear o Modificar datos)

Cuando un usuario quiere añadir algo nuevo (por ejemplo, una parcela), los datos siguen este camino:

1. **Recepción**: La API recibe los datos en un formato JSON.
2. **Validación**: El sistema comprueba mediante los "Schemas" que los datos son válidos (por ejemplo, que el código postal tenga 5 dígitos). Si algo está mal, devuelve un error automáticamente.
3. **Lógica de Negocio**: Si los datos son correctos, se pasan a las funciones del archivo CRUD.
4. **Mapeo a Base de Datos**: El CRUD crea un objeto compatible con la base de datos usando los "Modelos".
5. **Guardado**: Se abre una transacción en la base de datos, se insertan los datos y se confirma el cambio (commit).
6. **Respuesta**: Una vez guardado, el sistema devuelve un mensaje de confirmación al usuario con los datos que se han creado.

---

## 3. Proceso de Lectura (Consultar datos)

Cuando el panel de control pide ver la lista de invernaderos, el flujo es el siguiente:

1. **Petición**: El cliente solicita la información mediante una petición GET.
2. **Consulta**: El servidor traduce esa petición en una consulta SQL optimizada.
3. **Extracción**: La base de datos devuelve las filas correspondientes.
4. **Conversión**: El sistema convierte esas filas en objetos de Python que el programa puede manejar.
5. **Filtrado y Envío**: Antes de enviar los datos al navegador, pasan por los "Schemas" para asegurar que solo se envía la información necesaria y en el formato JSON correcto.

---

## 4. Resumen de Funciones por Archivo

| Archivo | Función | Responsabilidad |
|---------|-------------|----------------|
| `models.py` | Estructura de Datos | Define las tablas, columnas y relaciones de la base de datos. |
| `schemas.py` | Validación | Controla qué datos entran y salen del sistema en formato JSON. |
| `crud.py` | Operaciones | Realiza las acciones de guardar, leer, editar o borrar datos. |
| `main.py` | Controlador | Es el punto de entrada que organiza las llamadas entre los demás archivos. |

---
**Documentación Técnica - Flujo de Datos SIRA**  
*Versión 1.0 Final - Abril 2026*