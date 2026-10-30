# Incidencia: fallo de autenticación tras reconstruir los contenedores de SIRA

**Proyecto:** SIRA — Sistema Integral de Riego Automático
**Fecha de la incidencia:** 30/10/2026
**Componentes afectados:** `sira_api` (FastAPI + SQLAlchemy), `sira_nginx` (proxy inverso), `frontend/index.php`
**Severidad:** Alta (el sistema queda inoperativo: nadie puede iniciar sesión)
**Estado:** Resuelta — cambios aplicados en el repositorio (apartado 6); pendiente de reconstruir la imagen para desplegarlos

> **Nota (30/10/2026, misma jornada):** esta incidencia (driver `psycopg`) quedó cerrada, pero el
> mismo síntoma reapareció por una causa distinta: la contraseña del volumen de PostgreSQL ya no
> coincidía con la del `.env`. Ver
> [`INCIDENCIA_LOGIN_PASSWORD_POSTGRES_VOLUMEN.md`](./INCIDENCIA_LOGIN_PASSWORD_POSTGRES_VOLUMEN.md).
> Si el login vuelve a fallar, el orden de comprobación es: `./scripts/diagnostico_sira.sh` primero.

---

## Resumen

Tras levantar el stack completo desde cero (`docker compose up -d --build`), el formulario de
acceso mostraba el mensaje **«CIF o contraseña incorrectos»** con cualquier usuario, incluido
`root` / `root1234`.

La investigación descarta que el problema esté en la base de datos, en los usuarios o en los
hashes de las contraseñas (los tres se verificaron y son correctos). La causa real es que
**la API nunca llegó a arrancar**, porque una actualización de dependencias sin fijar versión
introdujo un driver de PostgreSQL que no está instalado en la imagen.

El mensaje del frontend era engañoso: traducía un error **502 Bad Gateway** (API caída) como
si fueran credenciales inválidas.

---

## 1. Entorno

| Elemento | Versión / detalle |
|---|---|
| Imagen de la API | `python:3.11-slim` (construida con `pip install -r requirements.txt`) |
| PostgreSQL | `postgres:16-alpine` (`sira_db`) |
| Proxy inverso | `nginx:latest` (`sira_nginx`, puerto externo 8085) |
| Frontend | `php:8.2-apache` (`sira_frontend`) |
| SQLAlchemy instalado | **2.1.6** |
| Driver instalado | `psycopg2-binary` 2.9.13 |
| Driver que intenta usar | `psycopg` (v3) — **no instalado** |

---

## 2. Síntomas observados

1. `docker ps` muestra **los cuatro contenedores en estado `Up`**, incluido `sira_api`.
2. La página de login (`http://localhost:8085/`) carga correctamente.
3. Cualquier intento de acceso con usuarios existentes devuelve el aviso
   *«CIF o contraseña incorrectos. Inténtalo de nuevo.»*
4. La base de datos contiene los 7 usuarios sembrados por `backend/database/20-data.sql`,
   todos con `activa = t`.

El punto 1 es la trampa: el contenedor está «arriba», pero **el servicio dentro no escucha en
el puerto 8000**.

---

## 3. Investigación

### 3.1 Prueba directa contra la API (saltándose el formulario)

```bash
# Llamada directa al endpoint de login a través de Nginx
curl -s -o /dev/null -w "%{http_code}\n" \
     -X POST http://localhost:8085/api/auth/token \
     -d "username=root&password=root1234"
```

Resultado: **`502`** (Bad Gateway), no `401`.

Esto ya descarta el problema de credenciales: un `401` lo generaría FastAPI tras consultar la
tabla `CLIENTE`; un `502` lo genera Nginx cuando **no hay nada escuchando** en el upstream.

### 3.2 Estado y logs del contenedor de la API

```bash
docker inspect -f '{{.Name}} restarts={{.RestartCount}} estado={{.State.Status}}' sira_api
docker logs --tail 40 sira_api
```

