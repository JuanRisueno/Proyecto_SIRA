from sqlalchemy.orm import Session
from sqlalchemy import or_, func, String
from typing import Optional, List
from .. import models, schemas

# --- LOCALIDADES ---
def get_localidad(db: Session, codigo_postal: str):
    return db.query(models.Localidad).filter(models.Localidad.codigo_postal == codigo_postal).first()

def get_localidades(db: Session, skip: int = 0, limit: int = 1000, q: Optional[str] = None):
    query = db.query(models.Localidad)
    if q:
        search_filter = f"%{q}%"
        query = query.filter(
            or_(
                func.unaccent(models.Localidad.municipio.cast(String)).ilike(func.unaccent(search_filter)),
                func.unaccent(models.Localidad.provincia.cast(String)).ilike(func.unaccent(search_filter)),
                models.Localidad.codigo_postal.ilike(search_filter)
            )
        )
    return query.order_by(func.unaccent(models.Localidad.municipio.cast(String))).offset(skip).limit(limit).all()

def create_localidad(db: Session, localidad: schemas.LocalidadCreate):
    db_localidad = models.Localidad(**localidad.model_dump())
    db.add(db_localidad)
    db.commit()
    db.refresh(db_localidad)
    return db_localidad

def update_localidad(db: Session, codigo_postal: str, localidad_update: schemas.LocalidadUpdate):
    db_localidad = db.query(models.Localidad).filter(models.Localidad.codigo_postal == codigo_postal).first()
    if not db_localidad:
        return None
    
    # [V11.1] Usamos exclude_unset para permitir actualizaciones parciales y valores nulos explícitos
    update_data = localidad_update.model_dump(exclude_unset=True)
    for key, value in update_data.items():
        setattr(db_localidad, key, value)
        
    db.commit()
    db.refresh(db_localidad)
    return db_localidad


# --- CULTIVOS (Legacy fallback) ---
def get_cultivo(db: Session, cultivo_id: int):
    return db.query(models.Cultivo).filter(models.Cultivo.cultivo_id == cultivo_id).first()

def get_cultivos(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Cultivo).offset(skip).limit(limit).all()

def create_cultivo(db: Session, cultivo: schemas.CultivoCreate):
    db_cultivo = models.Cultivo(**cultivo.model_dump())
    db.add(db_cultivo)
    db.commit()
    db.refresh(db_cultivo)
    return db_cultivo

# --- PARCELAS ---
def get_parcela(db: Session, parcela_id: int):
    return db.query(models.Parcela).filter(models.Parcela.parcela_id == parcela_id).first()

def get_parcelas(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Parcela).offset(skip).limit(limit).all()

def create_parcela(db: Session, parcela: schemas.ParcelaCreate):
    db_parcela = models.Parcela(**parcela.model_dump())
    db.add(db_parcela)
    db.commit()
    db.refresh(db_parcela)
    return db_parcela

def get_parcelas_por_cliente(db: Session, cliente_id: int):
    return db.query(models.Parcela).filter(models.Parcela.cliente_id == cliente_id).order_by(models.Parcela.parcela_id).all()

def get_parcelas_por_localidad(db: Session, codigo_postal: str):
    return db.query(models.Parcela).filter(models.Parcela.codigo_postal == codigo_postal).all()

def update_parcela(db: Session, parcela_id: int, parcela_update: schemas.ParcelaUpdate):
    db_parcela = db.query(models.Parcela).filter(models.Parcela.parcela_id == parcela_id).first()
    if not db_parcela:
        return None

    # [V11.1] Manejo robusto de actualización
    update_data = parcela_update.model_dump(exclude_unset=True)

    for key, value in update_data.items():
        if key != "confirmar_cambio_ref":
            setattr(db_parcela, key, value)
            
    db.commit()
    db.refresh(db_parcela)
    return db_parcela

