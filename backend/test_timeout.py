#!/usr/bin/env python3
# =============================================================================
# test_timeout.py — Prueba manual del cierre de sesión por inactividad
# -----------------------------------------------------------------------------
# QUÉ HACE
#   1. Hace login contra la API para obtener un JWT.
#   2. Fuerza en la base de datos que 'ultima_actividad' sea de hace 40 minutos
#      (más que la ventana deslizante de 30 min de Iron Fortress).
#   3. Vuelve a llamar a un endpoint protegido: debe responder 401 porque la
#      sesión ha caducado.
#
# CÓMO SE USA
#   python3 backend/test_timeout.py
#
#   Variables opcionales:
#     SIRA_URL       (por defecto http://localhost:8085/api/auth/token)
#     SIRA_USER      (por defecto la cuenta semilla 'admin')
#     SIRA_PASSWORD  (por defecto la contraseña semilla de admin)
#
# QUÉ NECESITA
#   - SIRA levantada con Docker Compose y el contenedor 'sira_db' en marcha.
#   - Paquete 'requests' instalado.
#
# NOTA: el SQL se envía por stdin al psql del contenedor y las credenciales se
# leen de las variables POSTGRES_USER / POSTGRES_DB del propio contenedor, así
# que el script funciona con cualquier .env y en cualquier ruta del proyecto.
# =============================================================================

import os
import subprocess

import requests

BASE_URL = os.getenv("SIRA_URL", "http://localhost:8085/api/auth/token")
PROTECTED_URL = "http://localhost:8085/api/v1/clientes/2"
USUARIO = os.getenv("SIRA_USER", "admin")
PASSWORD = os.getenv("SIRA_PASSWORD", "admin1234")

# 1. Iniciar sesión
resp = requests.post(BASE_URL, data={"username": USUARIO, "password": PASSWORD})
token = resp.json().get("access_token")
print(f"Token obtenido. Estado: {resp.status_code}")

if not token:
    raise SystemExit("No se pudo obtener el token: revisa usuario y contraseña.")

# 2. Forzar que 'ultima_actividad' sea de hace 40 minutos.
#    Se pasa el SQL por stdin para no pelear con las comillas del shell.
sql = (
    "UPDATE cliente SET ultima_actividad = NOW() - INTERVAL '40 minutes' "
    f"WHERE cif = '{USUARIO}';"
)
subprocess.run(
    ["docker", "exec", "-i", "sira_db", "sh", "-c",
     'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"'],
    input=sql,
    text=True,
    check=True
)

# 3. Acceder al endpoint protegido: debe devolver 401 (SESSION_TIMEOUT)
headers = {"Authorization": f"Bearer {token}"}
resp2 = requests.get(PROTECTED_URL, headers=headers)
print(f"Estado de la petición: {resp2.status_code}")
print(f"Respuesta de la petición: {resp2.text}")
