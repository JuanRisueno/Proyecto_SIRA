#!/usr/bin/env bash
# =============================================================================
# verificar_usuarios_sira.sh — Comprueba que SIRA reconoce a los usuarios
# -----------------------------------------------------------------------------
# QUÉ HACE
#   Verifica, de punta a punta, que el login funciona:
#     1. Los contenedores `sira_db` y `sira_api` están en marcha.
#     2. La tabla `cliente` tiene usuarios y todos están activos.
#     3. Los hashes guardados son bcrypt válidos ($2b$12$... de 60 caracteres).
#     4. El endpoint de login devuelve 200 con las credenciales del seed y 401
#        con una contraseña incorrecta.
#
#   Sirve para demostrar que el sistema funciona igual en Linux, Windows con
#   Docker Desktop o AWS: si sale "TODO CORRECTO", los usuarios de la BBDD se
#   reconocen en esa máquina.
#
# CÓMO SE USA
#   ./scripts/verificar_usuarios_sira.sh              # comprobación completa
#   ./scripts/verificar_usuarios_sira.sh --sin-login  # solo base de datos
#
#   Salida: una tabla por usuario y un veredicto final.
#   Código de salida: 0 = todo correcto, 1 = hay algún fallo.
#
# QUÉ NECESITA
#   - Docker en marcha y el stack levantado (docker compose up -d).
#   - El .env del proyecto (de ahí se leen DB_USER, DB_NAME y SIRA_PORT).
#   - curl (incluido en Linux, macOS y Windows 10+).
#
# AVISO IMPORTANTE
#   Cada login de prueba genera un session_id nuevo e invalida la sesión que
#   ese usuario tuviera abierta en el navegador. No lo ejecutes mientras
#   alguien esté usando SIRA (o usa --sin-login).
# =============================================================================

# -u: error si se usa una variable vacía | -o pipefail: falla la tubería si falla un comando.
# NO se usa -e a propósito: aquí interesa ejecutar TODAS las comprobaciones y
# resumir al final, no abortar en el primer fallo.
set -uo pipefail

IFS=$'\n\t'

SIRA_DIR="${SIRA_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
CONTENEDOR_API="sira_api"
CONTENEDOR_DB="sira_db"

# -----------------------------------------------------------------------------
# 0. Argumentos
# -----------------------------------------------------------------------------
COMPROBAR_LOGIN=1
for argumento in "$@"; do
    case "$argumento" in
        --sin-login) COMPROBAR_LOGIN=0 ;;
        -h|--help)
            echo "Uso: $0 [--sin-login]"
            echo "  --sin-login   No prueba el endpoint de login (no cierra sesiones abiertas)."
            exit 0
            ;;
        *)
            echo "ERROR: opción desconocida: $argumento" >&2
            exit 1
            ;;
    esac
done

# -----------------------------------------------------------------------------
# 1. Leer configuración del .env sin ejecutarlo
# -----------------------------------------------------------------------------
ENV_FILE="$SIRA_DIR/.env"
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: no encuentro $ENV_FILE" >&2
    exit 1
fi

leer_variable_env() {
    # Última aparición de la clave, sin comillas ni \r de Windows.
    awk -F'=' -v clave="$1" '$1 == clave { valor = substr($0, index($0, "=") + 1) } END { print valor }' "$ENV_FILE" \
        | tr -d '\r' \
        | sed -e 's/^["'"'"']//' -e 's/["'"'"']$//'
}

DB_USER="$(leer_variable_env DB_USER)"
DB_NAME="$(leer_variable_env DB_NAME)"
SIRA_PORT="$(leer_variable_env SIRA_PORT)"
SIRA_PORT="${SIRA_PORT:-8085}"
URL_API="http://localhost:${SIRA_PORT}"

# -----------------------------------------------------------------------------
# 2. Contenedores
# -----------------------------------------------------------------------------
if ! command -v docker >/dev/null 2>&1; then
    echo "FALLO: 'docker' no está instalado o no está en el PATH." >&2
    exit 1
fi

for contenedor in "$CONTENEDOR_DB" "$CONTENEDOR_API"; do
    estado="$(docker inspect -f '{{.State.Running}}' "$contenedor" 2>/dev/null || echo "no-existe")"
    if [[ "$estado" != "true" ]]; then
        echo "FALLO: el contenedor $contenedor no está en marcha (estado: $estado)." >&2
        echo "       Levántalo con: cd $SIRA_DIR && docker compose up -d" >&2
        exit 1
    fi
done
echo "Contenedores: sira_db y sira_api en marcha."
echo

# -----------------------------------------------------------------------------
# 3. Usuarios en la base de datos
# -----------------------------------------------------------------------------
# rtrim(cif) quita el relleno de espacios del char(9); -F '|' separa campos.
if ! FILAS="$(docker exec "$CONTENEDOR_DB" psql -v ON_ERROR_STOP=1 \
        -U "$DB_USER" -d "$DB_NAME" -tAF'|' -c "
