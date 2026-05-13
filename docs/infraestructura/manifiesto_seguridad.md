# Documentación de Seguridad e Infraestructura - Proyecto SIRA

Este documento detalla la arquitectura de red, la gestión de usuarios y las medidas de protección implementadas en el proyecto SIRA (Sistema Integral de Riego Automático). Como parte del Trabajo de Fin de Grado (TFG) de ASIR, se ha diseñado esta infraestructura buscando un equilibrio entre seguridad, rendimiento y facilidad de despliegue.

---

## 1. Arquitectura de Red y Perímetro

### Uso de Nginx como Proxy Inverso y API Gateway
Para el proyecto se ha configurado **Nginx** como el único punto de acceso externo al sistema, ejerciendo funciones de enrutador inteligente y cortafuegos de aplicación inicial.
- **Aislamiento de servicios**: Tanto el backend (FastAPI) como la base de datos (PostgreSQL) corren en una red privada de Docker. Solo se puede acceder a ellos a través de Nginx, lo que evita ataques directos a los puertos 8000 o 5432.
- **Ocultación de topología (Routing)**: Nginx unifica todos los servicios bajo el mismo puerto. Enruta dinámicamente las peticiones sensibles (`/api`, `/docs`, `/redoc`) hacia FastAPI y delega el tráfico general al frontend, evitando que el usuario exterior sepa cuántos servidores o en qué puertos están corriendo internamente.
- **Prevención de Denegación de Servicio (DoS) por saturación**: Se ha implementado un control estricto de subidas mediante la directiva `client_max_body_size`, previniendo que un atacante envíe *payloads* gigantescos que colapsen la memoria del servidor o la API.
- **Conexiones IoT seguras**: Se ha habilitado explícitamente el soporte de HTTP/1.1 y *Upgrade* de WebSockets, garantizando que el flujo de datos en tiempo real de los sensores no sufra cortes por *timeouts* o cierres abruptos.
- **Abstracción del puerto host**: El archivo `docker-compose.yml` abstrae la exposición exterior, permitiendo usar el puerto 8085 en local y mapearlo al puerto HTTP estándar 80 en AWS EC2, lo que facilita enormemente la configuración de los *Security Groups* de Amazon sin modificar el servidor web interno.

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

### Entorno Simulado y Telemetría Abierta
En la versión actual del proyecto (orientada a la demostración del TFG), la capa de telemetría IoT opera en un entorno simulado. Para facilitar el uso de la herramienta de "Simulación Climática" desde el dashboard y agilizar las pruebas de carga, los endpoints de ingesta de datos (`/api/v1/iot/mediciones/`) se mantienen abiertos dentro de la red interna sin requerir tokens de autenticación por dispositivo. La implementación de API Keys individuales por sensor queda como una ampliación planificada para futuras versiones del sistema en producción física.

---

## Matriz de Riesgos

| Riesgo | Impacto | Medida de Mitigación |
| :--- | :--- | :--- |
| **Inyección SQL** | Crítico | Uso de SQLAlchemy (ORM) que parametriza las consultas. |
| **Fuerza Bruta** | Alto | Contraseñas complejas y hashing con Bcrypt. |
| **Fuga de datos** | Crítico | Uso de `.gitignore` para archivos de configuración. |
| **Robo de sesión** | Alto | Cierre por inactividad y almacenamiento en sesión PHP. |
| **Pérdida de datos** | Crítico | Backups graduales (Anual/Mensual/Diario) con rotación automática. |

---

## 6. Resiliencia y Recuperación
Para asegurar la continuidad del sistema, se ha implementado un script de **backup inteligente** (`backup_sira.sh`) automatizado mediante **Crontab**. Este sistema gestiona la rotación de copias de seguridad de forma automática, garantizando que siempre existan puntos de restauración históricos.

**Configuración de automatización:**
```bash
00 03 * * * /bin/bash /home/ubuntu/SIRA_Project/scripts/backup_sira.sh >> /home/ubuntu/sira_backups/log_cron.txt 2>&1
```

---

> [!NOTE]
> Este documento resume el estado final de la seguridad del proyecto para la defensa del TFG.

**Proyecto SIRA - Documentación Técnica**  
*Última actualización: 12 de Mayo de 2026 (Versión 1.1)*
