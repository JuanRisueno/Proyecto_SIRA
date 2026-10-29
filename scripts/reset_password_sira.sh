#!/usr/bin/env bash
# =============================================================================
# reset_password_sira.sh — Restablece la contraseña de un usuario de SIRA
# -----------------------------------------------------------------------------
# QUÉ HACE
#   Cambia el hash de la contraseña de un usuario (identificado por su CIF) en
#   la base de datos PostgreSQL del contenedor `sira_db`, sin tener que borrar
#   el volumen de datos ni volver a inicializar la base.
#
# POR QUÉ HACE FALTA
#   Los usuarios semilla (root, admin, clientes) solo se insertan al crear el
#   volumen por primera vez. Si luego cambias la contraseña desde la web, el
#   volcado `20-data.sql` ya no se aplica y no hay forma de volver a la clave
#   original sin tocar la base a mano.
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
#   Por defecto marca `debe_cambiar_pw = TRUE`, así que al entrar SIRA pedirá
#   una contraseña nueva (comportamiento de seguridad previsto).
#
# QUÉ NECESITA
#   - Docker en marcha y los contenedores `sira_api` y `sira_db` levantados.
#   - Que exista el .env del proyecto (se lee de ahí DB_USER y DB_NAME).
# =============================================================================

# -e: abortar si un comando falla | -u: error si se usa una variable vacía
# -o pipefail: si falla un comando dentro de una tubería, falla toda la línea
set -euo pipefail

# IFS restrictivo: evita que un espacio en una ruta o CIF rompa los bucles/argumentos
IFS=$'\n\t'

# Ruta del proyecto: se deduce subiendo un nivel desde la carpeta del script
# (scripts/ -> raíz del repo). Así funciona aunque muevas el proyecto de sitio
# o cambies el nombre de la carpeta. Se puede forzar con la variable SIRA_DIR.
SIRA_DIR="${SIRA_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"

CIF="${1:-}"
MODO_CAMBIO="${2:-}"

if [[ -z "$CIF" ]]; then
    echo "Uso: $0 <CIF> [--sin-cambio-obligatorio]" >&2
    exit 1
fi

# --- 1. Leer credenciales de la base desde el .env ---------------------------
ENV_FILE="$SIRA_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: no encuentro $ENV_FILE" >&2
    exit 1
fi

# `source` carga las variables del .env en esta shell
# shellcheck disable=SC1090
source "$ENV_FILE"

DB_USER="${DB_USER:?DB_USER no definido en el .env}"
DB_NAME="${DB_NAME:?DB_NAME no definido en el .env}"

# --- 2. Comprobar que los contenedores están arriba --------------------------
for contenedor in sira_api sira_db; do
    if [[ "$(docker inspect -f '{{.State.Running}}' "$contenedor" 2>/dev/null || echo false)" != "true" ]]; then
        echo "ERROR: el contenedor $contenedor no está en marcha." >&2
        echo "       Levántalo con: cd $SIRA_DIR && docker compose up -d" >&2
        exit 1
    fi
done

# --- 3. Pedir la contraseña sin mostrarla por pantalla -----------------------
# -s: no echo (la clave no queda visible) | -p: prompt | -r: no interpretar '\'
read -rsp "Nueva contraseña para '$CIF': " PASSWORD
echo
read -rsp "Repite la contraseña: " PASSWORD_2
echo

if [[ "$PASSWORD" != "$PASSWORD_2" ]]; then
    echo "ERROR: las contraseñas no coinciden." >&2
    exit 1
fi

if [[ ${#PASSWORD} -lt 8 ]]; then
    echo "ERROR: la contraseña debe tener al menos 8 caracteres." >&2
    exit 1
fi

# --- 4. Generar el hash bcrypt dentro del contenedor de la API ---------------
# Se hace ahí porque es la imagen que ya tiene instalado el módulo `bcrypt`.
# La contraseña viaja por stdin (no como argumento), así no aparece en `ps`.
HASH="$(docker exec -i sira_api python -c '
import sys, bcrypt
clave = sys.stdin.read().rstrip("\n").encode("utf-8")
print(bcrypt.hashpw(clave, bcrypt.gensalt(rounds=12)).decode())
' <<< "$PASSWORD")"

if [[ -z "$HASH" ]]; then
    echo "ERROR: no se pudo generar el hash." >&2
    exit 1
fi

# --- 5. Decidir si se obliga a cambiar la contraseña al entrar --------------
# En PostgreSQL los booleanos se escriben TRUE / FALSE.
DEBE_CAMBIAR="TRUE"
if [[ "$MODO_CAMBIO" == "--sin-cambio-obligatorio" ]]; then
    DEBE_CAMBIAR="FALSE"
fi

# --- 6. Actualizar la base ---------------------------------------------------
# El hash bcrypt solo contiene letras, números y . / $, así que es seguro
# incrustarlo entre comillas simples en el SQL.
# session_id = NULL cierra cualquier sesión abierta de ese usuario (evita el
# aviso de "sesión iniciada desde otro dispositivo").
RESULTADO="$(docker exec -i sira_db psql -U "$DB_USER" -d "$DB_NAME" -tAc "
UPDATE cliente
   SET hash_contrasena = '$HASH',
       debe_cambiar_pw = $DEBE_CAMBIAR,
       activa          = TRUE,
       session_id      = NULL,
       ultima_actividad = NULL
 WHERE cif = '$CIF';
")"

# psql -tAc devuelve el número de filas afectadas (UPDATE N)
if [[ "$RESULTADO" == "UPDATE 0" || -z "$RESULTADO" ]]; then
    echo "AVISO: no existe ningún usuario con CIF '$CIF'. No se ha cambiado nada." >&2
    exit 1
fi

echo "OK: contraseña actualizada para '$CIF'."
if [[ "$DEBE_CAMBIAR" == "TRUE" ]]; then
    echo "    Al iniciar sesión se pedirá establecer una contraseña definitiva."
fi
