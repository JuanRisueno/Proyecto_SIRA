import psycopg2
import time
import random
import argparse
import os
from datetime import datetime

# --- Configuración de Base de Datos ---
# Prioridad: Variables de entorno (Docker) > .env > Valores por defecto
DB_USER = os.getenv("DB_USER", "juanrisueno")
DB_PASS = os.getenv("DB_PASSWORD", "sira1234")
DB_NAME = os.getenv("DB_NAME", "sira_db")
DB_HOST = os.getenv("DB_HOST", "localhost")
DB_PORT = os.getenv("DB_PORT", "5432")

def get_connection():
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
