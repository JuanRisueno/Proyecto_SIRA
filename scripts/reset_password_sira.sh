#!/usr/bin/env bash
# =============================================================================
# reset_password_sira.sh — Restablece la contraseña de un usuario de SIRA
# -----------------------------------------------------------------------------
# QUÉ HACE
#   Cambia el hash de la contraseña de un usuario (identificado por su CIF) en
#   la base de datos PostgreSQL del contenedor `sira_db`, sin borrar el volumen
#   ni volver a inicializar la base.
#
# POR QUÉ HACE FALTA
#   Los usuarios semilla (root, admin, clientes) solo se insertan la primera vez
#   que se crea el volumen. Después, el `20-data.sql` ya no se aplica y la
#   contraseña real vive únicamente dentro del volumen.
#
# CÓMO SE USA
#   ./scripts/reset_password_sira.sh <CIF> [--sin-cambio-obligatorio]
#
#   Ejemplos:
#     ./scripts/reset_password_sira.sh root
#     ./scripts/reset_password_sira.sh admin --sin-cambio-obligatorio
#
#   Documentación completa: docs/infraestructura/reset_password.md
#
# QUÉ NECESITA
#   - Docker en marcha y los contenedores `sira_api` y `sira_db` levantados.
#   - Un terminal interactivo (la contraseña se pide por teclado, no por parámetro).
#   - El archivo .env del proyecto (de ahí se leen DB_USER y DB_NAME).
# =============================================================================

# -e: aborta si un comando falla | -u: error si se usa una variable vacía
# -o pipefail: si falla un comando dentro de una tubería, falla toda la línea.
# Cuidado: con -e, cualquier orden que devuelva error corta el script EN SILENCIO.
# Por eso más abajo todas las llamadas críticas se envuelven en "if ! ...".
set -euo pipefail

# IFS restrictivo: evita que un espacio dentro de un CIF o una ruta rompa los
# argumentos. Consecuencia: los bucles `for x in $lista` separan solo por salto
# de línea o tabulador, no por espacio.
IFS=$'\n\t'

# Ruta del proyecto: se deduce subiendo un nivel desde la carpeta del script
# (scripts/ -> raíz del repo). Funciona aunque muevas el proyecto de sitio.
# Se puede forzar con la variable de entorno SIRA_DIR.
SIRA_DIR="${SIRA_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

CONTENEDOR_API="sira_api"
CONTENEDOR_DB="sira_db"

# -----------------------------------------------------------------------------
# 0. Argumentos
# -----------------------------------------------------------------------------
CIF=""
MODO_CAMBIO=""

# Se recorre cada argumento para aceptar el flag en cualquier posición.
for argumento in "$@"; do
    case "$argumento" in
        --sin-cambio-obligatorio) MODO_CAMBIO="--sin-cambio-obligatorio" ;;
        -h|--help)
            echo "Uso: $0 <CIF> [--sin-cambio-obligatorio]"
            echo "  <CIF>                      Identificador del usuario (columna cliente.cif)."
            echo "  --sin-cambio-obligatorio   No obliga a cambiar la contraseña al entrar."
            exit 0
            ;;
        -*)
            echo "ERROR: opción desconocida: $argumento" >&2
            echo "       Uso: $0 <CIF> [--sin-cambio-obligatorio]" >&2
            exit 1
            ;;
        *)
            if [[ -n "$CIF" ]]; then
                echo "ERROR: solo se admite un CIF (recibidos '$CIF' y '$argumento')." >&2
                exit 1
            fi
            CIF="$argumento"
            ;;
    esac
done

if [[ -z "$CIF" ]]; then
    echo "ERROR: falta el CIF del usuario." >&2
    echo "       Uso: $0 <CIF> [--sin-cambio-obligatorio]" >&2
    exit 1
fi

# El CIF se incrusta en una sentencia SQL, así que se valida antes: solo letras
# y números, máximo 9 caracteres (la columna es char(9)). Esto impide cualquier
# intento de inyección SQL a través de este parámetro.
if [[ ! "$CIF" =~ ^[A-Za-z0-9]{1,9}$ ]]; then
    echo "ERROR: CIF no válido: '$CIF'." >&2
    echo "       Debe tener entre 1 y 9 caracteres alfanuméricos (sin guiones ni comillas)." >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 1. Leer DB_USER y DB_NAME del .env SIN ejecutarlo