Salida relevante:

```
/sira_api restarts=0 estado=running
...
  File "/usr/local/lib/python3.11/site-packages/sqlalchemy/dialects/postgresql/psycopg.py", line 497, in import_dbapi
    import psycopg
ModuleNotFoundError: No module named 'psycopg'
```

`restarts=0` explica por qué Docker no lo reinicia: **el proceso no ha muerto**. Uvicorn se
ejecuta con `--reload`, así que el proceso padre (PID 1) permanece vivo aunque el worker que
importa la aplicación falle una y otra vez.

### 3.3 Comprobación de la base de datos

```bash
docker exec sira_db psql -U <DB_USER> -d <DB_NAME> -c \
  "select cliente_id, cif, rol, activa from cliente order by cliente_id;"
```

Los 7 usuarios existen y están activos. La inicialización (`/docker-entrypoint-initdb.d`) se
ejecutó correctamente sobre el volumen nuevo `proyecto_sira_postgres_data`.

### 3.4 Verificación criptográfica de los hashes

Se validaron los hashes sembrados con `bcrypt.checkpw()` contra las contraseñas documentadas
en `20-data.sql`:

| Usuario (CIF) | Contraseña documentada | Hash válido |
|---|---|---|
| `root` | `root1234` | Sí |
| `admin` | `admin1234` | Sí |
| `B04XXXXXX` (y resto de clientes) | `sol1234` | Sí |

### 3.5 Comprobación del driver que resuelve SQLAlchemy

```bash
docker exec sira_api python -c "
from sqlalchemy.dialects import postgresql
print(postgresql.dialect.__name__)   # -> PGDialect_psycopg
"
```

SQLAlchemy 2.1 resuelve una URL `postgresql://` contra el dialecto de **psycopg v3**, no
contra `psycopg2`.

### 3.6 Resumen de las comprobaciones

| Comprobación | Resultado | Descarta |
|---|---|---|
| `curl` al endpoint de login | `502` | Que el fallo sea de credenciales |
| `docker logs sira_api` | `ModuleNotFoundError: psycopg` | Que el fallo sea de red o de Nginx |
| Consulta a la tabla `CLIENTE` | 7 usuarios activos | Que falte el seed |
| `bcrypt.checkpw()` sobre los hashes | Todos válidos | Que las contraseñas estén corruptas |
| Dialecto resuelto por SQLAlchemy | `PGDialect_psycopg` | Que el driver instalado sea el usado |

---

## 4. Causa raíz

### 4.1 El detonante: dependencias sin fijar

`backend/requirements.txt` declaraba las dependencias **sin número de versión**:

```
sqlalchemy                  # ORM (Mapeo Objeto-Relacional) para interactuar con SQL desde Python.
psycopg2-binary             # Adaptador/Driver para conectar Python con PostgreSQL.
```

Al reconstruir la imagen «de cero», `pip` instaló la última versión disponible
(**SQLAlchemy 2.1.6**). Entre los cambios de la rama 2.1 está el **cambio del driver por
defecto** para URLs `postgresql://`: pasa de `psycopg2` a **`psycopg` (v3)**.

El proyecto solo instala `psycopg2-binary`, de modo que al crear el motor de base de datos
(`create_engine` en `backend/app/database.py`) SQLAlchemy intenta `import psycopg` y falla.

Nótese que el código y la configuración no cambiaron: **el mismo repositorio funcionaba ayer y
hoy no**, simplemente porque el build resolvió versiones distintas. Es un problema clásico de
**reproducibilidad de builds**.

### 4.2 La cadena de fallo

```
requirements sin pin
        │
        ▼
pip instala SQLAlchemy 2.1.x (driver por defecto = psycopg v3)
        │
        ▼
create_engine("postgresql://...") → import psycopg → ModuleNotFoundError
        │
        ▼
uvicorn --reload: el padre sigue vivo  →  contenedor "Up" pero SIN puerto 8000
        │
        ▼
nginx (location /api/) no alcanza api:8000  →  502 Bad Gateway
        │
        ▼
index.php: cualquier código != 200  →  "CIF o contraseña incorrectos"
```

