from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List
from .. import crud, models, schemas, auth
from ..database import SessionLocal

router = APIRouter(
    prefix="/api/v1",
    tags=["Infraestructura (Parcelas e Invernaderos)"]
)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# --- 1. PARCELAS ---

@router.post("/parcelas/", response_model=schemas.Parcela, status_code=status.HTTP_201_CREATED, summary="Crear Parcela")
def crear_parcela(parcela: schemas.ParcelaCreate, db: Session = Depends(get_db)):
    from sqlalchemy.exc import IntegrityError
    
    if not crud.get_cliente(db, cliente_id=parcela.cliente_id):
        raise HTTPException(status_code=404, detail="Cliente no encontrado")
    if not crud.get_localidad(db, codigo_postal=parcela.codigo_postal):
        raise HTTPException(status_code=404, detail="CP no registrado")
    
    try:
        return crud.create_parcela(db=db, parcela=parcela)
    except IntegrityError as e:
        db.rollback()
        # Analizamos el error para dar un mensaje más humano
        error_msg = str(e.orig)
        if "ref_catastral" in error_msg:
            detail = "La Referencia Catastral ya está registrada en el sistema."
        else:
            detail = f"Error de integridad en la base de datos: {error_msg}"
        raise HTTPException(status_code=400, detail=detail)
    except Exception as e:
        db.rollback()
        raise HTTPException(status_code=500, detail=f"Error interno del servidor: {str(e)}")

@router.get("/parcelas/cliente/{cliente_id}", response_model=List[schemas.Parcela], summary="Listar Parcelas de un Cliente")
def listar_parcelas_cliente(cliente_id: int, db: Session = Depends(get_db)):
    return crud.get_parcelas_por_cliente(db, cliente_id=cliente_id)

@router.get("/parcelas/localidad/{codigo_postal}", response_model=List[schemas.Parcela], summary="Listar Parcelas por Localidad")
def listar_parcelas_localidad(codigo_postal: str, db: Session = Depends(get_db)):
    """Devuelve todas las parcelas registradas en un CP (Uso administrativo)."""
    return crud.get_parcelas_por_localidad(db, codigo_postal=codigo_postal)

@router.get("/parcelas/{parcela_id}", response_model=schemas.Parcela, summary="Leer Parcela")
def leer_parcela(parcela_id: int, db: Session = Depends(get_db)):
    db_parc = crud.get_parcela(db, parcela_id=parcela_id)
    if not db_parc:
        raise HTTPException(status_code=404, detail="Parcela no encontrada")
    return db_parc

@router.put("/parcelas/{parcela_id}", response_model=schemas.Parcela, summary="Actualizar Parcela")
def actualizar_parcela(parcela_id: int, parcela_update: schemas.ParcelaUpdate, db: Session = Depends(get_db)):
    db_parcela = crud.update_parcela(db=db, parcela_id=parcela_id, parcela_update=parcela_update)
    if not db_parcela:
        raise HTTPException(status_code=404, detail="Parcela no encontrada")
    return db_parcela

@router.put("/parcelas/{parcela_id}/restaurar_invernaderos", status_code=status.HTTP_204_NO_CONTENT, summary="Restaurar Invernaderos en Cascada")
def restaurar_invernaderos_cascada(parcela_id: int, db: Session = Depends(get_db)):
    """Activa todos los invernaderos vinculados a una parcela."""
    db.query(models.Invernadero).filter(models.Invernadero.parcela_id == parcela_id).update({"activa": True})
    db.commit()
    return None

@router.delete("/parcelas/{parcela_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Borrar Parcela")
def borrar_parcela(parcela_id: int, db: Session = Depends(get_db)):
    if not crud.delete_parcela(db, parcela_id):
        raise HTTPException(status_code=404, detail="Parcela no encontrada")
    return None

# --- 2. INVERNADEROS ---

@router.post("/invernaderos/", response_model=schemas.Invernadero, status_code=status.HTTP_201_CREATED, summary="Crear Invernadero")
def crear_invernadero(invernadero: schemas.InvernaderoCreate, db: Session = Depends(get_db)):
    if not crud.get_parcela(db, parcela_id=invernadero.parcela_id):
        raise HTTPException(status_code=404, detail="La parcela no existe")
    return crud.create_invernadero(db=db, invernadero=invernadero)

@router.get("/invernaderos/cliente/{cliente_id}", response_model=List[schemas.Invernadero], summary="Listar Invernaderos de un Cliente")
def listar_invernaderos_cliente(cliente_id: int, db: Session = Depends(get_db)):
    return crud.get_invernaderos_por_cliente(db, cliente_id=cliente_id)

@router.get("/invernaderos/{invernadero_id}", response_model=schemas.Invernadero, summary="Leer Invernadero")
def leer_invernadero(invernadero_id: int, db: Session = Depends(get_db)):
    db_inv = crud.get_invernadero(db, invernadero_id=invernadero_id)
    if not db_inv:
        raise HTTPException(status_code=404, detail="Invernadero no encontrado")
    return db_inv

@router.put("/invernaderos/{invernadero_id}", response_model=schemas.Invernadero, summary="Actualizar Invernadero")
def actualizar_invernadero(
    invernadero_id: int, 
    invernadero_update: schemas.InvernaderoUpdate, 
    db: Session = Depends(get_db)
):
    """Actualiza datos del invernadero (nombre, dimensiones, cultivo)."""
    db_inv = crud.update_invernadero(db=db, invernadero_id=invernadero_id, invernadero_update=invernadero_update)
    if not db_inv:
        raise HTTPException(status_code=404, detail="Invernadero no encontrado")
    return db_inv

@router.delete("/invernaderos/{invernadero_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Borrar Invernadero")
def borrar_invernadero(invernadero_id: int, db: Session = Depends(get_db)):
    if not crud.delete_invernadero(db, invernadero_id):
        raise HTTPException(status_code=404, detail="Invernadero no encontrado")
    return None

# --- 3. JERARQUÍA DEL DASHBOARD (Motor CRUD V11) ---

@router.get("/clientes/me/jerarquia", response_model=schemas.JerarquiaCliente, summary="Obtener Jerarquía del Dashboard")
def obtener_jerarquia(
    cliente_id: int = None,
    ver_ocultos: bool = False,
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """
    Construye y devuelve el árbol de infraestructura estructurado.
    Utiliza el motor optimizado migrado a la capa CRUD.
    """
    target_id = current_user.cliente_id
    nombre_mostrar = current_user.nombre_empresa

    if cliente_id is not None and current_user.rol in ["admin", "root"]:
        target_cliente = crud.get_cliente(db, cliente_id)
        if not target_cliente:
            raise HTTPException(status_code=404, detail="Cliente no encontrado")
        
        if current_user.rol == "admin" and target_cliente.rol != "cliente":
             raise HTTPException(status_code=403, detail="Acceso denegado a perfiles admin.")
        
        target_id = cliente_id
        nombre_mostrar = target_cliente.nombre_empresa

    # Llamada al nuevo motor CRUD (Fase 1)
    localidades_jerarquia = crud.get_jerarquia_datos(db, target_id, activa_only=not ver_ocultos)

    return schemas.JerarquiaCliente(
        cliente_id=target_id,
        nombre_empresa=nombre_mostrar,
        num_localidades=len(localidades_jerarquia),
        localidades=localidades_jerarquia
    )