# -----------------------------------------------------------------------------
# Antes se usaba `source .env`, pero eso ejecuta el archivo como código bash: un
# carácter especial en DB_PASSWORD ($, !, #, comillas) puede romper el script o
# hacer cosas inesperadas. Aquí solo se extraen los dos valores que necesitamos.
ENV_FILE="$SIRA_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: no encuentro $ENV_FILE" >&2
    exit 1
fi

leer_variable_env() {
    # Toma la ÚLTIMA aparición de la clave (así gana un valor real sobre uno comentado),
    # quita el prefijo 'CLAVE=', los retornos de carro de Windows y las comillas envolventes.
    awk -F'=' -v clave="$1" '$1 == clave { valor = substr($0, index($0, "=") + 1) } END { print valor }' "$ENV_FILE" \
        | tr -d '\r' \
        | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'
}

DB_USER="$(leer_variable_env DB_USER)"
DB_NAME="$(leer_variable_env DB_NAME)"

if [[ -z "$DB_USER" || -z "$DB_NAME" ]]; then
    echo "ERROR: faltan DB_USER o DB_NAME en $ENV_FILE" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 2. Comprobar Docker y los contenedores
# -----------------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    echo "ERROR: 'docker' no está instalado o no está en el PATH." >&2
    exit 1
fi

for contenedor in "$CONTENEDOR_API" "$CONTENEDOR_DB"; do
    # `docker inspect` falla si el contenedor no existe; se captura sin abortar
    # para poder dar un mensaje claro en lugar de morir en silencio por `set -e`.
    estado="$(docker inspect -f '{{.State.Running}}' "$contenedor" 2>/dev/null || echo "no-existe")"
    if [[ "$estado" != "true" ]]; then
        echo "ERROR: el contenedor $contenedor no está en marcha (estado: $estado)." >&2
        echo "       Levántalo con: cd $SIRA_DIR && docker compose up -d" >&2
        exit 1
    fi
done

# -----------------------------------------------------------------------------
# 3. Comprobar que el usuario existe ANTES de pedir la contraseña
# -----------------------------------------------------------------------------
# Se consulta primero para no hacer teclear dos veces una clave que luego no se
# podría guardar. -tA = sin cabecera ni formato, solo el valor.
if ! EXISTE="$(docker exec "$CONTENEDOR_DB" psql -U "$DB_USER" -d "$DB_NAME" -tAc \
        "SELECT COUNT(*) FROM cliente WHERE cif = '$CIF';" 2>&1)"; then
    echo "ERROR: no se pudo consultar la base de datos." >&2
    echo "$EXISTE" >&2
    exit 1
fi

if [[ "$EXISTE" != "1" ]]; then
    echo "AVISO: no existe ningún usuario con CIF '$CIF'. No se ha cambiado nada." >&2
    echo "       Usuarios disponibles:" >&2
    docker exec "$CONTENEDOR_DB" psql -U "$DB_USER" -d "$DB_NAME" -tAc \
        "SELECT '  - ' || cif || ' (' || rol || ')' FROM cliente ORDER BY cliente_id;" >&2 || true
    exit 1
fi

# -----------------------------------------------------------------------------
# 4. Pedir la contraseña sin mostrarla por pantalla
# -----------------------------------------------------------------------------
# -s: no echo (la clave no se ve) | -p: prompt | -r: no interpretar '\'
if ! read -rsp "Nueva contraseña para '$CIF': " PASSWORD; then
    echo >&2
    echo "ERROR: no se pudo leer la contraseña (¿se está ejecutando sin terminal?)." >&2
    exit 1
fi
echo

if ! read -rsp "Repite la contraseña: " PASSWORD_2; then
    echo >&2
    echo "ERROR: no se pudo leer la confirmación." >&2
    exit 1
fi
echo

if [[ "$PASSWORD" != "$PASSWORD_2" ]]; then
    echo "ERROR: las contraseñas no coinciden." >&2
    exit 1
fi

