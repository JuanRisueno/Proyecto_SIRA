import requests
import json

def test_nominatim(nombre):
    headers = {'User-Agent': 'Proyecto-SIRA/1.0 (TFG-Development)'}
    url = f"https://nominatim.openstreetmap.org/search?q={nombre}&countrycodes=es&format=json&addressdetails=1&limit=50"
    
    response = requests.get(url, headers=headers, timeout=5)
    if response.status_code == 200:
        data = response.json()
        print(f"Resultados para {nombre}: {len(data)}")
        for i, place in enumerate(data[:5]):
            print(f"\nResultado {i+1}:")
            print(json.dumps(place, indent=2, ensure_ascii=False))
    else:
        print(f"Error: {response.status_code}")

if __name__ == "__main__":
    test_nominatim("Barcelona")
