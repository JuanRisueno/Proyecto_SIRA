"""
=============================================================================
            CRUD DE CULTIVOS (crud/crud_cultivos.py)
=============================================================================
Propósito:
Gestionar el catálogo botánico y sus parámetros técnicos asociados.
Centraliza la lógica de joins para obtener datos técnicos (T/H) de forma atómica.
"""
from sqlalchemy.orm import Session
from sqlalchemy import or_, func, String
from typing import Optional, List
from .. import models, schemas

def get_cultivo(db: Session, cultivo_id: int):
    """Busca un cultivo por su ID."""
    return db.query(models.Cultivo).filter(models.Cultivo.cultivo_id == cultivo_id).first()

def get_cultivo_by_nombre(db: Session, nombre: str):
    """Busca un cultivo por nombre (case-insensitive)."""
    return db.query(models.Cultivo).filter(models.Cultivo.nombre_cultivo.ilike(nombre)).first()

def get_cultivo_completo(db: Session, cultivo_id: int):
    """
    Recupera un cultivo con su nombre de cliente asociado y parámetros óptimos.
    """
    return db.query(
        models.Cultivo,
        models.Cliente.nombre_empresa.label("nombre_cliente")
    ).outerjoin(models.Cliente, models.Cultivo.cliente_id == models.Cliente.cliente_id
    ).filter(models.Cultivo.cultivo_id == cultivo_id).first()

def get_cultivos(db: Session, skip: int = 0, limit: int = 200, ver_inactivos: bool = False, q: str = None):
    """
    Listado maestro de cultivos con Join a clientes y soporte de búsqueda.
    """
    query = db.query(
        models.Cultivo,
        models.Cliente.nombre_empresa.label("nombre_cliente")
    ).outerjoin(models.Cliente, models.Cultivo.cliente_id == models.Cliente.cliente_id)

    if not ver_inactivos:
        query = query.filter(models.Cultivo.activa == True)
    
    if q:
        search = f"%{q}%"
        query = query.filter(
            func.unaccent(models.Cultivo.nombre_cultivo.cast(String)).ilike(func.unaccent(search))
        )
        
    return query.order_by(models.Cultivo.nombre_cultivo).offset(skip).limit(limit).all()

def create_cultivo(db: Session, cultivo: schemas.CultivoCreate, cliente_id: Optional[int] = None):
    """
    Crea un cultivo y sus parámetros óptimos (Fase General) en una sola transacción.
    """
    db_cultivo = models.Cultivo(
        nombre_cultivo=cultivo.nombre_cultivo,
        cliente_id=cliente_id,
        activa=True
    )
    db.add(db_cultivo)
    db.flush() 

    if cultivo.parametros:
        db_params = models.ParametrosOptimos(
            cultivo_id=db_cultivo.cultivo_id,
            fase_crecimiento="General",
            temp_optima_min=cultivo.parametros.temp_optima_min,
            temp_optima_max=cultivo.parametros.temp_optima_max,
            humedad_optima_min=cultivo.parametros.humedad_optima_min,
            humedad_optima_max=cultivo.parametros.humedad_optima_max,
            necesidad_hidrica=cultivo.parametros.necesidad_hidrica,
            ph_ideal=cultivo.parametros.ph_ideal
        )
        db.add(db_params)
    
    db.commit()
    db.refresh(db_cultivo)
    return db_cultivo

def update_cultivo(db: Session, cultivo_id: int, cultivo_update: schemas.CultivoCreate):
    """
    Actualiza nombre y parámetros técnicos de un cultivo.
    """
    db_cultivo = db.query(models.Cultivo).filter(models.Cultivo.cultivo_id == cultivo_id).first()
    if not db_cultivo:
        return None
    
    db_cultivo.nombre_cultivo = cultivo_update.nombre_cultivo
    
    if cultivo_update.parametros:
        db_params = db.query(models.ParametrosOptimos).filter(
            models.ParametrosOptimos.cultivo_id == cultivo_id,
            models.ParametrosOptimos.fase_crecimiento == "General"
        ).first()
        
        if db_params:
            db_params.temp_optima_min = cultivo_update.parametros.temp_optima_min
            db_params.temp_optima_max = cultivo_update.parametros.temp_optima_max
            db_params.humedad_optima_min = cultivo_update.parametros.humedad_optima_min
            db_params.humedad_optima_max = cultivo_update.parametros.humedad_optima_max
            db_params.necesidad_hidrica = cultivo_update.parametros.necesidad_hidrica
            db_params.ph_ideal = cultivo_update.parametros.ph_ideal
        else:
            db_params = models.ParametrosOptimos(
                cultivo_id=cultivo_id,
                fase_crecimiento="General",
                **cultivo_update.parametros.dict()
            )
            db.add(db_params)

    db.commit()
    db.refresh(db_cultivo)
    return db_cultivo

def set_cultivo_status(db: Session, cultivo_id: int, activa: bool):
    """Cambia el estado de activación (visibilidad) de un cultivo."""
    db_cultivo = db.query(models.Cultivo).filter(models.Cultivo.cultivo_id == cultivo_id).first()
    if db_cultivo:
        db_cultivo.activa = activa
        db.commit()
        db.refresh(db_cultivo)
        return db_cultivo
    return None