SELECT rtrim(cif), rol, activa, hash_contrasena
  FROM cliente
 ORDER BY cliente_id;")"; then
    echo "FALLO: no se pudo consultar la tabla cliente." >&2
    exit 1
fi

if [[ -z "$FILAS" ]]; then
    echo "FALLO: la tabla cliente está vacía (¿se ejecutó el seed 20-data.sql?)." >&2
    exit 1
fi

# -----------------------------------------------------------------------------
# 4. Contraseñas del seed (solo para la prueba de login)
# -----------------------------------------------------------------------------
# Se comparan en minúsculas porque el login ya no distingue mayúsculas del CIF.
# Si alguien cambió su contraseña, el login devolverá 401: eso NO es un fallo,
# se informa como "contraseña distinta de la del seed".
password_semilla() {
    case "${1,,}" in
        root)                                              echo "root1234" ;;
        admin)                                             echo "admin1234" ;;
        b04xxxxxx|a12345678|b87654321|c11222333|d44333444) echo "sol1234" ;;
        *)                                                 echo "" ;;
    esac
}

# -----------------------------------------------------------------------------
# 5. Recorrer usuarios y comprobar
# -----------------------------------------------------------------------------
TOTAL=0; OK=0; AVISOS=0; FALLOS=0

printf '%-10s %-8s %-8s %-9s %s\n' "CIF" "ROL" "ACTIVA" "HASH" "LOGIN"
printf '%-10s %-8s %-8s %-9s %s\n' "----------" "--------" "--------" "---------" "-----"

while IFS='|' read -r cif rol activa hash; do
    [[ -z "$cif" ]] && continue
    TOTAL=$((TOTAL + 1))
    estado_hash="OK"
    estado_login="-"

    # Un hash bcrypt válido empieza por $2b$ o $2a$ y mide 60 caracteres.
    if [[ ! "$hash" =~ ^\$2[aby]\$[0-9]{2}\$.{53}$ ]]; then
        estado_hash="MAL"
    fi

    if [[ "$activa" != "t" ]]; then
        estado_login="INACTIVO"
    fi

    if [[ "$COMPROBAR_LOGIN" -eq 1 && "$activa" == "t" ]]; then
        clave="$(password_semilla "$cif")"
        if [[ -z "$clave" ]]; then
            estado_login="sin clave conocida"
        else
            codigo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 \
                -X POST "$URL_API/api/auth/token" \
                --data-urlencode "username=$cif" \
                --data-urlencode "password=$clave" 2>/dev/null)"
            case "$codigo" in
                200) estado_login="OK" ;;
                401) estado_login="clave distinta a la del seed" ;;
                000) estado_login="API NO RESPONDE" ;;
                *)   estado_login="HTTP $codigo" ;;
            esac
        fi
    fi

    # Clasificación del resultado de esta fila
    if [[ "$estado_hash" == "MAL" || "$activa" != "t" || "$estado_login" == "API NO RESPONDE" \
          || "$estado_login" == HTTP* ]]; then
        FALLOS=$((FALLOS + 1))
        marca="FALLO"
    elif [[ "$estado_login" == "clave distinta a la del seed" || "$estado_login" == "sin clave conocida" ]]; then
        AVISOS=$((AVISOS + 1))
        marca="aviso"
    else
        OK=$((OK + 1))
        marca="ok"
    fi

    printf '%-10s %-8s %-8s %-9s %s [%s]\n' "$cif" "$rol" "$activa" "$estado_hash" "$estado_login" "$marca"
done <<< "$FILAS"

# -----------------------------------------------------------------------------
# 6. Control negativo: una contraseña incorrecta debe devolver 401
# -----------------------------------------------------------------------------
if [[ "$COMPROBAR_LOGIN" -eq 1 ]]; then
    # Se usa un usuario que acabamos de ver que existe (el primero de la lista).
    primer_cif="$(head -n1 <<< "$FILAS" | cut -d'|' -f1)"
    codigo_malo="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 \
        -X POST "$URL_API/api/auth/token" \
        --data-urlencode "username=$primer_cif" \
        --data-urlencode "password=clave_que_no_existe_1234" 2>/dev/null)"
    echo
    if [[ "$codigo_malo" == "401" ]]; then
        echo "Control negativo: contraseña incorrecta rechazada con 401 (correcto)."
    else
        echo "Control negativo: se esperaba 401 y se obtuvo '$codigo_malo' -> FALLO."
        FALLOS=$((FALLOS + 1))
    fi
fi

# -----------------------------------------------------------------------------
# 7. Veredicto
# -----------------------------------------------------------------------------
echo
echo "Resumen: $TOTAL usuarios | $OK correctos | $AVISOS avisos | $FALLOS fallos"
if [[ "$FALLOS" -gt 0 ]]; then
    echo "RESULTADO: HAY FALLOS. Revisa docker logs sira_api y docs/infraestructura/garantia_login_usuarios.md"
    exit 1
fi

echo "RESULTADO: TODO CORRECTO. SIRA reconoce a los usuarios de la base de datos."
exit 0