### 4.3 El agravante: un mensaje de error que oculta la causa

En `frontend/index.php`:

```php
if ($http_code == 200) {
    // ... login correcto ...
} else {
    // Aquí cae un 401, pero también un 502, un 500 o un timeout de red
    $error_msg = "CIF o contraseña incorrectos. Inténtalo de nuevo.";
}
```

Al no distinguir entre «credenciales rechazadas» (`401`) y «servicio no disponible»
(`5xx` / error de conexión), la interfaz apunta a la hipótesis equivocada y alarga el
diagnóstico.

---

## 5. Qué **no** era el problema

Conviene dejarlo escrito para no repetir la búsqueda:

- **No** era la contraseña de `root`: el hash `$2b$12$EEav...` valida contra `root1234`.
- **No** era el seed: los 7 usuarios se insertaron y están `activa = t`.
- **No** era el `char(9)` de la columna `cif`: PostgreSQL compara `bpchar` ignorando los
  espacios de relleno, y `cif = 'root'` devuelve cierto para la fila de root.
- **No** era el volumen de datos: `proyecto_sira_postgres_data` se creó en este arranque y la
  inicialización se ejecutó sin errores.
- **No** era Nginx: el proxy está bien configurado; devuelve 502 porque el upstream no escucha.

---

## 6. Solución

### 6.1 Fijar el driver en la cadena de conexión

**Archivo:** `docker-compose.yml`, servicio `api`

```yaml
environment:
  # Se especifica el driver de forma explícita (psycopg2) en lugar de dejar que
  # SQLAlchemy elija el que crea conveniente según su versión.
  - DATABASE_URL=postgresql+psycopg2://${DB_USER}:${DB_PASSWORD}@db:5432/${DB_NAME}
```

Es la corrección principal: hace que la URL sea **autosuficiente** e independiente de la
versión de SQLAlchemy instalada.

### 6.2 Fijar la versión de SQLAlchemy

**Archivo:** `backend/requirements.txt`

```
# Se limita a la rama 2.0 para evitar que un build futuro instale la 2.1, que cambia
# el driver por defecto de PostgreSQL y rompe el arranque de la API.
sqlalchemy>=2.0,<2.1
```

Con esto, dos builds separados en el tiempo producen el mismo resultado. Idealmente, el resto
de dependencias críticas (`fastapi`, `uvicorn`, `bcrypt`) deberían fijarse igual con
`pip freeze > requirements.txt` una vez validado el entorno.

### 6.3 Reconstruir el servicio afectado

```bash
# Reconstruye solo la imagen de la API y la recrea.
# No es necesario borrar volúmenes: los datos de PostgreSQL se conservan.
docker compose up -d --build api
```

### 6.4 Mejorar el mensaje de error del frontend (recomendado)

**Archivo:** `frontend/index.php`

```php
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);   // Guardamos el error de red ANTES de cerrar el handle
curl_close($ch);

if ($curl_error !== '') {
    // El contenedor de la API no responde: problema de infraestructura, no de credenciales
    $error_msg = "No se puede contactar con el servicio. Inténtalo en unos minutos.";
} elseif ($http_code == 200) {
    // ... flujo de login correcto (sin cambios) ...
} elseif ($http_code == 401) {
    // Solo el 401 significa realmente CIF o contraseña incorrectos
    $error_msg = "CIF o contraseña incorrectos. Inténtalo de nuevo.";
} else {
    // 500, 502, 503... el backend está arrancando o ha fallado
    $error_msg = "El servicio no está disponible en este momento (código $http_code).";
}
```

Regla general: **un mensaje de error debe describir el fallo real**, no el más habitual.

---

## 7. Verificación posterior

