import requests
from typing import Optional, Dict, List
from urllib.parse import quote as url_quote

# --- Mapeo de Provincias de España (Integridad Gating SIRA) ---
MAPA_PROVINCIAS = {
    "01": "Álava", "02": "Albacete", "03": "Alicante", "04": "Almería", "05": "Ávila",
    "06": "Badajoz", "07": "Islas Baleares", "08": "Barcelona", "09": "Burgos", "10": "Cáceres",
    "11": "Cádiz", "12": "Castellón", "13": "Ciudad Real", "14": "Córdoba", "15": "A Coruña",
    "16": "Cuenca", "17": "Gerona", "18": "Granada", "19": "Guadalajara", "20": "Guipúzcoa",
    "21": "Huelva", "22": "Huesca", "23": "Jaén", "24": "León", "25": "Lérida",
    "26": "La Rioja", "27": "Lugo", "28": "Madrid", "29": "Málaga", "30": "Murcia",
    "31": "Navarra", "32": "Orense", "33": "Asturias", "34": "Palencia", "35": "Las Palmas",
    "36": "Pontevedra", "37": "Salamanca", "38": "Santa Cruz de Tenerife", "39": "Cantabria", "40": "Segovia",
    "41": "Sevilla", "42": "Soria", "43": "Tarragona", "44": "Teruel", "45": "Toledo",
    "46": "Valencia", "47": "Valladolid", "48": "Vizcaya", "49": "Zamora", "50": "Zaragoza",
    "51": "Ceuta", "52": "Melilla"
}

def obtener_provincia_por_cp(cp: str, backup_state: Optional[str] = None) -> str:
    """
    Determina la provincia basándose en los dos primeros dígitos del CP.
    """
    prefijo = cp[:2]
    return MAPA_PROVINCIAS.get(prefijo, backup_state if backup_state else "Desconocida")

def obtener_municipio_por_cp(cp: str) -> Optional[Dict]:
    """
    Valida un CP y obtiene su municipio/provincia de forma directa y rápida.
    """
    # 1. Intento con Zippopotam
    try:
        url = f"http://api.zippopotam.us/es/{cp}"
        response = requests.get(url, timeout=3)
        if response.status_code == 200:
            data = response.json()
            if "places" in data and len(data["places"]) > 0:
                place = data["places"][0]
                return {
                    "codigo_postal": cp,
                    "municipio": place["place name"],
                    "provincia": obtener_provincia_por_cp(cp, place["state"]),
                    "origen": "zippopotam"
                }
    except Exception:
        pass

    # 2. Respaldo con Nominatim (Búsqueda por CP exacto)
    try:
        headers = {'User-Agent': 'SIRA-Validator/1.0'}
        url_nom = f"https://nominatim.openstreetmap.org/search?postalcode={cp}&countrycodes=es&format=json&addressdetails=1&limit=1"
        resp = requests.get(url_nom, headers=headers, timeout=3)
        if resp.status_code == 200 and resp.json():
            place = resp.json()[0]
            addr = place.get("address", {})
            m = addr.get("city") or addr.get("town") or addr.get("village") or addr.get("municipality") or ""
            return {
                "codigo_postal": cp,
                "municipio": m,
                "provincia": obtener_provincia_por_cp(cp, addr.get("province")),
                "origen": "nominatim"
            }
    except Exception:
        pass

    return None
