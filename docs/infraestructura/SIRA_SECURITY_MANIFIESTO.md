# Documentación de Seguridad e Infraestructura - Proyecto SIRA

Este documento detalla la arquitectura de red, la gestión de usuarios y las medidas de protección implementadas en el proyecto SIRA (Sistema Integral de Riego Automático). Como parte del Trabajo de Fin de Grado (TFG) de ASIR, se ha diseñado esta infraestructura buscando un equilibrio entre seguridad, rendimiento y facilidad de despliegue.

---

## 1. Arquitectura de Red y Perímetro

### Uso de Nginx como Proxy Inverso
Para el proyecto se ha configurado **Nginx** como el único punto de acceso externo al sistema.
- **Aislamiento de servicios**: Tanto el backend (FastAPI) como la base de datos (PostgreSQL) corren en una red privada de Docker. Solo se puede acceder a ellos a través de Nginx, lo que evita ataques directos a los puertos 8000 o 5432.
- **Configuración en AWS**: En el despliegue realizado en AWS EC2, se ha mapeado Nginx al puerto 80 para cumplir con los estándares HTTP y facilitar la configuración de los Security Groups de Amazon.

### Contenedores y Docker
- **Gestión de volúmenes**: Se han separado los datos de la base de datos y los logs de seguridad en volúmenes persistentes de Docker.
- **Seguridad en el repositorio**: El archivo `.env` y las carpetas con datos sensibles están incluidos en el `.gitignore` para no subir secretos a GitHub.

---

## 2. Gestión de Usuarios y Accesos

### Autenticación con JWT
- **Sesión en servidor**: Aunque se usa JWT para la comunicación entre el frontend y la API, se guarda el token en la sesión de PHP por seguridad. Esto ayuda a prevenir ataques de tipo XSS que podrían robar el token si estuviera en el almacenamiento local del navegador.
- **Roles de usuario**: Se han implementado tres niveles de acceso: Root, Admin y Cliente, controlados mediante los "claims" del token JWT.

### Protección de Contraseñas (Bcrypt)
- **Hashing**: Todas las contraseñas se guardan usando el algoritmo **Bcrypt con 12 rondas**.
- **Sin texto plano**: En ninguna parte del sistema, incluyendo los scripts de inicialización SQL, se guardan contraseñas legibles.

---

## 3. Control de Sesiones y Auditoría

### Historial y Rotación de Claves
Para aumentar la seguridad de las cuentas, se han añadido estas funcionalidades:
- **Registro de cambios**: Se guarda un historial (en archivos JSON fuera de la base de datos) con las últimas 5 contraseñas para evitar que se repitan.
- **Caducidad**: Las contraseñas caducan a los 90 días, obligando al usuario a cambiarlas.

### Control de Inactividad y Sesión Única
- **Evitar sesiones duplicadas**: Cada vez que alguien entra, se genera un ID de sesión único. Si se entra desde otro sitio con la misma cuenta, la sesión anterior se cierra automáticamente.
- **Tiempo de inactividad**: Aunque el token tiene una duración máxima de **12 horas** para cubrir la jornada laboral, se ha configurado un sistema que cierra la sesión si no hay actividad en 30 minutos. Esto protege las cuentas si el usuario se deja la sesión abierta por descuido.
- **Cierre de sesión**: El botón de "Cerrar sesión" borra el identificador en la base de datos de forma inmediata.

---

## 4. Seguridad en la Interfaz

### Validación y Filtrado
- **Mínimo JavaScript**: Se ha intentado usar poco JS para evitar vulnerabilidades. Solo se usa para dar feedback visual al usuario cuando crea una contraseña (comprobar que cumple los requisitos).
- **Escapado de datos**: En PHP se usa siempre `htmlspecialchars()` para evitar ataques de inyección de scripts (XSS) al mostrar datos.

---

## 5. Seguridad en el Sistema IoT

### Tokens para Sensores
Para que nadie pueda enviar datos falsos al sistema, los dispositivos IoT deben incluir un token privado en sus peticiones. Si el token no es correcto, la API rechaza los datos.

---

## Matriz de Riesgos

| Riesgo | Impacto | Medida de Mitigación |
| :--- | :--- | :--- |
| **Inyección SQL** | Crítico | Uso de SQLAlchemy (ORM) que parametriza las consultas. |
| **Fuerza Bruta** | Alto | Contraseñas complejas y hashing con Bcrypt. |
| **Fuga de datos** | Crítico | Uso de `.gitignore` para archivos de configuración. |
| **Robo de sesión** | Alto | Cierre por inactividad y almacenamiento en sesión PHP. |

---

> [!NOTE]
> Este documento resume el estado final de la seguridad del proyecto para la defensa del TFG.

**Proyecto SIRA - Documentación Técnica**  
*Última actualización: 30 de Abril de 2026 (Versión 1.0)*
