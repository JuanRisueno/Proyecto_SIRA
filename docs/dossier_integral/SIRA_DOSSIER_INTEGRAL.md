# Proyecto SIRA: Sistema Integral de Riego Automático

**Documentación Final del Proyecto (Versión 1.0 Final)**

<style>
code {
    color: #000000 !important;
    background: transparent !important;
    font-weight: bold !important;
    font-family: inherit !important;
    font-size: 1em !important;
}
</style>

<br><br><br>

| | |
|---|---|
| **Proyecto** | Trabajo de Fin de Grado — ASIR |
| **Especialidad** | Administración de Sistemas Informáticos en Red |
| **Centro** | IES / Centro de Formación Profesional |
| **Fecha** | Mayo 2026 |
| **Versión** | 1.0 Final |

<br>

**Equipo de Desarrollo:**

| Integrante | Rol | Áreas de Responsabilidad |
|---|---|---|
| **Juan Risueño** | Backend Lead / DevOps | Arquitectura de sistemas, API FastAPI, protocolo Iron Fortress, despliegue AWS, Docker/Nginx |
| **Jorge Pedro López** | Coordinador / Frontend | Coordinación del proyecto, base de datos (SQL), desarrollo PHP, hardware IoT, efectos visuales CSS |
| **Alfonso Navarro** | CSS / Docs | Datos SQL y normalización, arquitectura CSS, documentación técnica del TFG |
 
<br><br>

---

<div style="page-break-after: always;"></div>

## Índice de Contenidos

1. Introducción y Marco Conceptual
2. Análisis de Requisitos y Caso de Uso Real
3. Modelado de Datos y Base de Datos
4. Arquitectura de Sistemas e Infraestructura
5. Arquitectura de Software: Backend (FastAPI)
6. Arquitectura de Software: Frontend (PHP)
7. Identidad Visual y Design System (SIRA UX)
8. Lógica de Dominio: Sensores, Actuadores y Cultivos
9. Protocolo de Seguridad "Iron Fortress"
10. Despliegue en Producción (AWS)
11. Análisis de Riesgos y Mitigaciones
12. Planificación y Línea Temporal
13. Auditoría Final y Conclusiones
14. Autoría y Roles del Proyecto

<div style="page-break-after: always;"></div>

## 1. Introducción y Marco Conceptual

### 1.1 Visión del Proyecto

SIRA (Sistema Integral de Riego Automático) es una plataforma tecnológica diseñada para la optimización del riego y la monitorización climática en explotaciones agrícolas bajo invernadero. El sistema permite la gestión centralizada de múltiples fincas, parcelas e invernaderos desde un único panel de control web, automatizando la toma de decisiones a partir de los datos captados por sensores IoT en tiempo real.

El proyecto se enmarca en el contexto de la agricultura de precisión en las provincias de Almería y Murcia, donde los cultivos en invernadero representan una actividad económica de primer orden que exige un uso eficiente del agua y un control climático constante.

### 1.2 Filosofía de Desarrollo

El proyecto se rige por dos principios arquitectónicos fundamentales:

- **Zero-JS Policy**: El uso de JavaScript se limita al mínimo estrictamente necesario. Toda la lógica de negocio, validación crítica y seguridad reside en el servidor (Python/PHP). Esta decisión elimina una superficie de ataque significativa y garantiza que el sistema funcione en cualquier navegador.
- **Server-Side Rendering (SSR)**: El frontend es generado íntegramente en el servidor por PHP. El navegador recibe HTML/CSS listo para mostrar, sin dependencias de frameworks de JavaScript.
- **Soberanía del Servidor**: Separación estricta de responsabilidades entre la capa de presentación (Frontend PHP), el núcleo de servicios (API FastAPI) y la persistencia de datos (PostgreSQL).

### 1.3 Contexto Tecnológico

El sistema se ha diseñado y probado en el contexto de la defensa de un TFG de ASIR. Dado que no se dispone de hardware físico (sensores reales), se ha implementado un simulador software (`simulador.py`) que inyecta datos sintéticos con variaciones realistas en la base de datos, permitiendo demostrar toda la lógica de automatización del sistema.

<div style="page-break-after: always;"></div>

## 2. Análisis de Requisitos y Caso de Uso Real

### 2.1 Perfil del Cliente Objetivo

SIRA ha sido diseñado teniendo en mente al agricultor profesional con explotaciones de mediano tamaño. El caso de uso de referencia utilizado durante el desarrollo es el de **"Invernaderos El Sol de Almería S.L."**, empresa ficticia gestionada por Antonio, propietario de dos fincas:

| Finca | Ubicación | Invernaderos | Cultivos |
|---|---|---|---|
| "La Finca Grande" | Níjar, Almería (CP 04100) | Nave 1 (100x50m), Nave 2 (100x50m), Nave 3 (120x50m), Nave 4 (80x40m) | Tomate Pera (N1, N2), Pimiento California (N3), Calabacín (N4) |
| "Los Pinos" | Águilas, Murcia (CP 30880) | Nave A (90x40m), Nave B (90x40m) | Tomate Cherry (A y B) |

### 2.2 Requisitos Funcionales Clave

- Registro y gestión multi-tenant de clientes, parcelas e invernaderos.
- Monitorización en tiempo real de sensores climáticos (temperatura, humedad, lluvia, viento, radiación solar).
- Automatización de actuadores (riego, ventanas, iluminación LED, extractores, calefacción) en función de umbrales por cultivo.
- Panel de control web con representación gráfica de datos de sensores (SSR con SVG).
- Sistema de roles: Root, Administrador, Cliente.
- Auditoría de seguridad: gestión de sesiones, historial de contraseñas, caducidad de credenciales.

### 2.3 Requisitos No Funcionales

- **Portabilidad**: El sistema debe poder desplegarse en local y en la nube (AWS) sin modificar el código fuente.
- **Seguridad**: Ninguna contraseña se almacenará en texto plano. Toda comunicación interna se validará mediante JWT.
- **Eficiencia**: El sistema debe funcionar en hardware modesto (instancia EC2 t3.small con 2 GB de RAM).
- **Independencia**: Sin dependencias de APIs externas. La base de conocimientos agronómicos es propia y local.

<div style="page-break-after: always;"></div>

## 3. Modelado de Datos y Base de Datos

### 3.1 Tecnología y Justificación

Se utiliza **PostgreSQL** como motor de base de datos relacional. La elección se justifica por su robustez, soporte nativo de tipos de datos avanzados (DECIMAL, TIMESTAMPTZ), y su excelente integración con SQLAlchemy (ORM del backend Python). Los datos de los sensores incluyen marcas de tiempo con zona horaria explícita (`TIMESTAMPTZ`) para evitar desajustes entre el horario UTC de los contenedores y la zona horaria local (Europe/Madrid).

### 3.2 Principios de Normalización

El esquema está normalizado hasta la **3FN (Tercera Forma Normal)**:
- No existen dependencias transitivas entre atributos no clave.
- Todas las relaciones son **1:N** (Uno a Muchos). Se ha evitado deliberadamente el uso de tablas pivote N:M para simplificar las consultas SQL en el contexto del TFG.
- El **borrado lógico** (campo `activa BOOLEAN`) se aplica en todas las entidades principales, preservando el historial de datos sin eliminar registros físicamente.

### 3.3 Diagrama Entidad-Relación (Simplificado)

