#!/usr/bin/env bash
# =============================================================================
#  diagnostico_sira.sh — Comprobación (y reparación) de la cadena de login SIRA
# =============================================================================
#
# QUÉ HACE
#   Recorre de abajo arriba toda la cadena que interviene en el login y dice en
#   qué eslabón se rompe:
#
#       1. Contenedores en marcha y su estado de salud (no solo "Up")
#       2. Volúmenes con nombre fijo (para que la instalación sea portátil)
#       3. La API escucha de verdad en el puerto 8000
#       4. La API puede autenticarse contra PostgreSQL con la contraseña del .env
#       5. El endpoint /api/auth/token responde a través de Nginx (puerto SIRA_PORT)
#
#   Desde la v2 el punto 4 se corrige solo: un healthcheck dentro del contenedor
#   de PostgreSQL (backend/scripts/sira-healthcheck.sh) alinea la contraseña del
#   rol con la del .env en cada arranque. Este script sirve para verificarlo.
#
# CÓMO SE USA
#   ./scripts/diagnostico_sira.sh            # solo diagnostica (no toca nada)
#   ./scripts/diagnostico_sira.sh --reparar  # fuerza el reinicio de la BBDD para
#                                            # que su healthcheck realinee la clave
#
# QUÉ NECESITA
#   - Docker y docker compose funcionando, con permiso para usar docker.
#   - Ejecutarse desde la carpeta del proyecto (donde está docker-compose.yml).
#   - El .env con DB_USER, DB_PASSWORD, DB_NAME y SIRA_PORT.
#
# =============================================================================

# -e : abortar al primer error no controlado
# -u : error si se usa una variable sin definir
# -o pipefail : si falla un comando dentro de una tubería, la tubería falla
set -euo pipefail

# ── 0. Situarnos en la raíz del proyecto (vale llamarlo desde cualquier sitio)
RAIZ_PROYECTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$RAIZ_PROYECTO"

if [[ ! -f .env ]]; then
    echo "ERROR: no existe .env en $RAIZ_PROYECTO"
    exit 1
fi

# Cargamos el .env exportando las variables (set -a).
# El `tr -d '\r'` es importante en Windows: si el fichero tiene finales de línea
# CRLF, cada valor arrastra un \r invisible y las contraseñas dejan de coincidir
# con un error desconcertante ("password authentication failed").
set -a
# shellcheck disable=SC1091
source <(tr -d '\r' < ./.env)
set +a

PUERTO="${SIRA_PORT:-8085}"
REPARAR=0
[[ "${1:-}" == "--reparar" ]] && REPARAR=1

FALLOS=0
fallo() { echo "  [FALLO] $*"; FALLOS=$((FALLOS + 1)); }
ok()    { echo "  [ OK  ] $*"; }
aviso() { echo "  [AVISO] $*"; }

echo "=============================================================="
echo " Diagnóstico SIRA — $(date '+%Y-%m-%d %H:%M:%S')"
echo " Proyecto: $RAIZ_PROYECTO"
echo "=============================================================="

# ── 1. Contenedores y salud ──────────────────────────────────────────────────
echo
echo "1) Contenedores"
for contenedor in sira_db sira_api sira_nginx sira_frontend; do
    estado="$(docker inspect -f '{{.State.Status}}' "$contenedor" 2>/dev/null || echo "no-existe")"
    # La salud solo existe si el servicio define healthcheck (db y api lo hacen).
    salud="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}sin healthcheck{{end}}' "$contenedor" 2>/dev/null || echo "-")"
    reinicios="$(docker inspect -f '{{.RestartCount}}' "$contenedor" 2>/dev/null || echo "?")"
    if [[ "$estado" == "running" && "$salud" != "unhealthy" ]]; then
        ok "$contenedor: $estado / $salud (reinicios: $reinicios)"
    else
        fallo "$contenedor: $estado / $salud (reinicios: $reinicios)"
    fi
done

