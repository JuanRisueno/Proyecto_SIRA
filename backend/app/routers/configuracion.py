import os
import json
from fastapi import APIRouter, Depends, HTTPException, status
from typing import List, Dict, Any
from sqlalchemy.orm import Session
from .. import auth, schemas, models, database

router = APIRouter(
    prefix="/api/v1/config",
    tags=["Configuración IoT"],
)

CONFIG_DIR = os.path.join(os.path.dirname(os.path.dirname(__file__)), "config_clientes")

def get_invernadero_config_path(invernadero_id: int):
    """Devuelve la ruta al fichero JSON del invernadero."""
    return os.path.join(CONFIG_DIR, f"jornada_inv_{invernadero_id}.json")

def get_cliente_config_path(cliente_id: int):
    """Devuelve la ruta al fichero JSON de configuración global del cliente."""
    return os.path.join(CONFIG_DIR, f"jornada_cliente_{cliente_id}.json")

def verificar_propiedad_invernadero(invernadero_id: int, current_user: models.Cliente, db: Session):
    """Verifica si el invernadero pertenece al usuario actual."""
    if current_user.rol in ["root", "admin"]:
        return True
    
    inv = db.query(models.Invernadero).join(models.Parcela).filter(
        models.Invernadero.invernadero_id == invernadero_id,
        models.Parcela.cliente_id == current_user.cliente_id
    ).first()
    
    if not inv:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="No tienes permiso para acceder a este invernadero"
        )
    return True

@router.get("/jornada/invernadero/{invernadero_id}", response_model=schemas.ConfigJornada)
def obtener_jornada(
    invernadero_id: int,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """Obtiene la configuración de jornada de un invernadero."""
    verificar_propiedad_invernadero(invernadero_id, current_user, db)

    path = get_invernadero_config_path(invernadero_id)
    if not os.path.exists(path):
        # Devolver una estructura vacía con es_laborable=True por defecto
        return schemas.ConfigJornada(default=[], es_laborable=True)

    try:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)
            return schemas.ConfigJornada(**data)
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Error al leer la configuración: {str(e)}"
        )

