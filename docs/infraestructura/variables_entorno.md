# Guía de Variables de Entorno (.env) - Proyecto SIRA

En el proyecto SIRA se utiliza un archivo llamado `.env` para centralizar toda la configuración del sistema, desde las contraseñas de la base de datos hasta las claves de seguridad. Este archivo es fundamental para que el proyecto funcione en diferentes entornos (local o en el servidor) sin tener que cambiar el código fuente.

---

## 1. Configuración de Red y Docker

Estas variables indican cómo se debe exponer la aplicación al exterior a través de Docker.

| Variable | Descripción | Valor común |
| :--- | :--- | :--- |
| `SIRA_PORT` | Puerto de acceso web (Proxy Nginx). | `8085` (Local) / `80` (AWS) |

---

## 2. Configuración de la Base de Datos (PostgreSQL)

Estas variables las usan tanto el contenedor de la base de datos para crearse como la API para poder conectarse a ella.

| Variable | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `DB_USER` | Nombre del usuario de la base de datos. | `juanrisueno` |
| `DB_PASSWORD` | Contraseña para conectar a la base de datos. | `juan1234` |
| `DB_NAME` | Nombre de la base de datos del proyecto. | `sira_db` |

---

## 3. Configuración de Seguridad (JWT)

Para que el sistema de login sea seguro, se utilizan tokens JWT. Estas variables controlan cómo se generan y cuánto duran.

| Variable | Descripción | Detalle |
| :--- | :--- | :--- |
| `JWT_SECRET_KEY` | Es la clave secreta que usa el servidor para firmar los tokens. | Debe ser una cadena larga y aleatoria. |
| `ACCESS_TOKEN_EXPIRE_MINUTES` | Tiempo que dura la sesión activa (en minutos). | Se ha configurado 1440 (24h) con control de inactividad de 30m. |

---

## 4. Configuración del Frontend (PHP)

El código PHP usa una constante para saber a qué dirección debe pedirle los datos a la API.

- `SIRA_API_BASE`: El sistema detecta automáticamente si estamos dentro de Docker o en local para usar la dirección correcta (`http://api:8000` o `http://localhost:8000`).

---

## 5. Variables de Diseño (CSS Custom Properties)

Para mantener la coherencia visual en todo el proyecto, se ha definido un sistema de tokens de diseño en el archivo `frontend/css/modules/variables.css`. Estas variables permiten cambiar el aspecto de toda la web desde un solo sitio.

### Colores y Estética
| Variable | Descripción | Valor (Tema Oscuro) |
| :--- | :--- | :--- |
| `--color-primary` | Color principal de la identidad SIRA (Verde). | `#10b981` |
| `--color-bg` | Color de fondo de la aplicación. | `#0f172a` |
| `--color-bg-card` | Fondo de tarjetas y paneles con transparencia. | `rgba(30, 41, 59, 0.7)` |
| `--color-text-main` | Color del texto principal. | `#f8fafc` |
| `--color-text-muted` | Color para textos secundarios o menos importantes. | `#94a3b8` |

### Estados de Sistema
| Variable | Descripción | Valor |
| :--- | :--- | :--- |
| `--color-error` | Color para errores y alertas críticas. | `#ef4444` |
| `--color-warning` | Color para advertencias y avisos. | `#f59e0b` |

### Tipografía (Escala Modular)
| Variable | Tamaño | Uso Sugerido |
| :--- | :--- | :--- |
| `--font-size-xs` | `0.75rem` | Etiquetas pequeñas y leyendas. |
| `--font-size-sm` | `0.85rem` | Textos secundarios. |
| `--font-size-base` | `0.95rem` | Texto de lectura principal. |
| `--font-size-lg` | `1.2rem` | Subtítulos de sección. |
| `--font-size-xl` | `1.8rem` | Títulos de tarjetas. |
| `--font-size-2xl` | `2.5rem` | Títulos principales de página. |

### Geometría y Espaciado
| Variable | Descripción | Valor |
| :--- | :--- | :--- |
| `--radius-container` | Radio de los bordes para tarjetas (Standard-10). | `10px` |
| `--radius-interactive` | Radio para elementos clicables (Standard-10). | `4px` |
| `--spacing-sm` | Margen/Relleno pequeño. | `0.75rem` |
| `--spacing-md` | Margen/Relleno estándar. | `1.25rem` |
| `--spacing-lg` | Margen/Relleno grande. | `1.5rem` |

### Sombras y Efectos
| Variable | Descripción | Efecto |
| :--- | :--- | :--- |
| `--shadow-card` | Sombra base de las tarjetas. | `0 4px 6px rgba(0,0,0,0.2)` |
| `--shadow-card-hover`| Sombra al pasar el ratón (elevación). | `0 20px 25px -5px ...` |
| `--transition-smooth`| Transición estándar para animaciones. | `0.3s cubic-bezier` |

---

**Importante para la seguridad**: El archivo `.env` nunca debe subirse a GitHub, por lo que está incluido en el archivo `.gitignore`. En el servidor de producción (AWS), se ha creado este archivo manualmente con contraseñas seguras.

---
**Documentación de Infraestructura - SIRA**  
*Versión 1.0 Final - 30 de Abril de 2026*
