#!/bin/sh
# =============================================================================
#  sira-healthcheck.sh — Mantiene la BBDD sincronizada con el .env
# =============================================================================
#
# QUÉ HACE
#   Se ejecuta como healthcheck del contenedor sira_db (cada 10 s) y se asegura
#   de que la base de datos acepta EXACTAMENTE las credenciales del .env:
#
#     1. Comprueba la contraseña de POSTGRES_USER contra POSTGRES_DB.
#     2. Si el rol no existe, LO CREA.
#     3. Si el rol existe con otra contraseña, le pone la del .env.
#     4. Si la base no existe, LA CREA (con el esquema y los datos de ejemplo).
#     5. Si los datos pertenecen a otro rol (caso típico: se ha cambiado
#        DB_USER), le TRASPASA LA PROPIEDAD para que la API pueda leerlos.
#
#   Con esto, el .env manda de verdad: se puede poner DB_USER=PepitoSL,
#   DB_PASSWORD=loquesea y DB_NAME=empresa_pepito, y en el siguiente arranque
#   todo cuadra solo. Ya no hay que "acordarse" de nada.
#
# POR QUÉ HACE FALTA
#   La imagen oficial de PostgreSQL solo aplica POSTGRES_USER / POSTGRES_PASSWORD
#   / POSTGRES_DB la PRIMERA vez que inicializa el directorio de datos. Si el
#   volumen ya existe (y los volúmenes sobreviven a `docker compose down`, a
#   borrar contenedores y a borrar la carpeta del proyecto), los valores viejos
#   se quedan para siempre. De ahí el clásico "password authentication failed".
#
# POR QUÉ NO SE PUEDE "LEER" LA CONTRASEÑA DE LA BBDD
#   PostgreSQL no guarda la contraseña, guarda un hash SCRAM-SHA-256:
#       SCRAM-SHA-256$4096:<sal>$<clave_almacenada>:<clave_servidor>
#   Es irreversible por diseño. Por eso el sentido correcto es .env -> BBDD y no
#   BBDD -> .env.
#
# CÓMO SE REPARA SIN CONOCER LA CONTRASEÑA ANTIGUA
#   El pg_hba.conf de la imagen oficial confía ('trust') en las conexiones por
#   socket local: desde dentro del propio contenedor se puede entrar SIN
#   contraseña, solo hace falta saber el nombre de un rol que exista. Ese nombre
#   se guarda en .sira-identidad y, si no, se prueban los candidatos habituales.
#
#   OJO: 127.0.0.1 y ::1 también son 'trust' en ese pg_hba.conf, así que probar
#   la contraseña contra 127.0.0.1 daría un falso OK siempre. La prueba buena es
#   contra la IP propia del contenedor, que sí pasa por scram-sha-256.
#
# QUÉ NECESITA
#   - Ejecutarse DENTRO del contenedor de PostgreSQL (necesita psql y el socket).
#   - POSTGRES_USER, POSTGRES_DB y POSTGRES_PASSWORD (los pone docker-compose).
#   - Los scripts de backend/database/ montados en /docker-entrypoint-initdb.d
#     (solo se usan al crear una base nueva; si no están, la base se crea vacía).
#   - La contraseña y el usuario viajan codificados en base64 dentro de ajustes de
#     sesión (GUC) y se decodifican en SQL: así funciona con cualquier carácter
#     (espacios, @, $, comillas...) sin pelearse con el entrecomillado del shell.
#
# FICHERO DE IDENTIDAD (.sira-identidad)
#   Guarda DB_USER, DB_NAME y la lista de roles que pueden entrar, para poder
#   recuperarse si el .env cambia tanto que ya no se sabe ni con qué usuario se
#   creó el volumen. No guarda la contraseña: solo nombres y fecha.
#
# CÓDIGOS DE SALIDA (los interpreta Docker)
#   0  -> sano: PostgreSQL responde y las credenciales del .env funcionan
#   1  -> arrancando o error: se reintenta en el siguiente ciclo
# =============================================================================

set -u

USUARIO="${POSTGRES_USER:-postgres}"
BASE_DATOS="${POSTGRES_DB:-postgres}"
CLAVE="${POSTGRES_PASSWORD:-}"

SOCKET="/var/run/postgresql"
DIRECTORIO_DATOS="${PGDATA:-/var/lib/postgresql/data}"
ARCHIVO_IDENTIDAD="$DIRECTORIO_DATOS/.sira-identidad"
DIRECTORIO_INICIALIZACION="/docker-entrypoint-initdb.d"

# ── Utilidades ───────────────────────────────────────────────────────────────

# Códigos base64 de usuario y contraseña, sin saltos de línea.
b64() { printf '%s' "$1" | base64 | tr -d '\n'; }

