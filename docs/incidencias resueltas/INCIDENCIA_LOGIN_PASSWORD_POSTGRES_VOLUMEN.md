# Incidencia: «No se puede contactar con el servicio» en el login (contraseña del volumen de PostgreSQL)

**Proyecto:** SIRA — Sistema Integral de Riego Automático
**Fecha de la incidencia:** 30/10/2026
**Componentes afectados:** `sira_db` (PostgreSQL), `sira_api` (FastAPI + SQLAlchemy), `sira_frontend` (PHP), `frontend/includes/config.php`
**Severidad:** Alta (nadie puede iniciar sesión)
**Estado:** Resuelta — causa identificada, corregida en el entorno y **con autocorrección permanente** (`backend/scripts/sira-healthcheck.sh`); instalación verificada como portátil (cualquier carpeta y cualquier sistema operativo)

---

## Resumen

El formulario de acceso mostraba el aviso **«No se puede contactar con el servicio. Inténtalo en
unos minutos»**. Ese mensaje lo genera `frontend/index.php` cuando `curl` **no llega ni a
conectar** con la API (`curl_errno != 0`); es decir, no es un problema de credenciales.

Cadena real del fallo:

1. El contenedor `sira_api` arrancaba y aparecía como `Up`.
2. Al importar el código, `models.Base.metadata.create_all(bind=engine)` intentaba conectar con
   PostgreSQL y **fallaba la autenticación**:
   `FATAL: password authentication failed for user "juan_risueno"`.
3. La excepción mataba el proceso hijo de `uvicorn`. Como el `CMD` llevaba `--reload`, el proceso
   padre (el vigilante de ficheros) **seguía vivo**: contenedor «Up», nada escuchando en el 8000 y
   el `restart: unless-stopped` de Docker sin dispararse nunca.
4. Nginx devolvía error de conexión y el PHP lo traducía como «no se puede contactar con el
   servicio».

La causa de fondo: **la contraseña del `.env` ya no coincidía con la del rol de PostgreSQL**.

---

## 1. Por qué la contraseña deja de coincidir

`docker-compose.yml` pasa `POSTGRES_PASSWORD` desde el `.env`, pero **PostgreSQL solo aplica esa
variable la primera vez que inicializa el directorio de datos**. Si el volumen ya contiene una base
de datos, el arranque lo dice explícitamente:

```
PostgreSQL Database directory appears to contain a database; Skipping initialization
```

A partir de ahí, cambiar `DB_PASSWORD` en el `.env` no cambia nada dentro de PostgreSQL: la API
intenta entrar con la contraseña nueva y el servidor sigue teniendo la vieja.

Y aquí está el detalle que hace perder días: **`docker compose down`, borrar los contenedores o
borrar la carpeta del proyecto no elimina los volúmenes nombrados**. `postgres_data` es un volumen
de Docker y sobrevive a todo eso, así que «empezar de cero» volviendo a descargar el proyecto
reproduce el fallo exactamente igual.

### Por qué la comprobación habitual engaña

Si se prueba la contraseña desde dentro del contenedor de la BBDD por el socket local, **siempre
sale OK**, incluso con una contraseña incorrecta, porque el `pg_hba.conf` de la imagen oficial
confía en las conexiones locales:

```
local   all   all                      trust      <-- no comprueba contraseña
host    all   all   127.0.0.1/32       trust      <-- no comprueba contraseña
host    all   all   all                 scram-sha-256   <-- aquí SÍ se comprueba
```

Solo las conexiones que llegan **por red desde otro contenedor** (la API) pasan por
`scram-sha-256`. Es decir, la prueba válida es ejecutarla **desde el contenedor `sira_api`**, no
desde `sira_db`.

---

## 2. Diagnóstico (comandos que identifican el fallo)

```bash
# ¿La API escucha de verdad? Un contenedor "Up" no lo garantiza.
docker exec sira_api python -c \
  "import socket; s=socket.socket(); print(0 if s.connect_ex(('127.0.0.1',8000))==0 else 'NO escucha')"

# ¿Qué dice la API al arrancar?
docker logs sira_api --tail 40

# ¿Qué dice PostgreSQL? (fecha/hora del rechazo y línea de pg_hba aplicada)
docker logs sira_db | grep -iE "FATAL|authentication failed|Skipping initialization"

# Prueba REAL de la contraseña: desde el contenedor de la API y por red.
docker exec -e PGPASSWORD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2-)" sira_api python -c "
import os, psycopg2
psycopg2.connect(host='db', port=5432, user=os.environ.get('DB_USER','juan_risueno'),
                 password=os.environ['PGPASSWORD'], dbname='sira_db')
print('CONEXION OK')"
```

Síntoma en los logs (la huella exacta de esta incidencia):

