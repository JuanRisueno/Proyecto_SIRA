import requests

BASE_URL = "http://localhost:8085/api/auth/token"
PROTECTED_URL = "http://localhost:8085/api/v1/clientes/2"

# 1. Iniciar sesión
resp = requests.post(BASE_URL, data={"username": "admin", "password": "admin1234"})
token = resp.json().get("access_token")
print(f"Token obtenido. Estado: {resp.status_code}")

# 2. Forzar que 'ultima_actividad' sea de hace 40 minutos
import os
os.system('docker exec sira_db psql -U juanrisueno -d sira_db -c "UPDATE cliente SET ultima_actividad = NOW() - INTERVAL \'40 minutes\' WHERE cif = \'admin\';"')

# 3. Acceder al endpoint
headers = {"Authorization": f"Bearer {token}"}
resp2 = requests.get(PROTECTED_URL, headers=headers)
print(f"Estado de la petición: {resp2.status_code}")
print(f"Respuesta de la petición: {resp2.text}")