# Guarda/actualiza la identidad del volumen. Solo escribe si algo ha cambiado,
# para no estar tocando el disco cada 10 segundos.
registrar_identidad() {
    ROLES_CONOCIDOS="$(psql -h "$SOCKET" -U "$ADMIN" -d postgres -tAc \
        "select string_agg(rolname, ' ') from pg_roles
          where rolcanlogin and rolname not like 'pg\_%'" 2>/dev/null | tr -s ' ')"

    CONTENIDO="$(printf '# Identidad del volumen de datos de SIRA\n# Escrito automaticamente por si algun dia se pierde el .env.\nDB_USER=%s\nDB_NAME=%s\nROLES=%s\nFECHA=%s\n' \
        "$USUARIO" "$BASE_DATOS" "${ROLES_CONOCIDOS:-}" "$(date -u '+%Y-%m-%dT%H:%M:%SZ')")"

    if [ ! -f "$ARCHIVO_IDENTIDAD" ] || [ "$(cat "$ARCHIVO_IDENTIDAD" 2>/dev/null)" != "$CONTENIDO" ]; then
        printf '%s\n' "$CONTENIDO" > "$ARCHIVO_IDENTIDAD" 2>/dev/null || true
    fi
}

# Muestra la identidad guardada para que el diagnóstico sea accionable.
sugerir_identidad() {
    if [ -f "$ARCHIVO_IDENTIDAD" ]; then
        echo "sira-healthcheck: este volumen se creo con:" >&2
        grep -E '^DB_(USER|NAME)=' "$ARCHIVO_IDENTIDAD" | sed 's/^/sira-healthcheck:   /' >&2
        echo "sira-healthcheck: copia esos valores en el .env y vuelve a arrancar." >&2
    fi
}

# ¿Se puede conectar por TCP (scram de verdad) con el usuario y contraseña dados?
# Esta es la ÚNICA prueba válida: por 127.0.0.1 siempre diría que sí.
credenciales_validas() {
    [ -n "$CLAVE" ] || return 1
    PGPASSWORD="$CLAVE" psql -h "${IP_PROPIA:-db}" -U "$USUARIO" -d "$BASE_DATOS" \
        -tAc 'select 1' >/dev/null 2>&1
}

# ¿Está TODO en orden? No basta con que la contraseña funcione: si el rol no
# puede leer las tablas, la API conectaría pero fallaría con "permission denied",
# que es aún más confuso que un error de login. Por eso se comprueba también que
# pueda leer todas las tablas, usar las secuencias y crear tablas nuevas.
estado_correcto() {
    credenciales_validas || return 1
    PGPASSWORD="$CLAVE" psql -h "${IP_PROPIA:-db}" -U "$USUARIO" -d "$BASE_DATOS" -tAc "
        SELECT has_schema_privilege(current_user, 'public', 'CREATE')
           AND (SELECT count(*) FROM pg_class c
                  JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = 'public'
                   AND c.relkind IN ('r', 'p', 'v', 'm', 'S')
                   AND NOT CASE WHEN c.relkind = 'S'
                                THEN has_sequence_privilege(current_user, c.oid, 'USAGE')
                                ELSE has_table_privilege(current_user, c.oid, 'SELECT')
                           END) = 0
    " 2>/dev/null | grep -qx 't'
}

# ── FASE 1. ¿PostgreSQL está levantado? ──────────────────────────────────────
# pg_isready solo mira el socket/servicio, no autentica: sirve para distinguir
# "todavía arrancando" de "arrancado pero con otras credenciales".
if ! pg_isready -q -h "$SOCKET" -U "$USUARIO" -d "$BASE_DATOS"; then
    exit 1
fi

IP_PROPIA="$(hostname -i 2>/dev/null | awk '{print $1}')"
[ -n "$IP_PROPIA" ] || IP_PROPIA="db"

# ── FASE 2. ¿Ya está todo en orden? (el caso normal, cada 10 s) ──────────────
if estado_correcto; then
    ADMIN="$USUARIO"
    registrar_identidad
    exit 0
fi

# ── FASE 3. Reparación ───────────────────────────────────────────────────────
echo "sira-healthcheck: las credenciales del .env no funcionan todavia; reparando." >&2

# 3.1 Buscar un rol con el que entrar por el socket local (trust).
#     Candidatos por orden: el que dejó anotado la última vez que todo fue bien,
#     "postgres" (por si el volumen viene de otra instalación) y el propio
#     usuario del .env.
#
#     Se prefiere un SUPERUSUARIO: para crear roles, cambiar contraseñas ajenas o
#     conceder permisos hace falta serlo. Si se eligiera el primero que conecta
#     (que puede ser un rol normal), la reparación se quedaría a medias con
#     "permission denied to alter role".
CANDIDATOS="postgres $USUARIO"
if [ -f "$ARCHIVO_IDENTIDAD" ]; then
    CANDIDATOS="$(sed -n 's/^DB_USER=//p;s/^ROLES=//p' "$ARCHIVO_IDENTIDAD") $CANDIDATOS"