<div align="center">
<svg width="680" height="420" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_er" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- CLIENTE -->
  <rect x="10" y="170" width="110" height="50" rx="6" fill="#3498db" stroke="#2c3e50" stroke-width="2"/>
  <text x="65" y="191" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">CLIENTE</text>
  <text x="65" y="208" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">CIF (PK), nombre</text>
  <!-- PARCELA -->
  <rect x="200" y="90" width="120" height="50" rx="6" fill="#9b59b6" stroke="#2c3e50" stroke-width="2"/>
  <text x="260" y="111" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">PARCELA</text>
  <text x="260" y="128" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">ref_catastral, dir.</text>
  <!-- LOCALIDAD -->
  <rect x="200" y="250" width="120" height="50" rx="6" fill="#95a5a6" stroke="#2c3e50" stroke-width="2"/>
  <text x="260" y="271" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">LOCALIDAD</text>
  <text x="260" y="288" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">CP, municipio, prov.</text>
  <!-- INVERNADERO -->
  <rect x="390" y="170" width="120" height="50" rx="6" fill="#e67e22" stroke="#2c3e50" stroke-width="2"/>
  <text x="450" y="191" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">INVERNADERO</text>
  <text x="450" y="208" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">nombre, m², cultivo</text>
  <!-- CULTIVO -->
  <rect x="390" y="30" width="120" height="50" rx="6" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="450" y="51" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">CULTIVO</text>
  <text x="450" y="68" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">nombre_cultivo</text>
  <!-- SENSOR -->
  <rect x="570" y="90" width="100" height="50" rx="6" fill="#e74c3c" stroke="#2c3e50" stroke-width="2"/>
  <text x="620" y="111" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">SENSOR</text>
  <text x="620" y="128" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">tipo, estado</text>
  <!-- ACTUADOR -->
  <rect x="570" y="260" width="100" height="50" rx="6" fill="#c0392b" stroke="#2c3e50" stroke-width="2"/>
  <text x="620" y="281" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">ACTUADOR</text>
  <text x="620" y="298" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">tipo, estado</text>
  <!-- MEDICION -->
  <rect x="570" y="170" width="100" height="50" rx="6" fill="#f39c12" stroke="#2c3e50" stroke-width="2"/>
  <text x="620" y="191" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">MEDICION</text>
  <text x="620" y="208" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">valor, timestamp</text>
  <!-- Arrows -->
  <line x1="120" y1="195" x2="200" y2="130" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <text x="153" y="153" font-family="sans-serif" font-size="10" fill="#7f8c8d">1:N</text>
  <line x1="120" y1="210" x2="200" y2="270" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <line x1="320" y1="115" x2="390" y2="190" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <text x="345" y="145" font-family="sans-serif" font-size="10" fill="#7f8c8d">1:N</text>
  <line x1="260" y1="250" x2="390" y2="210" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <line x1="450" y1="80" x2="450" y2="170" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <text x="458" y="130" font-family="sans-serif" font-size="10" fill="#7f8c8d">1:N</text>
  <line x1="510" y1="195" x2="570" y2="195" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <text x="530" y="188" font-family="sans-serif" font-size="10" fill="#7f8c8d">1:N</text>
  <line x1="510" y1="185" x2="570" y2="115" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <line x1="510" y1="205" x2="570" y2="275" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <line x1="620" y1="140" x2="620" y2="170" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_er)"/>
  <text x="626" y="158" font-family="sans-serif" font-size="10" fill="#7f8c8d">1:N</text>
</svg>
</div>

<div style="page-break-before: always;"></div>

### 3.4 Entidades Principales

| Entidad | Clave Primaria | Descripción |
|---|---|---|
| **CLIENTE** | **cliente_id** | Empresa/autónomo propietario de las fincas. Accede con su CIF. |
| **LOCALIDAD** | **codigo_postal** | Catálogo de municipios. Normaliza la ubicación de las parcelas. |
| **PARCELA** | **parcela_id** | Finca física identificada por referencia catastral. |
| **INVERNADERO** | **invernadero_id** | Nave dentro de una parcela. Contiene sensores y actuadores. |
| **CULTIVO** | **cultivo_id** | Tipo de cultivo con sus parámetros óptimos asociados. |
| **PARAMETROS_OPTIMOS** | **parametro_id** | Rangos ideales (temp., humedad, pH) por cultivo y fase. |
| **SENSOR** | **sensor_id** | Dispositivo de lectura (temperatura, humedad, viento, etc.). |
| **MEDICION** | **medicion_id** | Registro histórico de una lectura de sensor con timestamp. |
| **ACTUADOR** | **actuador_id** | Dispositivo de control (riego, ventana, calefacción, etc.). |
| **ACCION_ACTUADOR** | **accion_id** | Log de cada acción ejecutada por un actuador. |
| **RECOMENDACION_RIEGO** | **recomendacion_id** | Decisión de riego generada automáticamente por el backend. |

<div style="page-break-after: always;"></div>

## 4. Arquitectura de Sistemas e Infraestructura

### 4.1 Visión General

SIRA se despliega como un conjunto de microservicios orquestados mediante **Docker Compose**. Todos los componentes operan dentro de una red interna privada (`sira-network`), siendo Nginx el único servicio expuesto al exterior.

<div align="center">
<svg width="600" height="340" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_infra" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- External -->
  <rect x="220" y="10" width="160" height="40" rx="6" fill="#ecf0f1" stroke="#2c3e50" stroke-width="2"/>
  <text x="300" y="35" font-family="sans-serif" font-weight="bold" font-size="13" text-anchor="middle" fill="#2c3e50">INTERNET / IoT</text>
  <!-- Arrow down -->
  <line x1="300" y1="50" x2="300" y2="95" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_infra)"/>
  <text x="312" y="77" font-family="sans-serif" font-size="11" fill="#7f8c8d">HTTP :80</text>
  <!-- Docker boundary -->
  <rect x="30" y="95" width="540" height="210" rx="10" fill="#fafafa" stroke="#3498db" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="50" y="118" font-family="sans-serif" font-weight="bold" font-size="12" fill="#3498db">Red Docker: sira-network</text>
  <!-- Nginx -->
  <rect x="200" y="125" width="200" height="50" rx="6" fill="#3498db" stroke="#2c3e50" stroke-width="2"/>
  <text x="300" y="146" font-family="sans-serif" font-weight="bold" font-size="13" text-anchor="middle" fill="#fff">NGINX</text>
  <text x="300" y="163" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">Proxy Inverso · Puerto 80</text>
  <!-- Arrows to services -->
  <line x1="240" y1="175" x2="140" y2="225" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_infra)"/>
  <text x="162" y="203" font-family="sans-serif" font-size="10" fill="#7f8c8d">/*</text>
  <line x1="300" y1="175" x2="300" y2="225" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_infra)"/>
  <text x="306" y="205" font-family="sans-serif" font-size="10" fill="#7f8c8d">/api/</text>
  <!-- Frontend -->
  <rect x="60" y="225" width="140" height="55" rx="6" fill="#e74c3c" stroke="#2c3e50" stroke-width="2"/>
  <text x="130" y="248" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">FRONTEND</text>
  <text x="130" y="265" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">PHP / Apache</text>
  <text x="130" y="279" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#ffc0c0">Puerto interno: 80</text>
  <!-- Backend -->
  <rect x="225" y="225" width="150" height="55" rx="6" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="300" y="248" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">BACKEND</text>
  <text x="300" y="265" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">FastAPI / Uvicorn</text>
  <text x="300" y="279" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#afffcd">Puerto interno: 8000</text>
  <!-- DB -->
  <rect x="400" y="225" width="140" height="55" rx="6" fill="#f39c12" stroke="#2c3e50" stroke-width="2"/>
  <text x="470" y="248" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">POSTGRESQL</text>
  <text x="470" y="265" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">Base de Datos</text>
  <text x="470" y="279" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#ffeac0">Puerto interno: 5432</text>
  <!-- Backend to DB -->
  <line x1="375" y1="252" x2="400" y2="252" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_infra)"/>
  <text x="380" y="247" font-family="sans-serif" font-size="10" fill="#7f8c8d">SQL</text>
  <!-- Nginx to DB (hidden ports note) -->
  <line x1="370" y1="155" x2="470" y2="225" stroke="#bdc3c7" stroke-width="1.5" stroke-dasharray="4,3"/>
</svg>
</div>

### 4.2 Nginx como API Gateway y Perímetro de Seguridad

Nginx es la pieza central de la infraestructura de red. Su configuración implementa:

- **Aislamiento de puertos**: Los puertos internos de FastAPI (8000), PostgreSQL (5432) y Apache (80 interno) nunca se exponen a internet.
- **Enrutamiento inteligente**: Las peticiones a **/api/**, **/docs** y **/redoc** se redirigen al contenedor FastAPI. El resto del tráfico se sirve desde el contenedor PHP.
- **Prevención DoS**: La directiva **client_max_body_size 50M** previene ataques de saturación por payload.
- **Soporte WebSocket**: Habilitado mediante las cabeceras **Upgrade** para el flujo continuo de datos IoT.
- **Abstracción de entorno**: El puerto de exposición se configura mediante variable de entorno (**SIRA_PORT**), permitiendo usar 8085 en local y 80 en AWS sin tocar el código.