@router.post("/jornada/invernadero/{invernadero_id}", status_code=status.HTTP_200_OK)
def guardar_jornada(
    invernadero_id: int,
    config: schemas.ConfigJornada,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """Guarda la configuración de jornada de un invernadero."""
    verificar_propiedad_invernadero(invernadero_id, current_user, db)

    # Asegurar que el directorio existe
    if not os.path.exists(CONFIG_DIR):
        os.makedirs(CONFIG_DIR)

    path = get_invernadero_config_path(invernadero_id)
    
    try:
        data_to_save = config.model_dump(by_alias=True, exclude_none=False)
        with open(path, "w", encoding="utf-8") as f:
            json.dump(data_to_save, f, indent=2, ensure_ascii=False)
        return {"mensaje": "Configuración guardada correctamente"}
    except Exception as e:
        raise HTTPException(
            status_code=500,
            detail=f"Error al guardar la configuración: {str(e)}"
        )

@router.get("/jornada/cliente/{cliente_id}", response_model=schemas.ConfigJornada)
def obtener_jornada_cliente(
    cliente_id: int,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """Obtiene la configuración de jornada global de un cliente."""
    if current_user.rol not in ["root", "admin"] and current_user.cliente_id != cliente_id:
        raise HTTPException(status_code=403, detail="No autorizado")

    path = get_cliente_config_path(cliente_id)
    if not os.path.exists(path):
        return schemas.ConfigJornada(default=[], es_laborable=True, heredar_de_global=False)

    try:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)
            return schemas.ConfigJornada(**data)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error al leer la configuración global: {str(e)}")

@router.post("/jornada/cliente/{cliente_id}", status_code=status.HTTP_200_OK)
def guardar_jornada_cliente(
    cliente_id: int,
    config: schemas.ConfigJornada,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """Guarda la configuración de jornada global de un cliente."""
    if current_user.rol not in ["root", "admin"] and current_user.cliente_id != cliente_id:
        raise HTTPException(status_code=403, detail="No autorizado")

    if not os.path.exists(CONFIG_DIR):
        os.makedirs(CONFIG_DIR)

    path = get_cliente_config_path(cliente_id)
    try:
        data_to_save = config.model_dump(by_alias=True, exclude_none=False)
        with open(path, "w", encoding="utf-8") as f:
            json.dump(data_to_save, f, indent=2, ensure_ascii=False)
        
        # --- SINCRONIZACIÓN MASIVA ---
        # Al guardar el maestro, todas las naves del cliente se ponen en modo 'heredar'
        invernaderos = db.query(models.Invernadero).join(models.Parcela).filter(
            models.Parcela.cliente_id == cliente_id
        ).all()

        for inv in invernaderos:
            inv_path = get_invernadero_config_path(inv.invernadero_id)
            inv_config = {"default": [], "es_laborable": True} # Default inicial
            
            if os.path.exists(inv_path):
                try:
                    with open(inv_path, "r") as f:
                        inv_config = json.load(f)
                except:
                    pass
            
            inv_config["heredar_de_global"] = True
            
            with open(inv_path, "w") as f:
                json.dump(inv_config, f, indent=2, ensure_ascii=False)

        return {"mensaje": "Configuración global guardada y sincronizada con todas las naves"}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error al guardar y sincronizar: {str(e)}")

@router.get("/jornada/cliente/{cliente_id}/resumen")
def obtener_resumen_jornadas_cliente(
    cliente_id: int,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """Devuelve el estado de configuración de todos los invernaderos de un cliente."""
    if current_user.rol not in ["root", "admin"] and current_user.cliente_id != cliente_id:
        raise HTTPException(status_code=403, detail="No autorizado")

    # Obtener todos los invernaderos del cliente
    invernaderos = db.query(models.Invernadero).join(models.Parcela).filter(
        models.Parcela.cliente_id == cliente_id
    ).all()

    resumen = []
    for inv in invernaderos:
        path = get_invernadero_config_path(inv.invernadero_id)
        configurado = os.path.exists(path)
        es_laborable = True
        heredar_de_global = False
        
        if configurado:
            try:
                with open(path, "r") as f:
                    data = json.load(f)
                    es_laborable = data.get("es_laborable", True)
                    heredar_de_global = data.get("heredar_de_global", False)
            except:
                pass
        
        resumen.append({
            "invernadero_id": inv.invernadero_id,
            "nombre": inv.nombre,
            "parcela_nombre": inv.parcela.nombre or inv.parcela.ref_catastral,
            "configurado": configurado,
            "es_laborable": es_laborable,
            "heredar_de_global": heredar_de_global
        })

    return resumen

@router.delete("/jornada/cliente/{cliente_id}/reset", status_code=status.HTTP_200_OK)
def resetear_jornada_cliente(
    cliente_id: int,
    current_user: models.Cliente = Depends(auth.get_current_user),
    db: Session = Depends(database.get_db)
):
    """
    Elimina TODA la configuración de jornada de un cliente.
    1. Borra el JSON maestro del cliente.
    2. Borra los JSON individuales de todas sus naves.
    """
    if current_user.rol not in ["root", "admin"] and current_user.cliente_id != cliente_id:
        raise HTTPException(status_code=403, detail="No autorizado")

    # 1. Borrar configuración maestra
    path_maestro = get_cliente_config_path(cliente_id)
    if os.path.exists(path_maestro):
        os.remove(path_maestro)

    # 2. Borrar configuraciones de naves
    invernaderos = db.query(models.Invernadero).join(models.Parcela).filter(
        models.Parcela.cliente_id == cliente_id
    ).all()

    borrados = 0
    for inv in invernaderos:
        path_inv = get_invernadero_config_path(inv.invernadero_id)
        if os.path.exists(path_inv):
            os.remove(path_inv)
            borrados += 1

    return {
        "mensaje": "Configuración maestra e individual reseteada correctamente",
        "naves_limpiadas": borrados
    }