if [[ ${#PASSWORD} -lt 8 ]]; then
    echo "ERROR: la contraseña debe tener al menos 8 caracteres." >&2
    exit 1
fi

# Bcrypt solo usa los primeros 72 bytes; avisar evita una falsa sensación de
# seguridad en contraseñas muy largas.
if [[ $(printf '%s' "$PASSWORD" | wc -c) -gt 72 ]]; then
    echo "ERROR: la contraseña supera los 72 bytes que admite Bcrypt." >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 5. Generar el hash bcrypt dentro del contenedor de la API
# -----------------------------------------------------------------------------
# Se hace ahí porque es la imagen que ya tiene instalado el módulo `bcrypt`.
# La contraseña viaja por stdin (no como argumento), así no aparece en `ps`.
if ! HASH="$(printf '%s' "$PASSWORD" | docker exec -i "$CONTENEDOR_API" python -c '
import sys, bcrypt
clave = sys.stdin.read().encode("utf-8")
print(bcrypt.hashpw(clave, bcrypt.gensalt(rounds=12)).decode())
' 2>&1)"; then
    echo "ERROR: no se pudo generar el hash con bcrypt dentro de $CONTENEDOR_API." >&2
    echo "$HASH" >&2
    echo "       Comprueba que la API arranca bien: docker logs --tail 50 $CONTENEDOR_API" >&2
    exit 1
fi

if [[ "$HASH" != \$2b\$* ]]; then
    echo "ERROR: la salida no parece un hash bcrypt válido." >&2
    echo "$HASH" >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 6. Decidir si se obliga a cambiar la contraseña al entrar
# -----------------------------------------------------------------------------
# En PostgreSQL los booleanos se escriben TRUE / FALSE (sin comillas).
DEBE_CAMBIAR="TRUE"
if [[ "$MODO_CAMBIO" == "--sin-cambio-obligatorio" ]]; then
    DEBE_CAMBIAR="FALSE"
fi

# -----------------------------------------------------------------------------
# 7. Actualizar la base
# -----------------------------------------------------------------------------
# El hash bcrypt solo contiene letras, números y . / $, por lo que es seguro
# incrustarlo entre comillas simples.
# session_id = NULL cierra cualquier sesión abierta de ese usuario (evita el
# aviso de "sesión iniciada desde otro dispositivo").
# ON_ERROR_STOP=1 hace que psql devuelva error si la sentencia falla, en lugar
# de terminar con éxito silencioso.
if ! RESULTADO="$(docker exec "$CONTENEDOR_DB" psql -v ON_ERROR_STOP=1 \
        -U "$DB_USER" -d "$DB_NAME" -tAc "
UPDATE cliente
   SET hash_contrasena  = '$HASH',
       debe_cambiar_pw  = $DEBE_CAMBIAR,
       activa           = TRUE,
       session_id       = NULL,
       ultima_actividad = NULL
 WHERE cif = '$CIF';")"; then
    echo "ERROR: falló la actualización en PostgreSQL." >&2
    exit 1
fi

# psql -tAc devuelve el número de filas afectadas con el formato "UPDATE N".
if [[ "$RESULTADO" != "UPDATE 1" ]]; then
    echo "AVISO: se esperaba actualizar 1 fila y se actualizaron: '$RESULTADO'." >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 8. Verificación final (leer lo escrito)
# -----------------------------------------------------------------------------
# No se da nada por bueno sin releer la fila: confirma que el hash guardado es
# exactamente el generado y que el usuario ha quedado activo.
if ! COMPROBACION="$(docker exec "$CONTENEDOR_DB" psql -v ON_ERROR_STOP=1 \
        -U "$DB_USER" -d "$DB_NAME" -tAc "
SELECT COUNT(*) FROM cliente
 WHERE cif = '$CIF'
   AND hash_contrasena = '$HASH'
   AND activa = TRUE;")"; then
    echo "ERROR: no se pudo verificar el cambio." >&2
    exit 1
fi

if [[ "$COMPROBACION" != "1" ]]; then
    echo "ERROR: la verificación ha fallado; el hash guardado no coincide." >&2
    exit 1
fi

echo "OK: contraseña actualizada y verificada para '$CIF'."
if [[ "$DEBE_CAMBIAR" == "TRUE" ]]; then
    echo "    Al iniciar sesión se pedirá establecer una contraseña definitiva."
fi
