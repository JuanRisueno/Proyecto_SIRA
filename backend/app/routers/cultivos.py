from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List, Optional
from .. import crud, models, schemas, auth
from ..database import SessionLocal

router = APIRouter(
    prefix="/api/v1",
    tags=["Catálogo de Cultivos"]
)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

@router.post("/cultivos/", response_model=schemas.Cultivo, status_code=status.HTTP_201_CREATED, summary="Crear Cultivo")
def crear_cultivo(
    cultivo: schemas.CultivoCreate, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """Registra un nuevo cultivo y sus parámetros técnicos."""
    existente = crud.get_cultivo_by_nombre(db, nombre=cultivo.nombre_cultivo)
    if existente:
        raise HTTPException(status_code=400, detail="Ya existe un cultivo con este nombre.")
    
    # Lógica de propiedad delegada al CRUD
    cliente_id = current_user.cliente_id if current_user.rol == "cliente" else (cultivo.cliente_id or None)
    return crud.create_cultivo(db=db, cultivo=cultivo, cliente_id=cliente_id)

@router.get("/cultivos/", response_model=List[schemas.Cultivo], summary="Listar Cultivos")
def listar_cultivos(
    skip: int = 0, 
    limit: int = 200, 
    ver_inactivos: bool = False,
    q: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Devuelve el catálogo de cultivos con sus parámetros (V11 Modular)."""
    results = crud.get_cultivos(db, skip=skip, limit=limit, ver_inactivos=ver_inactivos, q=q)
    
    cultivos = []
    for c_model, nombre_cli in results:
        c_dict = schemas.Cultivo.model_validate(c_model)
        c_dict.nombre_cliente = nombre_cli if c_model.cliente_id else "🌱 Sistema SIRA"
        
        # Inyectar parámetros de fase 'General'
        params_general = next((p for p in c_model.parametros_optimos if p.fase_crecimiento == "General"), None)
        if params_general:
            c_dict.parametros = schemas.ParametrosNested.model_validate(params_general)
            
        cultivos.append(c_dict)
        
    return cultivos

@router.get("/cultivos/{cultivo_id}", response_model=schemas.Cultivo, summary="Leer Cultivo")
def leer_cultivo(cultivo_id: int, db: Session = Depends(get_db)):
    result = crud.get_cultivo_completo(db, cultivo_id=cultivo_id)
    if result is None:
        raise HTTPException(status_code=404, detail="Cultivo no encontrado")
    
    c_model, nombre_cli = result
    c_dict = schemas.Cultivo.model_validate(c_model)
    c_dict.nombre_cliente = nombre_cli if c_model.cliente_id else "🌱 Sistema SIRA"
    
    params_general = next((p for p in c_model.parametros_optimos if p.fase_crecimiento == "General"), None)
    if params_general:
        c_dict.parametros = schemas.ParametrosNested.model_validate(params_general)
        
    return c_dict

@router.put("/cultivos/{cultivo_id}", response_model=schemas.Cultivo, summary="Actualizar Cultivo")
def actualizar_cultivo(
    cultivo_id: int, 
    cultivo_update: schemas.CultivoCreate, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """Actualiza datos botánicos y parámetros en una sola operación."""
    db_cultivo = crud.get_cultivo(db, cultivo_id=cultivo_id)
    if not db_cultivo:
        raise HTTPException(status_code=404, detail="Cultivo no encontrado")
    
    if current_user.rol == "cliente" and db_cultivo.cliente_id != current_user.cliente_id:
        raise HTTPException(status_code=403, detail="No puedes editar este cultivo.")
    
    return crud.update_cultivo(db=db, cultivo_id=cultivo_id, cultivo_update=cultivo_update)

@router.patch("/cultivos/{cultivo_id}/status", response_model=schemas.Cultivo, summary="Ocultar/Activar Cultivo")
def cambiar_status_cultivo(
    cultivo_id: int, 
    activa: bool, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.require_admin)
):
    """Cambia la visibilidad del cultivo en el catálogo maestro."""
    db_cult = crud.set_cultivo_status(db, cultivo_id=cultivo_id, activa=activa)
    if not db_cult:
        raise HTTPException(status_code=404, detail="Cultivo no encontrado")
    return db_cult
