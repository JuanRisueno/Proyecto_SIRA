import os
import json
from fastapi import APIRouter, Depends, HTTPException, status
from .. import auth, schemas, models

router = APIRouter(
    prefix="/api/v1/sistema",
    tags=["Gestión del Sistema"],
)

# Directorio de configuración global (independiente de clientes)
SYSTEM_CONFIG_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "config_sistema")
SOCIAL_CONFIG_PATH = os.path.join(SYSTEM_CONFIG_DIR, "social_links.json")

def get_social_config():
    """Recupera la configuración de redes sociales del JSON."""
    if not os.path.exists(SOCIAL_CONFIG_PATH):
        return {
            "twitter": "",
            "instagram": "",
            "facebook": "",
            "whatsapp": "",
            "email_soporte": "sira@sira.es"
        }
    
    try:
        with open(SOCIAL_CONFIG_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    except:
        return {}

@router.get("/social", response_model=schemas.ConfigSocial)
def obtener_social():
    """Devuelve los enlaces a redes sociales configurados. Endpoint Público."""
    return get_social_config()

@router.post("/social", status_code=status.HTTP_200_OK)
def guardar_social(
    config: schemas.ConfigSocial,
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """Actualiza los enlaces de redes sociales. Solo accesible para Root."""
    if current_user.rol != "root":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Solo el usuario Root puede configurar los enlaces del sistema"
        )

    if not os.path.exists(SYSTEM_CONFIG_DIR):
        os.makedirs(SYSTEM_CONFIG_DIR)

    try:
        data_to_save = config.model_dump()
        with open(SOCIAL_CONFIG_PATH, "w", encoding="utf-8") as f:
            json.dump(data_to_save, f, indent=2, ensure_ascii=False)
        return {"mensaje": "Redes sociales actualizadas correctamente"}
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Error al guardar la configuración: {str(e)}"
        )
