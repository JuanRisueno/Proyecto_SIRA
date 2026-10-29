#!/usr/bin/env python3
# =============================================================================
# simulador.py — Simulador IoT de SIRA
# -----------------------------------------------------------------------------
# QUÉ HACE
#   Inserta mediciones sintéticas en la tabla MEDICION cada N segundos, para
#   demostrar la lógica de automatización del backend sin hardware real.
#   El backend (FastAPI) evalúa esas mediciones y decide sobre los actuadores.
#
# CÓMO SE USA
#   Recomendado, dentro de la red Docker (el host correcto es 'db'):
#     docker compose run --rm -v "$PWD/scripts:/scripts:ro" \
#         api python /scripts/simulador.py --clima ideal --intervalo 10
#
#   En local, con acceso a PostgreSQL desde la máquina:
#     python3 scripts/simulador.py --clima tormenta
#
#   Escenarios: ideal | tormenta | calor_extremo | random
#
# QUÉ NECESITA
#   - Credenciales de la BD. Se resuelven en este orden:
#       1) DATABASE_URL (la define Docker Compose para el contenedor 'api')
#       2) variables de entorno DB_USER / DB_PASSWORD / DB_NAME / DB_HOST
#       3) fichero .env de la raíz del proyecto
#     No hay credenciales escritas en el código.
#   - Que PostgreSQL sea accesible desde donde se ejecute el script.
# =============================================================================

import psycopg2
import time
import random
import argparse
import os
from datetime import datetime

# --- Configuración de Base de Datos ---
# Prioridad: DATABASE_URL (Docker) > variables de entorno > fichero .env > valores neutros.
#
# No se asume ninguna ruta fija: el .env se busca subiendo un nivel desde este
# script (scripts/ -> raíz del repositorio), de modo que funciona igual si el
# proyecto se ha clonado en ~/Proyectos, en /opt o en el escritorio.
from pathlib import Path
from urllib.parse import urlparse

DIR_PROYECTO = Path(__file__).resolve().parent.parent


def cargar_env(ruta: Path) -> dict:
    """Lee un .env sencillo (CLAVE=valor) sin dependencias externas."""
    datos = {}
    if not ruta.exists():
        return datos
    for linea in ruta.read_text(encoding="utf-8").splitlines():
        linea = linea.strip()
        if not linea or linea.startswith("#") or "=" not in linea:
            continue
        clave, valor = linea.split("=", 1)
        datos[clave.strip()] = valor.strip().strip('"').strip("'")
    return datos


_env = cargar_env(DIR_PROYECTO / ".env")


def config(clave: str, por_defecto=None):
    """Devuelve el valor de una clave desde entorno, .env o valor por defecto."""
    return os.getenv(clave) or _env.get(clave) or por_defecto


# Dentro de la red Docker la API recibe DATABASE_URL; si existe, se reutiliza
# para no repetir credenciales (y el host correcto es 'db', no 'localhost').
_url = os.getenv("DATABASE_URL")
if _url:
    _partes = urlparse(_url)
    DB_USER = _partes.username
    DB_PASS = _partes.password
    DB_NAME = (_partes.path or "/sira_db").lstrip("/")
    DB_HOST = _partes.hostname or "db"
    DB_PORT = str(_partes.port or 5432)
else:
    DB_USER = config("DB_USER")
    DB_PASS = config("DB_PASSWORD")
    DB_NAME = config("DB_NAME", "sira_db")
    DB_HOST = config("DB_HOST", "localhost")
    DB_PORT = config("DB_PORT", "5432")

def get_connection():
    if not DB_USER or not DB_PASS:
        print("❌ Faltan credenciales: define DB_USER y DB_PASSWORD en el .env del proyecto")
        print(f"   (o copia env.ejemplo a .env dentro de {DIR_PROYECTO})")
        return None
    try:
        conn = psycopg2.connect(
            user=DB_USER,
            password=DB_PASS,
            host=DB_HOST,
            port=DB_PORT,
            database=DB_NAME
        )
        return conn
    except Exception as e:
        print(f"❌ Error conectando a la base de datos: {e}")
        print(f"   Destino: {DB_USER}@{DB_HOST}:{DB_PORT}/{DB_NAME}")
        return None

def simular_clima(clima):
    """Devuelve valores (temp, humedad, viento, luz) según el escenario."""
    if clima == "tormenta":
        return (random.uniform(10, 15), random.uniform(85, 95), random.uniform(60, 100), random.uniform(5, 20))
    elif clima == "calor_extremo":
        return (random.uniform(38, 45), random.uniform(10, 25), random.uniform(0, 15), random.uniform(80, 100))
    elif clima == "ideal":
        return (random.uniform(22, 26), random.uniform(60, 70), random.uniform(5, 10), random.uniform(60, 80))
    else: # random
        return (random.uniform(5, 45), random.uniform(20, 90), random.uniform(0, 50), random.uniform(0, 100))

def run_simulador():
    parser = argparse.ArgumentParser(description="Simulador IoT SIRA - Modo Defensa")
    parser.add_argument("--clima", choices=["tormenta", "calor_extremo", "ideal", "random"], default="ideal")
    parser.add_argument("--intervalo", type=int, default=10, help="Segundos entre mediciones")
    args = parser.parse_args()

    print(f"🚀 Iniciando Simulador SIRA (Modo: {args.clima})...")
    conn = get_connection()
    if not conn: return

    cursor = conn.cursor()

    # Obtener lista de sensores activos
    cursor.execute("SELECT sensor_id, tipo_sensor_id FROM SENSOR WHERE estado_sensor = 'Activo'")
    sensores = cursor.fetchall()

    if not sensores:
        print("⚠️ No se encontraron sensores activos en la base de datos.")
        # Intentamos obtener cualquier sensor si no hay activos
        cursor.execute("SELECT sensor_id, tipo_sensor_id FROM SENSOR LIMIT 10")
        sensores = cursor.fetchall()
        if not sensores:
            print("❌ No hay sensores registrados. Abortando.")
            return

    try:
        while True:
            t, h, v, l = simular_clima(args.clima)
            timestamp = datetime.now()

            for sensor_id, tipo_id in sensores:
                # Mapeo según TIPO_SENSOR (1:Temp, 2:Hum, 3:Viento, 4:Luz - asumiendo IDs estándar)
                valor = 0
                if tipo_id == 1: valor = t
                elif tipo_id == 2: valor = h
                elif tipo_id == 3: valor = v
                elif tipo_id == 4: valor = l
                else: valor = random.uniform(0, 100)

                cursor.execute(
                    "INSERT INTO MEDICION (sensor_id, fecha_hora, valor) VALUES (%s, %s, %s)",
                    (sensor_id, timestamp, valor)
                )
            
            conn.commit()
            print(f"✅ [{timestamp.strftime('%H:%M:%S')}] Telemetría enviada: T={t:.1f}ºC, H={h:.1f}%, V={v:.1f}km/h, L={l:.1f}%")
            time.sleep(args.intervalo)

    except KeyboardInterrupt:
        print("\n🛑 Simulador detenido por el usuario.")
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    run_simulador()
