import requests
import json

def diagnose(query):
    headers = {'User-Agent': 'Proyecto-SIRA/1.0 (TFG-Development)'}
    url = f"https://nominatim.openstreetmap.org/search?q={query}&countrycodes=es&format=json&addressdetails=1&limit=50&dedupe=0"
    print(f"Probando: {url}")
    try:
        response = requests.get(url, headers=headers, timeout=5)
        data = response.json()
        print(f"Resultados encontrados: {len(data)}")
        cps = [p.get('address', {}).get('postcode') for p in data if p.get('address', {}).get('postcode')]
        print(f"Códigos Postales detectados: {list(set(cps))}")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    print("--- DIAGNÓSTICO MADRID ---")
    diagnose("Madrid")
    print("\n--- DIAGNÓSTICO BARRIDO 280 ---")
    diagnose("280 Madrid")
    print("\n--- DIAGNÓSTICO VALENCIA ---")
    diagnose("Valencia")