fi

ADMIN=""
ADMIN_SIN_PRIVILEGIOS=""
for candidato in $CANDIDATOS; do
    if psql -h "$SOCKET" -U "$candidato" -d postgres -tAc 'select 1' >/dev/null 2>&1; then
        if [ "$(psql -h "$SOCKET" -U "$candidato" -d postgres -tAc \
                "select rolsuper from pg_roles where rolname = current_user" 2>/dev/null)" = "t" ]; then
            ADMIN="$candidato"
            break
        fi
        [ -n "$ADMIN_SIN_PRIVILEGIOS" ] || ADMIN_SIN_PRIVILEGIOS="$candidato"
    fi
done

if [ -z "$ADMIN" ]; then
    if [ -n "$ADMIN_SIN_PRIVILEGIOS" ]; then
        echo "sira-healthcheck: el unico rol accesible es '$ADMIN_SIN_PRIVILEGIOS', que no es superusuario." >&2
        echo "sira-healthcheck: se intentara la reparacion, pero probablemente falle por permisos." >&2
        ADMIN="$ADMIN_SIN_PRIVILEGIOS"
    else
        echo "sira-healthcheck: no hay ningun rol conocido con el que entrar en esta BBDD." >&2
        sugerir_identidad
        echo "sira-healthcheck: opciones: corregir DB_USER en el .env, o empezar de cero con 'docker compose down -v'." >&2
        exit 1
    fi
fi

if [ "$ADMIN" != "$USUARIO" ]; then
    echo "sira-healthcheck: entrando por el socket local como '$ADMIN'." >&2
fi

if [ -z "$CLAVE" ]; then
    echo "sira-healthcheck: POSTGRES_PASSWORD esta vacia en el .env; no se puede sincronizar." >&2
    sugerir_identidad
    exit 1
fi

# 3.2 Crear el rol si no existe y ponerle la contraseña del .env.
#     PL/pgSQL con format('%I') / format('%L') escapa nombre y clave, así que
#     funciona con cualquier carácter y sin riesgo de inyección.
#
#     OJO al comprobar el resultado: hay que mirar el CÓDIGO DE SALIDA, no si la
#     salida está vacía. psql escribe los NOTICE ("rol creado") por stderr, y
#     tomarlos por error abortaría la reparación a medias. Además se silencian
#     con client_min_messages para no ensuciar el diagnóstico.
ERROR_ROL="$(PGOPTIONS="-c client_min_messages=warning -c sira.usuario64=$(b64 "$USUARIO") -c sira.clave64=$(b64 "$CLAVE")" \
    psql -h "$SOCKET" -U "$ADMIN" -d postgres -v ON_ERROR_STOP=1 -tAc \
    "DO \$\$
     DECLARE
         v_usuario text := convert_from(decode(current_setting('sira.usuario64'), 'base64'), 'UTF8');
         v_clave   text := convert_from(decode(current_setting('sira.clave64'),   'base64'), 'UTF8');
     BEGIN
         IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = v_usuario) THEN
             -- SUPERUSER igual que hace la imagen oficial con POSTGRES_USER en
             -- una instalación nueva. Así el rol puede administrar su propia
             -- base y las reparaciones futuras no se quedan sin permisos.
             EXECUTE format('CREATE ROLE %I LOGIN SUPERUSER', v_usuario);
         END IF;
         EXECUTE format('ALTER ROLE %I WITH LOGIN PASSWORD %L', v_usuario, v_clave);
     END \$\$;" 2>&1 >/dev/null)"
ESTADO_ROL=$?

if [ "$ESTADO_ROL" -ne 0 ]; then
    echo "sira-healthcheck: no se pudo crear/actualizar el rol '$USUARIO'." >&2
    echo "sira-healthcheck:   $ERROR_ROL" >&2
    sugerir_identidad
    exit 1
fi

# 3.3 Crear la base de datos si no existe (con esquema y datos de ejemplo).
#     Se avisa por el log: es un cambio importante y no debe pasar desapercibido.
#
#     OJO con psql: los -c NO interpolan variables (:'base'), así que el nombre va
#     en un GUC codificado en base64, igual que la contraseña. Para el CREATE
#     DATABASE se genera la sentencia con format() (que escapa el identificador)
#     y se ejecuta en una segunda llamada, porque CREATE DATABASE no puede ir
#     dentro de un bloque DO.
EXISTE_BASE="$(PGOPTIONS="-c sira.base64=$(b64 "$BASE_DATOS")" \
    psql -h "$SOCKET" -U "$ADMIN" -d postgres -tAc "
        SELECT 1 FROM pg_database
         WHERE datname = convert_from(decode(current_setting('sira.base64'), 'base64'), 'UTF8')
    " 2>/dev/null)"