```bash
# 1. La API debe responder (401 con credenciales falsas = está viva y consultando la BBDD)
curl -s -o /dev/null -w "%{http_code}\n" \
     -X POST http://localhost:8085/api/auth/token \
     -d "username=root&password=incorrecta"        # esperado: 401

# 2. Login correcto: debe devolver un token JWT
curl -s -X POST http://localhost:8085/api/auth/token \
     -d "username=root&password=root1234" | head -c 200   # esperado: {"access_token":"eyJ...

# 3. El puerto 8000 debe estar a la escucha dentro del contenedor
docker exec sira_api python -c \
  "import socket; s=socket.socket(); print(s.connect_ex(('127.0.0.1',8000)))"   # esperado: 0

# 4. Sin errores de importación en los logs
docker logs --tail 20 sira_api | grep -i "error" || echo "sin errores"
```

Criterio de aceptación: el login con `root` / `root1234` redirige al formulario de cambio de
contraseña (`debe_cambiar_pw = TRUE` en el seed), y no al dashboard.

---

## 8. Prevención

| Medida | Motivo |
|---|---|
| Fijar versiones en `requirements.txt` (o usar `pip freeze`) | Evita que un rebuild cambie el comportamiento sin tocar código. |
| Especificar el driver en `DATABASE_URL` (`+psycopg2`, `+psycopg`) | La cadena de conexión deja de depender de la versión del ORM. |
| No usar `--reload` en la definición de producción del contenedor | El proceso padre enmascara el fallo del worker: el contenedor parece sano. |
| Añadir `healthcheck` al servicio `api` | Docker (y `depends_on`) detectarían que la API no responde, no solo que el contenedor existe. |
| Revisar `docker logs` antes que la interfaz | La interfaz puede estar traduciendo mal el error de fondo. |
| Distinguir 401 de 5xx en los formularios | Ahorra horas de diagnóstico apuntando a la hipótesis equivocada. |

Ejemplo de `healthcheck` para la API (Python ya está en la imagen, no hace falta instalar `curl`):

```yaml
healthcheck:
  test: ["CMD-SHELL", "python -c \"import socket,sys; s=socket.socket(); sys.exit(s.connect_ex(('127.0.0.1',8000)))\""]
  interval: 10s
  timeout: 5s
  retries: 5
```

---

## 9. Anexo: comandos de diagnóstico rápido

```bash
# ¿Están todos los contenedores arriba?
docker compose ps

# ¿Qué código HTTP devuelve realmente la API?
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8085/api/auth/token \
     -d "username=root&password=root1234"

# ¿Por qué no arranca la API?
docker logs --tail 50 sira_api

# ¿Hay algo escuchando en el 8000 dentro del contenedor?
docker exec sira_api python -c \
  "import socket; s=socket.socket(); print('0 = escucha' if s.connect_ex(('127.0.0.1',8000))==0 else 'NO escucha')"

# ¿Existen los usuarios y están activos?
docker exec sira_db psql -U <DB_USER> -d <DB_NAME> \
  -c "select cliente_id, cif, rol, activa from cliente order by cliente_id;"

# ¿Qué driver está usando SQLAlchemy?
docker exec sira_api python -c \
  "from sqlalchemy.dialects import postgresql; print(postgresql.dialect.__name__)"
```

---

## Conclusión

El fallo no tuvo origen en los datos ni en las credenciales, sino en la **falta de
reproducibilidad del build**: una dependencia sin versión fija cambió el driver de PostgreSQL y
dejó la API sin arrancar, mientras el contenedor seguía reportándose como «saludable». El
mensaje del frontend, que unificaba todos los errores bajo «credenciales incorrectas», ocultó
durante el diagnóstico la verdadera naturaleza del problema.

La lección aplicable a cualquier despliegue es doble: **fijar versiones** y **hacer que los
mensajes de error digan la verdad**. Un `502` nunca debería presentarse al usuario como una
contraseña equivocada.
