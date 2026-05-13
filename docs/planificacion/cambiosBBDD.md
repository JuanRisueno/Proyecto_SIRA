# Registro de Cambios en la Base de Datos - Proyecto SIRA

Este documento sirve para llevar un control de todos los cambios que se han ido haciendo en la base de datos PostgreSQL. Así se puede asegurar que el código de Python (SQLAlchemy) y las tablas de SQL están siempre sincronizados.

---

## [v1.0] - 2026-04-30 (Versión Final TFG)
### Control de Sesiones e Inactividad
- **Tabla `CLIENTE`**:
    - `[ADD]` Columna `ultima_actividad`: Para controlar cuándo fue la última vez que el usuario hizo algo.
    - `[ADD]` Columna `session_id`: Para guardar el ID de la sesión actual y evitar que se use la misma cuenta en varios sitios a la vez.
    - `[INDEX]` Se han añadido índices a estas columnas para que las consultas de quién está "online" sean más rápidas.
- **Seguridad**:
    - Se ha ajustado el tamaño del campo de contraseña para que quepan bien los hashes de bcrypt.

---

## [v0.9] - 2026-04-20
### Gestión de Cultivos Locales
- **Tabla `CULTIVO`**:
    - Se ha eliminado la conexión con APIs externas para que el sistema sea autónomo y no dependa de internet para funcionar.
    - Se ha añadido la columna `cliente_id` para que cada usuario pueda crear sus propios tipos de cultivos privados.
    - Se ha añadido un campo `activa` (booleano) para poder "borrar" cultivos sin eliminarlos realmente de la base de datos (borrado lógico).

---

## [v0.8] - 2026-04-14
### Parámetros Óptimos y Datos de Prueba
- **Tabla `PARAMETROS_OPTIMOS`**:
    - Se ha añadido el campo `ph_ideal` para llevar un control del nivel químico del agua.
    - Se han cargado los datos reales de 8 cultivos comunes (Tomate, Pimiento, Melón, etc.) para que el simulador tenga valores de referencia.

---

## [v0.7] - 2026-04-10
### Mejoras en el Diseño de Tablas
- **Borrado Lógico**: Se ha añadido el campo `activa` a las tablas de Clientes, Parcelas e Invernaderos.
- **Optimización de tipos**: Se han revisado los tipos de datos (VARCHAR vs CHAR) en campos como el CIF o el Código Postal para ahorrar espacio y mejorar el rendimiento.
- **Búsquedas**: Se ha activado la extensión `unaccent` en PostgreSQL para que el buscador ignore las tildes.

---

## [v0.6] - 2026-01-15
### Ajustes de Integridad
- Se ha revisado que todos los nombres de las columnas en Python coincidan exactamente con los de SQL para evitar errores de mapeo.
- Se han configurado las claves foráneas con `ON DELETE RESTRICT` para asegurar que no se borren datos por accidente si tienen otros elementos relacionados.

---

## [v0.5] - 2025-11-20
### Esquema Inicial del Proyecto
- Creación de las tablas principales del sistema: Clientes, Localidades, Parcelas, Invernaderos, Cultivos, Sensores y Actuadores.

---
**Registro de Cambios - SIRA**  
*Última actualización: 30 de Abril de 2026 (Versión 1.0)*