# ── 2. Volúmenes portátiles ──────────────────────────────────────────────────
# Con `name:` explícito en docker-compose.yml el volumen se llama igual esté el
# proyecto donde esté (Repositorios/, personal/, C:\...). Si aparece un nombre
# con prefijo raro, es que se está usando un docker-compose.yml antiguo.
echo
echo "2) Volúmenes (deben tener nombre fijo, sin prefijo de carpeta)"
for volumen in sira_postgres_data sira_security_history; do
    if docker volume inspect "$volumen" >/dev/null 2>&1; then
        ok "$volumen existe"
    else
        fallo "$volumen no existe (¿docker-compose.yml antiguo o stack nunca arrancado?)"
    fi
done

# ── 3. ¿La API escucha en el puerto 8000? ────────────────────────────────────
# Un contenedor 'Up' no garantiza que uvicorn esté sirviendo: si el proceso
# muere al importar el código, nada escucha y el navegador da un error de
# conexión sin más pistas.
echo
echo "3) La API escucha en el puerto 8000"
if docker exec sira_api python -c "
import socket, sys
s = socket.socket()
sys.exit(0 if s.connect_ex(('127.0.0.1', 8000)) == 0 else 1)
" 2>/dev/null; then
    ok "uvicorn escuchando"
else
    fallo "nada escucha en 8000: la API está caída (revisa: docker logs sira_api)"
fi

# ── 4. Autenticación API → PostgreSQL ────────────────────────────────────────
echo
echo "4) Autenticación de la API contra PostgreSQL"
# Se prueba desde DENTRO del contenedor de la API y por TCP. Si se probara desde
# el contenedor de la BBDD por 127.0.0.1 daría un falso OK: su pg_hba.conf se
# fía de las conexiones locales y no comprueba la contraseña.
if docker exec -e PGPASSWORD="$DB_PASSWORD" sira_api python -c "
import os, sys, psycopg2
try:
    conexion = psycopg2.connect(
        host='db', port=5432, dbname=os.environ['DB_NAME'],
        user=os.environ['DB_USER'], password=os.environ['PGPASSWORD'],
    )
    conexion.close()
except Exception as error:
    print('   ', str(error).strip())
    sys.exit(1)
" 2>/dev/null; then
    ok "la contraseña del .env es la que tiene el rol '$DB_USER' en PostgreSQL"
else
    fallo "PostgreSQL rechaza la contraseña del .env para el rol '$DB_USER'"
    aviso "El healthcheck de sira_db debería haberla alineado. Detalle:"
    echo "        docker inspect sira_db --format '{{json .State.Health}}'"
    if [[ "$REPARAR" -eq 1 ]]; then
        # Reiniciar la BBDD dispara su healthcheck, que reejecuta el ALTER USER.
        echo "        Reinciando sira_db para que su healthcheck realinee la clave..."
        docker restart sira_db >/dev/null
        docker restart sira_api >/dev/null
        echo "        Hecho. Repite el diagnóstico para confirmar."
    else
        aviso "Vuelve a ejecutar con --reparar."
    fi
fi

# ── 5. Login a través de Nginx ───────────────────────────────────────────────
echo
echo "5) Endpoint de login a través de Nginx (puerto $PUERTO)"
# Credenciales inventadas a propósito. Lo que interesa es el CÓDIGO:
#   401 -> la cadena funciona (la API contesta y rechaza al usuario falso)
#   502/000 -> la API no está disponible
codigo="$(curl -s -o /dev/null -m 10 -w '%{http_code}' \
    -X POST -d 'username=DIAGNOSTICO&password=invalida' \
    "http://localhost:${PUERTO}/api/auth/token" || echo "000")"
case "$codigo" in
    401) ok "responde 401 (esperado con credenciales falsas): la cadena completa funciona" ;;
    200) aviso "responde 200 a credenciales falsas: revisa el endpoint /api/auth/token" ;;
    *)   fallo "responde $codigo (la API no está disponible para el frontend)" ;;
esac

# ── Resumen ──────────────────────────────────────────────────────────────────
echo
echo "=============================================================="
if [[ "$FALLOS" -eq 0 ]]; then
    echo " RESULTADO: cadena de login correcta."
else
    echo " RESULTADO: $FALLOS comprobación(es) con fallo."
    echo " Pistas rápidas:"
    echo "   docker logs sira_api --tail 50"
    echo "   docker logs sira_db  --tail 20"
    echo "   ./scripts/diagnostico_sira.sh --reparar"
fi
echo "=============================================================="
exit "$FALLOS"