if [ "$EXISTE_BASE" != "1" ]; then
    echo "sira-healthcheck: la base '$BASE_DATOS' no existe; creandola." >&2

    SQL_CREAR="$(PGOPTIONS="-c sira.base64=$(b64 "$BASE_DATOS") -c sira.duenio64=$(b64 "$USUARIO")" \
        psql -h "$SOCKET" -U "$ADMIN" -d postgres -tAc "
            SELECT format('CREATE DATABASE %I OWNER %I',
                          convert_from(decode(current_setting('sira.base64'),   'base64'), 'UTF8'),
                          convert_from(decode(current_setting('sira.duenio64'), 'base64'), 'UTF8'))
        " 2>/dev/null)"

    if [ -z "$SQL_CREAR" ] || \
       ! printf '%s\n' "$SQL_CREAR" | psql -h "$SOCKET" -U "$ADMIN" -d postgres \
            -v ON_ERROR_STOP=1 -q >/dev/null 2>&1; then
        echo "sira-healthcheck: no se pudo crear la base '$BASE_DATOS'." >&2
        exit 1
    fi

    # Mismos scripts que usaría una instalación desde cero. Se ejecutan COMO EL
    # USUARIO DEL .ENV (no como el administrador) para que las tablas queden a su
    # nombre desde el principio y no haga falta ningún permiso extra.
    # Si no está montado el directorio, la base se queda vacía y la API creará las
    # tablas al arrancar (sin datos de ejemplo).
    for script in "$DIRECTORIO_INICIALIZACION/10-schema.sql" "$DIRECTORIO_INICIALIZACION/20-data.sql"; do
        if [ -f "$script" ]; then
            if ! psql -h "$SOCKET" -U "$USUARIO" -d "$BASE_DATOS" -v ON_ERROR_STOP=1 \
                    -f "$script" >/dev/null 2>&1; then
                echo "sira-healthcheck: aviso: no se pudo aplicar $script como '$USUARIO'; reintentando como '$ADMIN'." >&2
                psql -h "$SOCKET" -U "$ADMIN" -d "$BASE_DATOS" -v ON_ERROR_STOP=1 \
                    -f "$script" >/dev/null 2>&1 || \
                    echo "sira-healthcheck: aviso: $script tampoco se pudo aplicar como '$ADMIN'." >&2
            fi
        else
            echo "sira-healthcheck: aviso: no encuentro $script; la base queda vacia." >&2
        fi
    done
fi

# 3.4 Dar permisos al rol del .env sobre los datos que ya existen.
#     Se ejecuta SIEMPRE (también justo después de crear la base): es idempotente
#     y cubre el caso de que los scripts se hayan aplicado como el administrador.
#
#     No se traspasa la PROPIEDAD de las tablas a propósito: PostgreSQL lo prohíbe
#     si hay extensiones implicadas ("cannot reassign ownership of objects ...
#     required by the database system", que es lo que pasa con unaccent). Se
#     conceden privilegios, igual de efectivo para que la API lea y escriba, y no
#     cambia quién es el dueño de nada.
SALIDA_PERMISOS="$(PGOPTIONS="-c sira.usuario64=$(b64 "$USUARIO")" \
    psql -h "$SOCKET" -U "$ADMIN" -d "$BASE_DATOS" -v ON_ERROR_STOP=1 -tA 2>&1 >/dev/null <<'SQL'
DO $$
DECLARE
    v_usuario text := convert_from(decode(current_setting('sira.usuario64'), 'base64'), 'UTF8');
BEGIN
    EXECUTE format('GRANT USAGE, CREATE ON SCHEMA public TO %I', v_usuario);
    EXECUTE format('GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO %I', v_usuario);
    EXECUTE format('GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO %I', v_usuario);
    EXECUTE format('GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO %I', v_usuario);
END $$;
SQL
)"
if [ -n "$SALIDA_PERMISOS" ]; then
    echo "sira-healthcheck: aviso al conceder permisos: $SALIDA_PERMISOS" >&2
fi

registrar_identidad

# ── FASE 4. Verificación final: las credenciales tienen que servir de verdad ─
if estado_correcto; then
    echo "sira-healthcheck: BBDD sincronizada con el .env (usuario '$USUARIO', base '$BASE_DATOS')." >&2
    exit 0
fi

echo "sira-healthcheck: se aplicaron los cambios pero la verificacion por red fallo." >&2
sugerir_identidad
exit 1
