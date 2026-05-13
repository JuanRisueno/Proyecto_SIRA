import requests

BASE_URL = "http://localhost:8085/api/auth/token"
# El ID del cliente admin suele ser el 2
PROTECTED_URL = "http://localhost:8085/api/v1/clientes/2" 

def login(username, password):
    resp = requests.post(BASE_URL, data={"username": username, "password": password})
    if resp.status_code == 200:
        return resp.json()["access_token"]
    return None

token1 = login("admin", "admin1234")

# Realizar petición con el token 1
headers1 = {"Authorization": f"Bearer {token1}"}
resp1 = requests.get(PROTECTED_URL, headers=headers1)
print(f"Petición 1 con Token 1 - Estado: {resp1.status_code}")

token2 = login("admin", "admin1234")

# Realizar petición con el token 1 de nuevo
resp2 = requests.get(PROTECTED_URL, headers=headers1)
print(f"Petición 2 con Token 1 - Estado: {resp2.status_code}")
print(f"Respuesta de la Petición 2: {resp2.text}")

# Realizar petición con el token 2
headers2 = {"Authorization": f"Bearer {token2}"}
resp3 = requests.get(PROTECTED_URL, headers=headers2)
print(f"Petición 3 con Token 2 - Estado: {resp3.status_code}")