<div style="page-break-before: always;"></div>

### 4.3 Variables de Entorno (`.env`)

Toda la configuración sensible se centraliza en un archivo `.env` excluido del control de versiones (`.gitignore`):

| Variable | Descripción | Valor Local | Valor AWS |
|---|---|---|---|
| **SIRA_PORT** | Puerto de exposición de Nginx | **8085** | **80** |
| **DB_USER** | Usuario de PostgreSQL | *(local)* | *(seguro)* |
| **ACCESS_TOKEN_EXPIRE_MINUTES** | Duración máxima de sesión | **1440** (24h) | **240** |
| **DB_PASSWORD** | Contraseña de PostgreSQL | *(local)* | *(seguro)* |

<div style="page-break-before: always;"></div>

### 4.4 Estrategia de Copias de Seguridad (Backups)

Para garantizar la integridad de los datos ante fallos críticos o errores humanos, se ha implementado un sistema de **backups graduales inteligentes** mediante el script `scripts/backup_sira.sh`.

#### Lógica de Decisión (Cerebro de Backup)

El script no se limita a copiar archivos, sino que analiza el historial para optimizar el espacio en disco:

1. **Copia Anual**: Si es la primera ejecución del año, genera un backup en la carpeta `/anuales`. Se conservan las últimas **2 copias**.
2. **Copia Mensual**: Si ya hay anual pero es el primer backup del mes, se guarda en `/mensuales`. Se conservan las últimas **3 copias**.
3. **Copia Diaria**: En cualquier otro caso, genera un backup en `/diarios`. Se conservan las últimas **10 copias**.

#### Características Técnicas

- **Exclusiones Inteligentes**: Para optimizar el tamaño, el script ignora directorios pesados o innecesarios como `.git`, `venv`, `__pycache__` y los datos crudos de la base de datos (que se gestionan mediante volcados SQL independientes).
- **Prioridad de Sistema**: Utiliza el comando `nice -n 19` para realizar la compresión con la menor prioridad de CPU, evitando que el proceso de backup afecte al rendimiento de la API en producción.
- **Autogestión de Espacio**: Implementa una lógica de **rotación automática** que elimina las copias más antiguas una vez superado el límite configurado para cada tipo.
- **Metadatos**: Cada copia incluye un archivo `info_backup.txt` con la fecha, el usuario que lo lanzó y el tipo de backup realizado.

#### Automatización (Crontab)

Para asegurar la ejecución sin intervención humana, el script se programa en el `crontab` del sistema (Ubuntu en AWS). Se recomienda una ejecución diaria a una hora de baja carga (ej. 03:00 AM):

```text
# Programación en crontab -e
00 03 * * * /bin/bash /home/ubuntu/Proyecto_SIRA/scripts/backup_sira.sh >> /home/ubuntu/sira_backups/log_cron.txt 2>&1
```

> [!IMPORTANTE!]
> **Estado en Producción:** Este mecanismo de automatización se encuentra **activo y verificado** en la infraestructura de AWS EC2 del proyecto, garantizando la persistencia ante desastres de forma autónoma.

<div style="page-break-after: always;"></div>

## 5. Arquitectura de Software: Backend (FastAPI)

### 5.1 Stack Tecnológico del Backend

| Componente | Tecnología | Rol |
|---|---|---|
| Framework Web | FastAPI (Python) | Exposición de endpoints REST y documentación automática |
| Servidor ASGI | Uvicorn | Motor de ejecución asíncrona |
| ORM | SQLAlchemy | Mapeo objeto-relacional hacia PostgreSQL |
| Validación | Pydantic (Schemas) | Validación de datos de entrada y serialización de salida |
| Seguridad | python-jose + passlib | Generación y verificación de JWT / hashing Bcrypt |

### 5.2 Flujo de Datos Interno (Request/Response)

<div align="center">
<svg width="600" height="300" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_flow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- Steps -->
  <rect x="10" y="120" width="95" height="50" rx="6" fill="#3498db" stroke="#2c3e50" stroke-width="2"/>
  <text x="58" y="142" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">CLIENTE</text>
  <text x="58" y="158" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">HTTP Request</text>
  <line x1="105" y1="145" x2="130" y2="145" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_flow)"/>
  <rect x="130" y="120" width="95" height="50" rx="6" fill="#9b59b6" stroke="#2c3e50" stroke-width="2"/>
  <text x="178" y="142" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">ROUTER</text>
  <text x="178" y="158" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">Auth + Route</text>
  <line x1="225" y1="145" x2="250" y2="145" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_flow)"/>
  <rect x="250" y="120" width="95" height="50" rx="6" fill="#e67e22" stroke="#2c3e50" stroke-width="2"/>
  <text x="298" y="142" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">SCHEMA</text>
  <text x="298" y="158" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">Pydantic Val.</text>
  <line x1="345" y1="145" x2="370" y2="145" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_flow)"/>
  <rect x="370" y="120" width="95" height="50" rx="6" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="418" y="142" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">CRUD</text>
  <text x="418" y="158" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">SQLAlchemy</text>
  <line x1="465" y1="145" x2="490" y2="145" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_flow)"/>
  <rect x="490" y="120" width="100" height="50" rx="6" fill="#f39c12" stroke="#2c3e50" stroke-width="2"/>
  <text x="540" y="142" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">POSTGRESQL</text>
  <text x="540" y="158" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">SQL + Commit</text>
  <!-- Labels above -->
  <text x="58" y="110" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">1. Petición</text>
  <text x="178" y="110" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">2. Seguridad</text>
  <text x="298" y="110" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">3. Validación</text>
  <text x="418" y="110" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">4. Lógica</text>
  <text x="540" y="110" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">5. Persistencia</text>
  <!-- Return arrow -->
  <line x1="540" y1="195" x2="58" y2="195" stroke="#2ecc71" stroke-width="2" marker-end="url(#arr_flow)"/>
  <text x="300" y="215" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#27ae60">JSON Response (HTTP 200 OK)</text>
  <line x1="540" y1="170" x2="540" y2="195" stroke="#2ecc71" stroke-width="1.5"/>
  <line x1="58" y1="170" x2="58" y2="195" stroke="#2ecc71" stroke-width="1.5"/>
</svg>
</div>

<div style="page-break-before: always;"></div>

### 5.3 Organización de Archivos del Backend

