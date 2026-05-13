### Planificación y Línea Temporal - Proyecto SIRA (Versión 1.0 Final)

**Equipo de trabajo y Roles:**
* **Juan:** Programación Backend, Servidores, Seguridad y API.
* **Alfonso:** Base de Datos (SQL), Documentación técnica y Estilos CSS.
* **Jorge:** Coordinación, Diseño de Hardware y Desarrollo del Frontend.

---

#### FASE I: INFRAESTRUCTURA Y BASE DE DATOS (COMPLETADA)
*Montaje del entorno de trabajo, contenedores y diseño de la base de datos.*

* **T.0 Configuración Docker:** Creación del archivo `docker-compose.yml` para los servicios de API, Base de Datos y Nginx. **(Resp: Juan)**
* **T.1 Entorno de desarrollo:** Configuración del servidor Ubuntu y repositorio en GitHub. **(Resp: Juan)**
* **T.2 Diseño de datos:** Creación del diagrama Entidad-Relación. **(Resp: Jorge)**
* **T.3 Scripts SQL:** Creación de las tablas y relaciones iniciales (`10-schema.sql`). **(Resp: Alfonso)**
* **T.4 Mantenimiento:** Configuración de pruebas de estado (Healthchecks) para los contenedores. **(Resp: Juan)**
* **T.5 Documentación inicial:** Guías sobre backups, configuración del servidor y Nginx. **(Resp: Juan)**

#### FASE II: DESARROLLO DEL BACKEND (COMPLETADA)
*Programación de la API, funciones básicas y carga de datos de prueba.*

* **T.6 Modelos SQLAlchemy:** Mapeo de las tablas de SQL a Python. **(Resp: Juan)**
* **T.7 Validaciones:** Uso de Pydantic para asegurar que los datos que entran son correctos. **(Resp: Juan)**
* **T.8 Operaciones CRUD:** Programación de las funciones de lectura y escritura en la base de datos. **(Resp: Juan)**
* **T.9 Seguridad:** Implementación del cifrado de contraseñas con la librería bcrypt. **(Resp: Juan)**
* **T.10 Datos de prueba:** Carga de clientes y datos de invernaderos iniciales (`20-data.sql`). **(Resp: Alfonso)**
* **T.11 Roles de usuario:** Configuración de los niveles de acceso (Root, Admin, Cliente). **(Resp: Jorge)**
* **T.12 Rutas de la API:** Creación de los endpoints para gestionar clientes y parcelas. **(Resp: Juan)**
* **T.13 Documentación API:** Configuración de Swagger para probar la API. **(Resp: Juan)**

**PUNTO DE CONTROL 1:** Servidor y Base de Datos funcionando correctamente. (SUPERADO)

#### FASE III: INTERFAZ WEB Y AUTENTICACIÓN (COMPLETADA)
*Desarrollo del panel visual y sistema de acceso seguro.*

* **T.14 Ajustes del Core:** Corrección de errores en los esquemas de datos y peticiones HTTP. **(Resp: Juan)**
* **T.15 Login con JWT:** Implementación de acceso mediante CIF y tokens de seguridad. **(Resp: Juan)**
* **T.16 Estructura Frontend (PHP):** Creación de las páginas principales en PHP. **(Resp: Jorge)**
* **T.17 Diseño visual:** Creación de las hojas de estilo CSS para el panel. **(Resp: Jorge/Alfonso)**
* **T.18 Panel de control:** Programación del Dashboard para navegar por Localidades, Parcelas e Invernaderos de forma dinámica. **(Resp: Juan)**

**PUNTO DE CONTROL 2:** Interfaz web funcional y conexión segura con la API. (SUPERADO)

#### FASE IV: SIMULACIÓN DE SENSORES Y CULTIVOS (COMPLETADA)
*Lógica de control climático y telemetría simulada por software.*

* **T.19 Datos de Cultivos:** Creación de tablas con los parámetros de temperatura y humedad ideales. **(Resp: Juan)**
* **T.20 Adaptación UI:** Modificación del panel para mostrar los datos de los cultivos en tiempo real. **(Resp: Jorge/Alfonso)**
* **T.21 Escenarios climáticos:** Creación de perfiles (Calor, Lluvia, etc.) para probar el sistema. **(Resp: Jorge)**
* **T.22 Lógica de Actuadores:** Programación del backend que decide si abrir ventanas o regar según los sensores. **(Resp: Juan)**
* **T.23 Generador de eventos:** Botón para crear cambios climáticos aleatorios y ver la respuesta del sistema. **(Resp: Juan)**
* **T.24 Efectos visuales:** Añadido de animaciones CSS para representar el clima (lluvia, sol). **(Resp: Jorge/Alfonso)**

**PUNTO DE CONTROL 3:** Sistema de control y simulación validado. (SUPERADO)

#### FASE V: SEGURIDAD AVANZADA (COMPLETADA)
*Refuerzo de las protecciones del sistema y auditoría final.*

* **T.25 Cifrado total:** Revisión de que todos los usuarios tengan contraseñas en hash. **(Resp: Juan)**
* **T.26 Control de sesiones:** Implementación de caducidad de claves y control de historial (no repetir últimas 5). **(Resp: Juan)**
* **T.26.5 Almacenamiento seguro:** Uso de archivos JSON para guardar logs de seguridad fuera de Git. **(Resp: Juan)**
* **T.27 Informe final:** Pruebas de seguridad y redacción de la guía de infraestructura. **(Resp: Jorge/Juan)**
* **T.28 Optimización UI:** Mejora del feedback al usuario en los formularios de cambio de clave. **(Resp: Jorge/Alfonso)**

**PUNTO DE CONTROL 4:** Sistema protegido y listo para la defensa del proyecto. (SUPERADO)

#### FASE VI: DESPLIEGUE EN AWS Y CIERRE (COMPLETADA)
*Subida del proyecto a la nube y pruebas finales de funcionamiento.*

* **T.29 Servidor en AWS:** Despliegue de la instancia EC2 y configuración de red (Security Groups). **(Resp: Jorge/Juan)**
* **T.30 Despliegue con Docker:** Migración de los contenedores al servidor de Amazon. **(Resp: Jorge/Juan)**
* **T.31 Fortificación Cloud:** Aplicación de reglas de acceso de Amazon para proteger el servidor. **(Resp: Juan)**
* **T.32 Pruebas de estrés y defensa:** Simulacros de fallos en el servidor y ensayos para la presentación. **(Resp: Jorge/Alfonso/Juan)**
* **T.33 Documentación:** Redacción final de la memoria del proyecto y guías técnicas. **(Resp: Alfonso)**

**PUNTO DE CONTROL 5:** ¡Proyecto terminado y funcionando en la nube! (SUPERADO)