def delete_parcela(db: Session, parcela_id: int):
    db_parcela = db.query(models.Parcela).filter(models.Parcela.parcela_id == parcela_id).first()
    if db_parcela:
        # [V12.1] Cascada Jerárquica: Al archivar parcela, archivar todos sus invernaderos
        db_parcela.activa = False
        db.query(models.Invernadero).filter(models.Invernadero.parcela_id == parcela_id).update({"activa": False})
        
        db.commit()
        return True
    return False

# --- INVERNADEROS ---
def get_invernadero(db: Session, invernadero_id: int):
    return db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()

def get_invernaderos(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Invernadero).order_by(models.Invernadero.invernadero_id).offset(skip).limit(limit).all()

def create_invernadero(db: Session, invernadero: schemas.InvernaderoCreate):
    db_invernadero = models.Invernadero(**invernadero.model_dump())
    db.add(db_invernadero)
    db.commit()
    db.refresh(db_invernadero)
    return db_invernadero

def get_invernaderos_por_cliente(db: Session, cliente_id: int):
    return db.query(models.Invernadero).join(models.Parcela).filter(models.Parcela.cliente_id == cliente_id).order_by(models.Invernadero.invernadero_id).all()

def update_invernadero(db: Session, invernadero_id: int, invernadero_update: schemas.InvernaderoUpdate):
    db_invernadero = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
    if not db_invernadero:
        return None

    # [V11.1] Permitir nulos explícitos (ej: cultivo_id = null)
    update_data = invernadero_update.model_dump(exclude_unset=True)
    for key, value in update_data.items():
        setattr(db_invernadero, key, value)
            
    db.commit()
    db.refresh(db_invernadero)
    return db_invernadero

def delete_invernadero(db: Session, invernadero_id: int):
    db_invernadero = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
    if db_invernadero:
        db_invernadero.activa = False
        db.commit()
        return True
    return False

# --- JERARQUÍA DEL DASHBOARD ---
def get_jerarquia_datos(db: Session, target_cliente_id: int, activa_only: bool = True):
    query = db.query(models.Parcela).filter(models.Parcela.cliente_id == target_cliente_id)
    if activa_only:
        query = query.filter(models.Parcela.activa == True)
    
    parcelas_db = query.order_by(models.Parcela.parcela_id).all()
    estruct_localidades = {}
    
    for parcela in parcelas_db:
        cp = parcela.localidad.codigo_postal
        if cp not in estruct_localidades:
            estruct_localidades[cp] = {
                "codigo_postal": str(cp),
                "municipio": parcela.localidad.municipio,
                "provincia": parcela.localidad.provincia,
                "num_parcelas": 0,
                "num_invernaderos_total": 0,
                "parcelas": []
            }
            
        invernaderos_lista = []
        # [ORDEN ESTABLE]
        invernaderos_objs = sorted(parcela.invernaderos, key=lambda x: x.invernadero_id)
        for inv in invernaderos_objs:
            if activa_only and not inv.activa:
                continue
                
            invernaderos_lista.append({
                "invernadero_id": inv.invernadero_id,
                "nombre": inv.nombre,
                "largo_m": inv.largo_m,
                "ancho_m": inv.ancho_m,
                "cultivo": inv.cultivo.nombre_cultivo if inv.cultivo else None,
                "activa": inv.activa
            })
            
        parcela_data = {
            "parcela_id": parcela.parcela_id,
            "nombre": parcela.nombre,
            "direccion": parcela.direccion,
            "ref_catastral": parcela.ref_catastral,
            "activa": parcela.activa,
            "num_invernaderos": len(invernaderos_lista),
            "invernaderos": invernaderos_lista
        }
        
        estruct_localidades[cp]["parcelas"].append(parcela_data)
        estruct_localidades[cp]["num_parcelas"] += 1
        estruct_localidades[cp]["num_invernaderos_total"] += len(invernaderos_lista)
        
    return list(estruct_localidades.values())