| Archivo | Responsabilidad |
|---|---|
| **main.py** | Punto de entrada. Registra routers y configura middleware. |
| **models.py** | Define las tablas de PostgreSQL como clases Python (SQLAlchemy). |
| **schemas.py** | Define los modelos de validación Pydantic para entrada y salida de datos. |
| **crud.py** | Implementa las operaciones de lectura, escritura, actualización y borrado. |
| **routers/** | Directorio con un archivo por recurso (usuarios, parcelas, sensores, etc.). |
| **security.py** | Lógica de generación y verificación de tokens JWT + Bcrypt. |

### 5.4 Documentación Automática (Swagger)

FastAPI genera automáticamente documentación interactiva accesible en `/docs` (Swagger UI) y `/redoc`. Estos endpoints están protegidos por Nginx para que solo sean accesibles desde la red interna o mediante VPN en producción.

<div style="page-break-after: always;"></div>

## 6. Arquitectura de Software: Frontend (PHP)

### 6.1 Rol del Frontend y Filosofía SSR

El frontend de SIRA está construido en **PHP con renderizado en servidor (SSR)**. Esta decisión arquitectónica implica que el servidor genera el HTML completo antes de enviarlo al navegador, garantizando que:

- La lógica de negocio (permisos, roles, filtros de datos) nunca se expone al cliente.
- El sistema funciona en cualquier navegador, incluso con JavaScript desactivado.
- La carga inicial es más rápida, ya que no hay que esperar a que un framework de JavaScript hidrate la página.

### 6.2 PHP como Proxy/Gateway hacia la API

La comunicación con el backend no se realiza desde el navegador, sino desde PHP en el servidor mediante la librería **cURL**. El flujo completo para servir una página de datos es:

<div align="center">
<svg width="580" height="280" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_php" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- Step boxes (Aumentados a h=75) -->
  <rect x="10" y="80" width="95" height="75" rx="6" fill="#ecf0f1" stroke="#2c3e50" stroke-width="2"/>
  <text x="58" y="105" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#2c3e50">NAVEGADOR</text>
  <text x="58" y="125" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#2c3e50">GET /sensores</text>
  <text x="58" y="140" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#2c3e50">.php</text>
  
  <line x1="105" y1="117" x2="130" y2="117" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_php)"/>
  
  <rect x="130" y="80" width="115" height="75" rx="6" fill="#3498db" stroke="#2c3e50" stroke-width="2"/>
  <text x="188" y="105" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">NGINX</text>
  <text x="188" y="125" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">Ruta no /api/</text>
  <text x="188" y="140" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">→ PHP/Apache</text>
  
  <line x1="245" y1="117" x2="270" y2="117" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_php)"/>
  
  <rect x="270" y="80" width="115" height="75" rx="6" fill="#e74c3c" stroke="#2c3e50" stroke-width="2"/>
  <text x="328" y="105" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">PHP</text>
  <text x="328" y="125" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">cURL → /api/</text>
  <text x="328" y="140" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">sensores</text>
  
  <line x1="385" y1="117" x2="410" y2="117" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_php)"/>
  
  <rect x="410" y="80" width="115" height="75" rx="6" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="468" y="105" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#fff">FastAPI</text>
  <text x="468" y="125" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">Consulta SQL</text>
  <text x="468" y="140" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#fff">→ JSON</text>
  
  <!-- Return (Bajado para dar aire) -->
  <line x1="468" y1="215" x2="58" y2="215" stroke="#2ecc71" stroke-width="2" marker-end="url(#arr_php)"/>
  <text x="270" y="240" font-family="sans-serif" font-weight="bold" font-size="11" text-anchor="middle" fill="#27ae60">PHP genera HTML con datos → Navegador recibe página completa</text>
  <line x1="468" y1="155" x2="468" y2="215" stroke="#2ecc71" stroke-width="1.5"/>
  <line x1="58" y1="155" x2="58" y2="215" stroke="#2ecc71" stroke-width="1.5"/>
</svg>
</div>
<br><br>

<div style="page-break-after: always;"></div>

### 6.3 Sistema de Diseño Visual (CSS)

Se ha implementado una arquitectura CSS modular y basada en **Custom Properties (Variables CSS)**:

- **`variables.css`**: Token de diseño central. Color primario SIRA: `#10b981` (verde esmeralda). Fondo: `#0f172a` (azul marino oscuro). Modo oscuro por defecto.
- **`base.css`**: Reset de estilos + tipografía (Google Fonts: Inter, Roboto).
- **`layout.css`**: Estructura de navegación y cuerpo principal.
- **Archivos espejo**: Cada página PHP tiene su propio `nombre.css` para estilos específicos.
- **Módulos de clima**: `ideal.css`, `rain.css`, `heat.css`, `sequia.css`, `cloudy.css` y `snow.css` — cargados dinámicamente según los datos del sensor. Los efectos usan `pointer-events: none` para no bloquear la interacción.

### 6.4 Control de Acceso en el Frontend

- **`header.php`**: Incluido en todas las páginas. Extrae el rol del usuario del JWT y muestra/oculta elementos de la interfaz en consecuencia (Root > Admin > Cliente).
- **Borrado lógico**: Se usa el campo `activa = false` en lugar de eliminar registros, preservando el historial.
- **Refresco automático**: La etiqueta `<meta refresh>` recarga las páginas de monitorización cada 10-15 segundos. Está desactivado en páginas con formularios para no perder datos del usuario.

<div style="page-break-after: always;"></div>

## 7. Identidad Visual y Design System (SIRA UX)

### 7.1 Filosofía de Diseño

El diseño de SIRA no es meramente estético; responde a una necesidad de **claridad operativa** en entornos agrícolas. Se basa en una **arquitectura de doble tema (Dark/Light)** que permite al usuario adaptar la interfaz a las condiciones lumínicas del campo o de la oficina.

Aunque se ha optado prioritariamente por una estética *High-Tech Dark* (que reduce la fatiga visual y resalta los datos críticos), el sistema es totalmente funcional en modo claro, manteniendo la misma jerarquía de información.

### 7.2 Paleta de Colores Oficial

La coherencia cromática es el pilar de la interfaz. Se definen cuatro niveles de color para guiar la atención del usuario:

<div align="center">
<svg width="600" height="160" xmlns="http://www.w3.org/2000/svg">
  <!-- Navy SIRA -->
  <rect x="20" y="20" width="120" height="80" rx="8" fill="#0f172a" stroke="#2c3e50" stroke-width="2"/>
  <text x="80" y="125" font-family="sans-serif" font-size="11" text-anchor="middle" font-weight="bold" fill="#2c3e50">Navy SIRA</text>
  <text x="80" y="142" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">#0F172A</text>
  
  <!-- Emerald SIRA -->
  <rect x="160" y="20" width="120" height="80" rx="8" fill="#10b981" stroke="#2c3e50" stroke-width="2"/>
  <text x="220" y="125" font-family="sans-serif" font-size="11" text-anchor="middle" font-weight="bold" fill="#10b981">Esmeralda</text>
  <text x="220" y="142" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">#10B981</text>

  <!-- Technical Orange -->
  <rect x="300" y="20" width="120" height="80" rx="8" fill="#ffab00" stroke="#2c3e50" stroke-width="2"/>
  <text x="360" y="125" font-family="sans-serif" font-size="11" text-anchor="middle" font-weight="bold" fill="#d97706">Temp. Focus</text>
  <text x="360" y="142" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">#FFAB00</text>

  <!-- Technical Blue -->
  <rect x="440" y="20" width="120" height="80" rx="8" fill="#00d1ff" stroke="#2c3e50" stroke-width="2"/>
  <text x="500" y="125" font-family="sans-serif" font-size="11" text-anchor="middle" font-weight="bold" fill="#0099cc">Hum. Focus</text>
  <text x="500" y="142" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">#00D1FF</text>
</svg>
</div>

| Categoría | HEX (Dark) | HEX (Light) | Uso Principal |
|---|---|---|---|
| **Base (Fondo)** | **#0f172a** | **#f8fafc** | Fondo principal y estructura. |
| **Superficie** | **rgba(30,41,59,0.7)** | **#ffffff** | Tarjetas, paneles y contenedores. |
| **Texto Main** | **#f8fafc** | **#0f172a** | Lectura de datos y navegación. |
| **Primario** | **#10b981** | **#10b981** | Botones de acción y branding. |
| **Acento Temp.** | **#ffab00** | **#d97706** | Datos de temperatura y alertas de calor. |
| **Acento Hum.** | **#00d1ff** | **#0099cc** | Datos de humedad y riego. |
| **Error** | **#ef4444** | **#b91c1c** | Alertas críticas y fallos. |

<div style="page-break-after: always;"></div>

### 7.3 Tipografía

La legibilidad es crítica en un panel de control técnico. Se utiliza una jerarquía clara:
- **Inter (Principal):** Fuente sans-serif de alta legibilidad para todos los datos del dashboard. Su diseño optimizado para pantallas permite leer valores numéricos rápidos sin error.
- **Montserrat / Roboto:** Reservadas para la documentación oficial y elementos de identidad corporativa en portadas y cabeceras.

### 7.4 Estándares de Interfaz (SIRA Standard-10)

Para lograr una apariencia premium y moderna, SIRA sigue la regla del **Standard-10**:
- **Radios de Contenedor (10px):** Todas las tarjetas (`.card`), paneles y ventanas de navegación usan un radio de 10px. Esto suaviza la rigidez de los datos y acerca el software a estándares de diseño actuales (estilo iOS/SaaS moderno).
- **Radios de Interacción (4px):** Los elementos clicables (botones, inputs) usan un radio menor para mantener una sensación de precisión y control técnico.

### 7.5 Glassmorphism y Filtros Cinemáticos

La característica más distintiva de SIRA UX es su capacidad de **reacción visual al entorno**:
- **Glassmorphism:** Los paneles usan fondos translúcidos con `backdrop-filter: blur(10px)`. Esto permite que los efectos climáticos del fondo (lluvia, nieve) sean visibles pero no interfieran con la lectura de los datos.
- **Filtros de Clima:** Mediante CSS dinámico, la plataforma aplica filtros de color a toda la interfaz según el estado de los sensores. El sistema orquesta una jerarquía visual completa:
    - *Modo Ideal:* Contraste y saturación optimizados para máxima claridad bajo luz solar (`saturate(1.08) hue-rotate(5deg)`).
    - *Modo Calor:* Tonalidad sepia y brillo aumentado para simular atmósfera pesada (`sepia(0.12) brightness(1.05)`).
    - *Modo Tormenta (Lluvioso):* Desaturación y tono azulado frío (`saturate(0.70) hue-rotate(-10deg)`).
    - *Modo Sequía:* Desaturación extrema, tono terroso y contraste alto (`saturate(0.55) sepia(0.15)`).
    - *Modo Nublado:* Reducción de saturación y brillo suave para días grises (`saturate(0.70) brightness(0.94)`).
    - *Modo Nieve / Helada:* Tono gélido (cian) y desaturación para ambientes de baja temperatura.
- **Modo Randomize:** El sistema permite la activación de un ciclo dinámico que alterna entre estos estados, demostrando la capacidad de respuesta inmediata de la interfaz ante cambios en la telemetría.

<div style="page-break-after: always;"></div>

### 7.6 Dualidad de Temas (Dark & Light)

SIRA implementa un sistema de temas dinámicos basado en atributos de datos (`data-theme`). Esta funcionalidad no es solo estética, sino una herramienta de legibilidad operativa:
- **Modo Oscuro (Default):** Optimizado para uso en interiores o condiciones de baja luminosidad. Utiliza una base Navy profundo (`#0f172a`) que reduce la fatiga visual y resalta los datos críticos en verde esmeralda y azul tecnológico.
- **Modo Claro (High Visibility):** Diseñado específicamente para el trabajo en campo bajo luz solar directa. El fondo cambia a un blanco hueso (`#f8fafc`) y el texto a un azul marino profundo, maximizando el contraste para combatir los reflejos en las pantallas de dispositivos móviles.
- **Implementación Técnica (Zero-JS UI):** El cambio de tema se gestiona mediante **Variables CSS (Custom Properties)**. El servidor PHP inyecta el atributo `data-theme` en la etiqueta `<html>`, y el CSS redefine instantáneamente los tokens de color sin necesidad de recargar la página o ejecutar lógica compleja en el cliente.
- **Persistencia:** La preferencia del usuario se almacena en la sesión del servidor, garantizando que la experiencia sea coherente al saltar entre el ordenador de la oficina y la tablet de campo.

### 7.7 UX Adaptativa y Diseño Responsivo (3-Tier)

Para garantizar la eficacia del sistema en el entorno agrícola, SIRA se ha diseñado bajo una arquitectura responsiva de tres niveles, verificada mediante Media Queries:

1. **Nivel Desktop (1080p / >1200px):** El panel de control se despliega en su máxima extensión, permitiendo una visión panorámica de todos los invernaderos, gráficas de telemetría y logs de actuadores de forma simultánea.
2. **Nivel Tablet (Breakpoints 1024px - 900px):** La interfaz reorganiza los componentes (Grids) para priorizar la visualización táctil. Los elementos de navegación se compactan y los radio de interacción se mantienen en el estándar de 10px para facilitar la navegación con dedos.
3. **Nivel Mobile (Breakpoints <768px):** En smartphones, SIRA aplica una **jerarquía de información crítica**. Se ocultan elementos decorativos o secundarios (como el reloj central del header o taglines de marca) para dejar espacio libre a los valores de los sensores y botones de control de actuadores. Las tablas de datos se transforman en listas verticales legibles, asegurando que el agricultor pueda actuar sobre un riego desde cualquier punto de la finca.

<div style="page-break-after: always;"></div>

## 8. Lógica de Dominio: Sensores, Actuadores y Cultivos

### 8.1 Dispositivos Implementados

**Sensores (Entrada de datos):**

| Sensor | Variable medida | Unidad |
|---|---|---|
| Temperatura | Temperatura interior del invernadero | ºC |
| Humedad del Suelo | Nivel de humedad del sustrato | % |
| Lluvia | Detección de precipitación | Booleano |
| Viento | Velocidad del viento exterior | km/h |
| Radiación Solar | Intensidad de luz recibida | W/m² |

**Actuadores (Salida / Control):**

| Actuador | Acción | Trigger |
|---|---|---|
| Riego (Electroválvula) | Apertura/cierre del suministro de agua | Humedad suelo < 60% |
| Motor de Ventana | Apertura/cierre de ventilación | Temp > 30ºC o viento > 45km/h |
| Iluminación LED | Encendido/apagado de luces | Radiación < 200 W/m² en jornada laboral |
| Extractor de Aire | Renovación del aire | Humedad relativa > 90% |
| Calefacción | Protección contra heladas | Temperatura < 10ºC |

<div style="page-break-before: always;"></div>

### 8.2 Lógica de Automatización y Prioridades

El backend evalúa las mediciones entrantes y aplica las siguientes reglas por orden de prioridad:

<div align="center">
<svg width="560" height="290" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_logic" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- Priority 1 -->
  <rect x="30" y="20" width="500" height="55" rx="8" fill="#e74c3c" stroke="#2c3e50" stroke-width="2"/>
  <text x="50" y="43" font-family="sans-serif" font-weight="bold" font-size="13" fill="#fff">PRIORIDAD 1 — SEGURIDAD ESTRUCTURAL</text>
  <text x="50" y="63" font-family="sans-serif" font-size="11" fill="#fff">Viento > 45 km/h o lluvia activa → CERRAR VENTANAS inmediatamente (ignora temperatura)</text>
  <!-- Arrow -->
  <line x1="280" y1="75" x2="280" y2="100" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_logic)"/>
  <!-- Priority 2 -->
  <rect x="30" y="100" width="500" height="55" rx="8" fill="#e67e22" stroke="#2c3e50" stroke-width="2"/>
  <text x="50" y="123" font-family="sans-serif" font-weight="bold" font-size="13" fill="#fff">PRIORIDAD 2 — SEGURIDAD DEL CULTIVO</text>
  <text x="50" y="143" font-family="sans-serif" font-size="11" fill="#fff">Temp &lt; 10ºC → CALEFACCIÓN ON · Temp > 40ºC → EXTRACTORES ON + avisar usuario</text>
  <!-- Arrow -->
  <line x1="280" y1="155" x2="280" y2="180" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_logic)"/>
  <!-- Priority 3 -->
  <rect x="30" y="180" width="500" height="55" rx="8" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="50" y="203" font-family="sans-serif" font-weight="bold" font-size="13" fill="#fff">PRIORIDAD 3 — OPTIMIZACIÓN DEL CRECIMIENTO</text>
  <text x="50" y="223" font-family="sans-serif" font-size="11" fill="#fff">Humedad suelo &lt; 60% → RIEGO ON · Radiación &lt; 200 W/m² en jornada → LUCES ON</text>
  <!-- Arrow -->
  <line x1="280" y1="235" x2="280" y2="260" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_logic)"/>
  <rect x="155" y="260" width="250" height="25" rx="6" fill="#2c3e50"/>
  <text x="280" y="278" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">REGISTRO EN ACCION_ACTUADOR</text>
</svg>
</div>

### 8.3 Parámetros Agronómicos por Cultivo

Los umbrales de automatización se adaptan al cultivo configurado en cada invernadero:

| Cultivo | Temp. Óptima (ºC) | Humedad Óptima (%) | pH Ideal | Riego (L/m²/día) |
|---|---|---|---|---|
| **Tomate** | 18 – 30 | 60 – 80 | 6.0 – 6.8 | 5.0 |
| **Pimiento** | 21 – 26 | 65 – 85 | 5.5 – 7.0 | 4.5 |
| **Pepino** | 20 – 30 | 70 – 90 | 5.5 – 7.0 | 4.2 |
| **Sandía** | 20 – 30 | 60 – 70 | 6.0 – 6.8 | 3.8 |
| **Melón** | 20 – 30 | 60 – 70 | 6.0 – 6.8 | 3.8 |
| **Calabacín** | 25 – 35 | 65 – 80 | 5.6 – 6.8 | 4.0 |
| **Berenjena** | 25 – 30 | 60 – 80 | 5.5 – 6.8 | 5.0 |

*Fuentes: Fundación Cajamar, MAPA, InfoAgro Almería.*

### 8.4 Control Manual y "Modo Cortesía"

Si el usuario activa o desactiva un actuador manualmente desde el panel, el sistema automático **respeta esa decisión durante 2 horas**. Pasado ese tiempo, la automatización se reactiva para prevenir descuidos (ej. riego abierto toda la noche).

<div style="page-break-before: always;"></div>

### 8.5 Estrategia de Simulación IoT

#### Justificación de la Decisión

El sistema SIRA está diseñado para integrarse con sensores físicos reales (temperatura, humedad, viento, lluvia, radiación solar) desplegados en invernaderos. Sin embargo, en el contexto de un Trabajo de Fin de Grado, **no se dispone del hardware físico necesario** (microcontroladores, módulos de comunicación, cableado de campo, etc.) para realizar una demostración con dispositivos reales ante el tribunal evaluador.

Como solución técnica a esta limitación, se ha desarrollado un **simulador por software** (`scripts/simulador.py`) que replica fielmente el comportamiento de una red de sensores IoT real:

- **Inserción periódica**: El script se conecta directamente a PostgreSQL e inserta nuevas mediciones cada 10 segundos, simulando el ritmo de telemetría de sensores físicos.
- **Realismo estadístico**: Los valores no son constantes, sino que incorporan pequeñas variaciones aleatorias (ruido gaussiano) para evitar que las gráficas del dashboard muestren líneas rectas artificiales.
- **Respuesta automática en tiempo real**: El backend de FastAPI analiza cada nueva medición y decide en el momento si debe activar o desactivar actuadores, exactamente igual que haría con hardware real.
- **Base de conocimientos local**: Se ha descartado el uso de APIs externas de datos agronómicos (como Perenual) para garantizar que el sistema funcione al 100% sin conexión a internet durante la defensa. Los parámetros óptimos por cultivo están almacenados en la base de datos propia.

Esta decisión demuestra que la **arquitectura del sistema es agnóstica a la fuente de datos**: en un despliegue productivo real, únicamente sería necesario sustituir el script simulador por los drivers de comunicación del hardware (MQTT, HTTP, Modbus), sin modificar ninguna línea del backend ni del frontend.

<div style="page-break-after: always;"></div>

#### Escenarios de Demostración (Presets)

El simulador incluye los siguientes escenarios climáticos pre-configurados, activables durante la defensa para mostrar la respuesta automática del sistema:

| Preset | Condición Simulada | Respuesta Esperada del Sistema |
|---|---|---|
| **Condiciones Ideales** | Temp 22ºC, Hum 70%, sin viento | Actuadores en reposo. VFX: Modo Ideal. |
| **Tormenta** | Viento > 60 km/h + lluvia | Cierre inmediato de ventanas (Prioridad 1). VFX: Modo Tormenta. |
| **Ola de Calor** | Temp 42ºC, Hum 30% | Extractores ON + riego ON + alerta usuario. VFX: Modo Calor. |
| **Sequía Extrema** | Humedad suelo < 20%, Radiación alta | Riego intensivo + alertas de estrés hídrico. VFX: Modo Sequía. |
| **Helada Nocturna** | Temp 3ºC | Calefacción ON (Prioridad 2). VFX: Modo Nieve/Helada. |
| **Día Nublado** | Radiación 50 W/m² | Luces LED ON (si es horario laboral). VFX: Modo Nublado. |
| **Randomize** | Valores aleatorios en rangos amplios | Evaluación dinámica de la lógica de control. VFX: Ciclo aleatorio de filtros climáticos. |

<div style="page-break-after: always;"></div>

## 9. Protocolo de Seguridad "Iron Fortress"

La seguridad es un eje transversal en todo SIRA, implementada bajo el nombre en clave **Iron Fortress**.

<div align="center">
<svg width="600" height="360" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_sec" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- Phase 1 box -->
  <rect x="20" y="10" width="560" height="145" rx="10" fill="#f8f9fa" stroke="#c0392b" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="40" y="35" font-family="sans-serif" font-weight="bold" font-size="14" fill="#c0392b">FASE 1: AUTENTICACIÓN (LOGIN)</text>
  <rect x="40" y="55" width="130" height="65" rx="6" fill="#3498db" stroke="#2c3e50" stroke-width="2"/>
  <text x="105" y="80" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">Credenciales</text>
  <text x="105" y="97" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">CIF + Contraseña</text>
  <text x="105" y="112" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#cce5ff">POST /api/token</text>
  <line x1="170" y1="87" x2="205" y2="87" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_sec)"/>
  <rect x="205" y="55" width="140" height="65" rx="6" fill="#e67e22" stroke="#2c3e50" stroke-width="2"/>
  <text x="275" y="80" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">Bcrypt (12 rnd)</text>
  <text x="275" y="97" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">Hash Irreversible</text>
  <text x="275" y="112" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#ffe0c0">Sin texto plano</text>
  <line x1="345" y1="87" x2="380" y2="87" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_sec)"/>
  <rect x="380" y="55" width="165" height="65" rx="6" fill="#27ae60" stroke="#2c3e50" stroke-width="2"/>
  <text x="463" y="76" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">Emisión de Tokens</text>
  <text x="463" y="93" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">JWT (firma RS256)</text>
  <text x="463" y="110" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">+ SID único en BBDD</text>
  <!-- Phase 2 box -->
  <rect x="20" y="195" width="560" height="145" rx="10" fill="#f8f9fa" stroke="#8e44ad" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="40" y="220" font-family="sans-serif" font-weight="bold" font-size="14" fill="#8e44ad">FASE 2: VALIDACIÓN DE PETICIONES</text>
  <rect x="40" y="238" width="130" height="65" rx="6" fill="#9b59b6" stroke="#2c3e50" stroke-width="2"/>
  <text x="105" y="263" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">Petición</text>
  <text x="105" y="280" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">Header Bearer</text>
  <text x="105" y="296" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#e0c0ff">JWT Token</text>
  <line x1="170" y1="270" x2="205" y2="270" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_sec)"/>
  <rect x="205" y="238" width="140" height="65" rx="6" fill="#f1c40f" stroke="#2c3e50" stroke-width="2"/>
  <text x="275" y="263" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#2c3e50">SID + Inactividad</text>
  <text x="275" y="280" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#2c3e50">Sliding Window</text>
  <text x="275" y="296" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#5a4800">30 min timeout</text>
  <line x1="345" y1="270" x2="380" y2="270" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_sec)"/>
  <rect x="380" y="238" width="165" height="65" rx="6" fill="#1abc9c" stroke="#2c3e50" stroke-width="2"/>
  <text x="463" y="263" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#fff">Acceso Concedido</text>
  <text x="463" y="280" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#fff">Lógica de Negocio</text>
  <text x="463" y="296" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#afffee">Rol verificado</text>
  <!-- Connector between phases -->
  <path d="M 462 120 C 462 160, 105 160, 105 238" fill="none" stroke="#bdc3c7" stroke-width="2" stroke-dasharray="5,3" marker-end="url(#arr_sec)"/>
</svg>
</div>

<div style="page-break-before: always;"></div>

### 9.1 Mecanismos de Seguridad Implementados

| Mecanismo | Detalle |
|---|---|
| **Bcrypt (12 rondas)** | Hashing irreversible de contraseñas. Ningún script SQL contiene contraseñas en texto plano. |
| **JWT Stateless** | Tokens firmados con clave secreta. Contienen el rol del usuario en los claims. Duración máxima: 24h. |
| **Session ID (SID) único** | Cada login genera un SID que se almacena en BD. Un nuevo login invalida el SID anterior (previene sesiones duplicadas). |
| **Sliding Window (30 min)** | Si no hay actividad en 30 minutos, la sesión se cierra automáticamente. |
| **Historial de contraseñas** | Se guardan las últimas 5 contraseñas (en JSON fuera de la BD) para evitar su reutilización. |
| **Caducidad de credenciales** | Las contraseñas caducan a los 90 días. |
| **XSS Prevention** | PHP usa htmlspecialchars() en todos los datos mostrados al usuario. |
| **SQL Injection Prevention** | SQLAlchemy parametriza todas las consultas. No existe SQL concatenado en cadenas. |
| **Almacenamiento seguro del JWT** | El token se guarda en la sesión PHP del servidor, no en localStorage del navegador. |
| **Secretos fuera del repositorio** | El archivo .env está en .gitignore. Nunca se sube a GitHub. |

### 9.2 Matriz de Riesgos

| Riesgo | Impacto | Probabilidad | Medida |
|---|---|---|---|
| Inyección SQL | Crítico | Baja | ORM SQLAlchemy parametrizado |
| Robo de sesión (XSS) | Alto | Baja | JWT en sesión PHP + htmlspecialchars() |
| Fuerza bruta | Alto | Media | Bcrypt 12 rondas + contraseñas complejas |
| Acceso no autorizado | Crítico | Baja | Validación de rol en cada endpoint |
| Fuga de secretos | Crítico | Baja | .gitignore + variables de entorno |
| Sesión olvidada abierta | Medio | Alta | Sliding Window 30 min |

<div style="page-break-after: always;"></div>

## 10. Despliegue en Producción (AWS)

### 10.1 Infraestructura Cloud

El sistema SIRA ha sido desplegado con éxito en **Amazon Web Services (AWS)** para la demo de la defensa del TFG.

| Parámetro | Valor |
|---|---|
| **Servicio** | Amazon EC2 |
| **Tipo de instancia** | t3.small |
| **Región** | us-east-1 (N. Virginia) |
| **Almacenamiento** | 8 GB EBS (Root Volume) |
| **RAM** | 2 GB (+ 2 GB Swap configurado) |
| **Security Group** | Puertos: 22 (SSH), 80 (HTTP), 443 (HTTPS), 8085 (Nginx) |

### 10.2 Diagrama de Despliegue Cloud

<div align="center">
<svg width="540" height="280" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <marker id="arr_aws" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
      <path d="M 0 0 L 10 5 L 0 10 z" fill="#2c3e50"/>
    </marker>
  </defs>
  <!-- Internet -->
  <ellipse cx="80" cy="100" rx="65" ry="40" fill="#ecf0f1" stroke="#2c3e50" stroke-width="2"/>
  <text x="80" y="96" font-family="sans-serif" font-weight="bold" font-size="12" text-anchor="middle" fill="#2c3e50">INTERNET</text>
  <text x="80" y="112" font-family="sans-serif" font-size="11" text-anchor="middle" fill="#7f8c8d">Usuario / IoT</text>
  <!-- Arrow -->
  <line x1="145" y1="100" x2="185" y2="100" stroke="#2c3e50" stroke-width="2" marker-end="url(#arr_aws)"/>
  <text x="165" y="92" font-family="sans-serif" font-size="10" text-anchor="middle" fill="#7f8c8d">HTTP :80</text>
  <!-- AWS EC2 boundary -->
  <rect x="185" y="30" width="330" height="220" rx="12" fill="#fef9e7" stroke="#f39c12" stroke-width="2" stroke-dasharray="6,3"/>
  <text x="205" y="55" font-family="sans-serif" font-weight="bold" font-size="13" fill="#f39c12">AWS EC2 — Ubuntu 24.04</text>
  <!-- Docker boundary -->
  <rect x="200" y="70" width="295" height="160" rx="8" fill="#eaf4fb" stroke="#3498db" stroke-width="1.5" stroke-dasharray="4,2"/>
  <text x="218" y="90" font-family="sans-serif" font-size="11" fill="#3498db">Docker Compose (sira-network)</text>
  <!-- Services -->
  <rect x="215" y="100" width="75" height="40" rx="5" fill="#3498db" stroke="#2c3e50" stroke-width="1.5"/>
  <text x="252" y="116" font-family="sans-serif" font-weight="bold" font-size="10" text-anchor="middle" fill="#fff">NGINX</text>
  <text x="252" y="130" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#fff">:80</text>
  <line x1="290" y1="120" x2="315" y2="120" stroke="#2c3e50" stroke-width="1.5" marker-end="url(#arr_aws)"/>
  <rect x="315" y="100" width="75" height="40" rx="5" fill="#27ae60" stroke="#2c3e50" stroke-width="1.5"/>
  <text x="352" y="116" font-family="sans-serif" font-weight="bold" font-size="10" text-anchor="middle" fill="#fff">FastAPI</text>
  <text x="352" y="130" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#fff">:8000</text>
  <line x1="390" y1="120" x2="415" y2="120" stroke="#2c3e50" stroke-width="1.5" marker-end="url(#arr_aws)"/>
  <rect x="415" y="100" width="60" height="40" rx="5" fill="#f39c12" stroke="#2c3e50" stroke-width="1.5"/>
  <text x="445" y="116" font-family="sans-serif" font-weight="bold" font-size="10" text-anchor="middle" fill="#fff">PG</text>
  <text x="445" y="130" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#fff">:5432</text>
  <rect x="215" y="165" width="75" height="40" rx="5" fill="#e74c3c" stroke="#2c3e50" stroke-width="1.5"/>
  <text x="252" y="181" font-family="sans-serif" font-weight="bold" font-size="10" text-anchor="middle" fill="#fff">PHP</text>
  <text x="252" y="196" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#fff">Apache :80</text>
  <line x1="252" y1="145" x2="252" y2="165" stroke="#2c3e50" stroke-width="1.5"/>
  <!-- Volume -->
  <rect x="415" y="165" width="60" height="40" rx="5" fill="#95a5a6" stroke="#2c3e50" stroke-width="1.5"/>
  <text x="445" y="181" font-family="sans-serif" font-weight="bold" font-size="10" text-anchor="middle" fill="#fff">Volumen</text>
  <text x="445" y="196" font-family="sans-serif" font-size="9" text-anchor="middle" fill="#fff">pg_data</text>
  <line x1="445" y1="140" x2="445" y2="165" stroke="#2c3e50" stroke-width="1.5"/>
</svg>
</div>

<div style="page-break-before: always;"></div>

### 10.3 Proceso de Despliegue

```text
# 1. Configurar swap (instancia t3.small con 2 GB RAM)
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile

# 2. Instalar Docker y Docker Compose
sudo apt update && sudo apt install -y docker.io docker-compose

# 3. Clonar el repositorio y configurar el entorno
git clone https://github.com/JuanRisueno/Proyecto_SIRA.git
cd Proyecto_SIRA && cp .env.example .env && nano .env

# 4. Levantar todos los servicios
docker-compose up -d --build
```

**Acceso:** `http://<IP_PUBLICA_AWS>:80`

<div style="page-break-after: always;"></div>

## 11. Análisis de Riesgos y Mitigaciones

| # | Riesgo | Categoría | Impacto | Medida Implementada |
|---|---|---|---|---|
| 1 | Fallo del servidor durante la demo | Infraestructura | Alto | Script de arranque automático (docker-compose up -d) |
| 2 | Desajuste de zona horaria en gráficas | Base de Datos | Medio | TZ=Europe/Madrid en todos los contenedores Docker |
| 3 | Saturación del servidor por simulador | Rendimiento | Medio | Intervalo mínimo de 5-10 segundos entre inserciones |
| 4 | Pérdida de datos del formulario por meta-refresh | UX | Bajo | Auto-refresh solo activo en páginas de monitorización |
| 5 | Introducción de valores imposibles por el usuario | Validación | Medio | Validación doble: Pydantic (backend) + PHP (frontend) |
| 6 | Ataque de inyección SQL | Seguridad | Crítico | ORM SQLAlchemy parametrizado (0 SQL concatenado) |
| 7 | Robo de token JWT | Seguridad | Alto | Token en sesión PHP, no en localStorage |
| 8 | Instancia EC2 sin memoria suficiente | Cloud | Alto | 2 GB Swap + monitorización de memoria |
| 9 | Corrupción o pérdida de datos | Base de Datos | Crítico | Sistema de backups graduales (Anual/Mensual/Diario) con rotación |

<div style="page-break-after: always;"></div>

## 12. Planificación y Línea Temporal

El proyecto se ha desarrollado en **6 fases** a lo largo del curso 2025-2026:

| Fase | Nombre | Estado | Responsables Principales |
|---|---|---|---|
| **I** | Infraestructura y Base de Datos | **✔ COMPLETADA** | Juan, Jorge, Alfonso |
| **II** | Desarrollo del Backend (FastAPI) | **✔ COMPLETADA** | Juan |
| **III** | Interfaz Web y Autenticación | **✔ COMPLETADA** | Jorge, Juan, Alfonso |
| **IV** | Simulación de Sensores y Cultivos | **✔ COMPLETADA** | Juan, Jorge, Alfonso |
| **V** | Seguridad Avanzada (Iron Fortress) | **✔ COMPLETADA** | Juan, Jorge |
| **VI** | Despliegue en AWS y Cierre | **✔ COMPLETADA** | Juan, Jorge |

**Hitos Superados:**
- **✔** Punto de Control 1: Servidor y BD funcionando correctamente.
- **✔** Punto de Control 2: Interfaz web funcional y conexión segura con la API.
- **✔** Punto de Control 3: Sistema de control y simulación validado.
- **✔** Punto de Control 4: Sistema protegido y listo para la defensa.
- **✔** Punto de Control 5: Proyecto desplegado y funcionando en la nube.

<div style="page-break-after: always;"></div>

## 13. Auditoría Final y Conclusiones

### 13.1 Resultado de la Auditoría Técnica

Tras la revisión final de todos los componentes del sistema:

| Área | Estado | Observaciones |
|---|---|---|
| **Documentación** | **✔** Completa | Guías de infraestructura, seguridad, BD y defensa redactadas. |
| **Backend (FastAPI)** | **✔** Estable | Organización modular, simulación IoT validada, JWT operativo. |
| **Frontend (PHP/CSS)** | **✔** Funcional | Glassmorphism, diseño oscuro, SSR sin dependencias JS externas. |
| **Base de Datos (PostgreSQL)** | **✔** Normalizada | Esquema 3FN, borrado lógico, backups configurados. |
| **Infraestructura (Docker/Nginx)** | **✔** Productiva | Despliegue local y AWS verificado. |
| **Seguridad (Iron Fortress)** | **✔** Auditada | Bcrypt, JWT, SID, Sliding Window, historial de contraseñas. |
| **Despliegue AWS** | **✔** Operativo | EC2 t3.small con Swap, Security Groups configurados. |

### 13.2 Conclusión

El sistema SIRA representa una solución tecnológica completa y funcional para la gestión automatizada del riego en explotaciones agrícolas. El proyecto demuestra la integración de competencias clave del ciclo ASIR:

- **Redes y Servidores**: Nginx como proxy inverso, Docker, AWS EC2.
- **Programación**: API REST con FastAPI (Python) y renderizado SSR con PHP.
- **Bases de Datos**: Diseño relacional normalizado en PostgreSQL.
- **Seguridad**: Autenticación JWT, hashing Bcrypt, control de sesiones.
- **Despliegue en Nube**: Instancia EC2, Security Groups, configuración de producción.

**Estado del Proyecto: TERMINADO — Versión 1.0 Final (Mayo 2026)**

<div style="page-break-after: always;"></div>

## 14. Autoría y Roles del Proyecto

El desarrollo integral de SIRA ha sido liderado por el siguiente equipo técnico:

| Integrante | Rol | Áreas de Responsabilidad |
|---|---|---|
| **Juan Risueño** | Backend Lead / DevOps | Arquitectura de sistemas, API FastAPI, protocolo Iron Fortress, despliegue AWS, Docker/Nginx |
| **Jorge Pedro López** | Coordinador / Frontend | Coordinación del proyecto, base de datos (SQL), desarrollo PHP, hardware IoT, efectos visuales CSS |
| **Alfonso Navarro** | CSS / Docs | Datos SQL y normalización, arquitectura CSS, documentación técnica del TFG |
---

*El proyecto SIRA es el resultado de más de 6 meses de trabajo colaborativo y aprendizaje. Cada decisión técnica documentada en este dossier ha sido tomada conscientemente, con base en los principios aprendidos durante el ciclo formativo de ASIR.*

---

<br>

**Proyecto SIRA — Dossier Técnico Maestro**
*Versión 1.0 Final — Mayo 2026*
*Trabajo de Fin de Grado — ASIR*