```
sqlalchemy.exc.OperationalError: (psycopg2.OperationalError) connection to server at "db"
(172.19.0.3), port 5432 failed: FATAL:  password authentication failed for user "juan_risueno"
```

---

## 3. Solución aplicada (sin perder datos)

Alinear la contraseña del rol con la del `.env`. Se hace **por el socket local** (donde `pg_hba`
confía), así que no hace falta recordar la contraseña antigua:

```bash
# La contraseña viaja como ajuste de sesión (GUC) y PL/pgSQL la inserta con %L,
# que la entrecomilla y escapa sola: no hay que construir SQL con comillas ni
# queda la clave en el historial del shell.
docker exec -e PGOPTIONS="-c sira.pw=$DB_PASSWORD" \
  sira_db psql -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 -tAc \
  "DO \$\$ BEGIN EXECUTE format('ALTER USER %I WITH PASSWORD %L', current_user, current_setting('sira.pw')); END \$\$;"

# Y reiniciar la API para que vuelva a conectar
docker restart sira_api
```

Verificación (la cadena completa, de abajo arriba):

```bash
./scripts/diagnostico_sira.sh
```

Salida esperada:

```
1) Contenedores ................ [ OK ]
2) La API escucha en 8000 ...... [ OK ]
3) Autenticación API -> BBDD ... [ OK ]
4) Login a través de Nginx ..... [ OK ]  (401 con credenciales falsas)
```

### Alternativa destructiva (solo si no hay datos que conservar)

```bash
docker compose down -v      # la -v borra los VOLÚMENES, no solo los contenedores
docker compose up -d --build
```

`-v` es lo que faltaba cuando se «borró todo»: sin esa opción el volumen `postgres_data`
sobrevive y PostgreSQL nunca se reinicializa. Al reinicializarse, los scripts
`backend/database/10-schema.sql` y `20-data.sql` se ejecutan de nuevo y recargan los datos de
prueba (contraseña única `sol1234` para los clientes de ejemplo).

---

## 4. Corrección definitiva: la BBDD se sincroniza sola

La solución manual del punto 3 arregla el síntoma una vez. Para que **no vuelva a pasar nunca**
hay un healthcheck propio dentro del contenedor de PostgreSQL:
`backend/scripts/sira-healthcheck.sh`.

En cada arranque (y cada 10 s mientras el contenedor esté vivo) hace esto:

1. Comprueba que PostgreSQL responde (`pg_isready` por socket).
2. Prueba las credenciales del `.env` **contra la IP propia del contenedor** (no contra
   `127.0.0.1`, que es `trust` y daría un falso OK).
3. Si el rol no existe, **lo crea**; si existe con otra contraseña, **se la cambia**.
4. Si la base no existe, **la crea** aplicando `10-schema.sql` y `20-data.sql` como el propio
   usuario (es decir, con los datos de ejemplo y las tablas ya a su nombre).
5. Si los datos existen pero son de otro rol (caso típico: se ha cambiado `DB_USER`), **concede
   los permisos** necesarios (`GRANT`) al rol nuevo.
6. Verifica que todo funciona por red y solo entonces se declara sano.

Consecuencias prácticas:

| Situación | Qué pasa ahora |
|---|---|
| Cambias `DB_PASSWORD` en el `.env` | `docker compose up -d` realinea la contraseña y el login funciona. |
| Cambias `DB_USER` (p. ej. `PepitoSL`) | Se crea el rol y se le dan permisos sobre los datos existentes: **se conservan los clientes y parcelas**. |
| Cambias `DB_NAME` | Se crea esa base con el esquema y los datos de ejemplo. La base anterior sigue intacta. |
| No te acuerdas de las credenciales | No hace falta: el valor del `.env` es el que vale, y es el que ves con `grep DB_ .env`. |
| Instalas en otro equipo con el mismo volumen | Se alinea igual, sin tocar nada a mano. |
| El `.env` no existe o está incompleto | Se usan valores por defecto idénticos en la BBDD y en la API (`${DB_...:-...}`), así que el login sigue funcionando. |

> **Por qué el sentido es `.env` → BBDD y no al revés.** PostgreSQL no guarda la contraseña:
> guarda un hash `SCRAM-SHA-256$4096:<sal>$<clave>:<clave_servidor>`, que es irreversible por
> diseño. De la base de datos **no se puede sacar la clave en claro**, ni siquiera como superusuario.
> Por eso la única fuente de verdad posible es el `.env`, y la BBDD se limita a obedecerlo.

### Si además se pierde el `.env`

La contraseña no es recuperable, pero **sí el usuario y el nombre de la base**: cuando la
comprobación sale bien, el healthcheck deja escrito `DB_USER`, `DB_NAME` y la lista de roles
válidos en un fichero dentro del propio volumen (`/var/lib/postgresql/data/.sira-identidad`, sin
contraseña). Si algún día no hay forma de entrar, el error del healthcheck lo dice:

