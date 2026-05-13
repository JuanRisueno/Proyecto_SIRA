import json
import os
from datetime import datetime, timedelta
from . import auth

HISTORY_DIR = "/app/data/security/history"

def get_history_file(user_id: int):
    """Obtiene la ruta del búnker JSON del usuario asegurando la carpeta."""
    if not os.path.exists(HISTORY_DIR):
        os.makedirs(HISTORY_DIR, exist_ok=True)
    return os.path.join(HISTORY_DIR, f"{user_id}.json")

def load_history(user_id: int):
    """Carga los datos de seguridad del archivo local."""
    path = get_history_file(user_id)
    if not os.path.exists(path):
        # Si no existe, inicializamos (el primer cambio contará como 'ahora')
        return {
            "last_change": datetime.now().isoformat(),
            "history": []
        }
    try:
        with open(path, "r") as f:
            return json.load(f)
    except:
        return {"last_change": datetime.now().isoformat(), "history": []}

def save_history(user_id: int, data: dict):
    """Guarda los datos en el búnker asegurando que el directorio existe."""
    path = get_history_file(user_id)
    with open(path, "w") as f:
        json.dump(data, f, indent=4)

def check_password_reuse(user_id: int, plain_password: str):
    """Comprueba si la contraseña ya ha sido usada en las últimas 5 ocasiones."""
    data = load_history(user_id)
    # Si el historial está vacío (sistema virgen), permitimos cualquier cambio inicial
    if not data["history"]:
        return False
        
    for old_hash in data["history"]:
        # Usamos el motor unificado de auth.py
        if auth.verify_password(plain_password, old_hash):
            return True
    return False

def record_new_password(user_id: int, new_password_hash: str):
    """Registra una nueva contraseña en el historial y actualiza la fecha."""
    data = load_history(user_id)
    
    # Insertar al principio y mantener solo las últimas 5
    data["history"].insert(0, new_password_hash)
    data["history"] = data["history"][:5]
    
    data["last_change"] = datetime.now().isoformat()
    save_history(user_id, data)

def is_password_expired(user_id: int):
    """Verifica si la contraseña actual tiene más de 90 días de vida."""
    data = load_history(user_id)
    try:
        last_change = datetime.fromisoformat(data["last_change"])
        # Umbral de 90 días
        if datetime.now() > last_change + timedelta(days=90):
            return True
    except:
        return False
    return False