```
sira-healthcheck: no hay ningun rol conocido con el que entrar en esta BBDD.
sira-healthcheck: este volumen se creo con:
sira-healthcheck:   DB_USER=juan_risueno
sira-healthcheck:   DB_NAME=sira_db
sira-healthcheck: copia esos valores en el .env y vuelve a arrancar.
```

### Otros cambios para que el fallo sea visible y no silencioso

| Cambio | Por qué |
|---|---|
| `backend/Dockerfile`: eliminado `--reload` del `CMD` | Con `--reload` hay un proceso padre que sobrevive a la muerte del hijo: el contenedor parece sano aunque la API esté muerta. Sin él, un fallo de arranque mata el contenedor, Docker lo reintenta y el fallo se ve en `docker ps` («Restarting») y en los logs. |
| `docker-compose.yml`: `healthcheck` en `api` | `docker ps` muestra `(healthy)` / `(unhealthy)` de verdad, en lugar de un «Up» engañoso. |
| `scripts/diagnostico_sira.sh` | Diagnóstico de la cadena completa en un comando; `--reparar` fuerza el reinicio de `sira_db` para que su healthcheck realinee la clave. |
| `backend/app/database.py`: `URL.create()` en vez de `quote_plus()` | `quote_plus` convierte el espacio en `+` y SQLAlchemy lo lee como carácter literal: una contraseña con espacios («Clave Rara») fallaba con `password authentication failed` aunque fuera correcta. Detectado al probar contraseñas «raras». |

---

## 5. Portabilidad: la ruta y el sistema operativo dejan de importar

El nombre de los volúmenes lo deducía Docker del **nombre de la carpeta**, así que mover el
proyecto creaba una base de datos nueva y vacía. Corregido con nombres explícitos:

| Antes | Ahora |
|---|---|
| `proyecto_sira_postgres_data` (según carpeta) | `sira_postgres_data` (`name:` explícito) |
| `proyecto_sira_sira_security_history` | `sira_security_history` |
| `proyecto_sira_sira-network` | `sira_network` |
| Proyecto `proyecto_sira` / `sira` (según carpeta) | `sira` (`name:` en la cabecera del compose) |

Los datos existentes se migraron copiando el contenido del volumen antiguo al nuevo
(`cp -a` como root, para conservar el propietario `uid 70` de PostgreSQL), sin pérdida de datos.

Comprobado en la práctica copiando el proyecto a `/tmp/opencode/otra_ruta_SIRA` y levantándolo
desde ahí: mismos contenedores, mismo volumen, **mismos 7 clientes y login correcto**.

Notas para Windows (`C:\...`):

- Los montajes son relativos, así que funcionan igual en Docker Desktop.
- El healthcheck se invoca con `sh /usr/local/bin/...` a propósito: en un bind mount de Windows
  el fichero puede llegar sin permiso de ejecución.
- `.gitattributes` fuerza finales de línea `LF` en `.sh`, `.yml` y `.env`. Un `.sh` con `CRLF`
  no arranca, y un `.env` con `CRLF` mete un `\r` invisible en la contraseña.
- Evita el carácter `$` en `DB_PASSWORD`: Docker Compose lo interpreta como inicio de variable
  (`Clave$x` → aviso «variable not set» y valor recortado). Si hace falta, se escribe `$$`.

---

## 6. Volúmenes antiguos que siguen en el equipo

Tras la migración quedan los volúmenes de instalaciones anteriores. Ya no los usa nadie, pero
**no se borran solos y conviene revisarlos antes de eliminarlos**:

```bash
docker volume ls | grep -i sira
docker run --rm -v sira_project_postgres_data:/datos alpine ls -l /datos   # ¿hay datos dentro?
docker volume rm sira_project_postgres_data sira_project_sira_security_history
docker volume rm proyecto_sira_postgres_data proyecto_sira_sira_security_history
```

---

## Conclusión

El fallo no estaba ni en el código de login ni en las credenciales: estaba en un **volumen de
PostgreSQL que sobrevivió a todos los «borrados»** y conservaba una contraseña distinta a la del
`.env`. Lo que lo hizo tan difícil de ver fue la combinación de dos engaños: un `pg_hba.conf` que
acepta cualquier contraseña en local (falso positivo en las pruebas) y un `uvicorn --reload` que
mantenía el contenedor «Up» con la API muerta (falso positivo en `docker ps`).

La lección de diseño: cuando un dato se guarda en dos sitios (`.env` y volumen), hay que decidir
cuál manda y **hacer que el otro se sincronice solo**. Aquí manda el `.env`, porque la contraseña
de PostgreSQL es un hash irreversible y no se puede recuperar desde la base de datos.

Y la regla práctica: **«he borrado los contenedores» no es «he borrado el stack»**. Los datos
viven en los volúmenes, y ahí es donde hay que mirar primero.
