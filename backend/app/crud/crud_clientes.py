# backend/app/crud/crud_clientes.py

"""
=============================================================================
            CRUD DE CLIENTES (crud/crud_clientes.py)
=============================================================================
Propósito:
Gestionar las operaciones de base de datos de la tabla 'Cliente'.
Asegura que todas las contraseñas se guarden con hashing profesional.
"""

from fastapi import HTTPException
from sqlalchemy.orm import Session
from sqlalchemy import or_, func, String
from typing import Optional

# Importaciones locales
from .. import models, schemas, auth

# --- 1. LEER (SELECT) ---

def get_cliente(db: Session, cliente_id: int):
    """Busca un cliente por su ID interno."""
    return db.query(models.Cliente).filter(models.Cliente.cliente_id == cliente_id).first()

def get_cliente_by_cif(db: Session, cif: str):
    """Busca un cliente por su CIF único."""
    return db.query(models.Cliente).filter(models.Cliente.cif == cif).first()

def get_cliente_by_nombre(db: Session, nombre_empresa: str):
    """Busca un cliente por su nombre de empresa (usado para validar unicidad en admins)."""
    # Buscamos ignorando mayúsculas/minúsculas para mayor robustez
    return db.query(models.Cliente).filter(func.lower(models.Cliente.nombre_empresa) == func.lower(nombre_empresa)).first()

def get_clientes(db: Session, skip: int = 0, limit: int = 100, q: str = None):
    """
    Listado de clientes con búsqueda por texto y triple criterio de ordenamiento:
    1. Usuarios En Línea (actividad < 5 min)
    2. Jerarquía de Rol (Root > Admin > Cliente)
    3. ID de registro
    """
    from sqlalchemy import case, and_
    from datetime import datetime, timedelta
    
    query = db.query(models.Cliente)
    
    if q:
        search = f"%{q}%"
        query = query.filter(
            or_(
                func.unaccent(models.Cliente.nombre_empresa.cast(String)).ilike(func.unaccent(search)),
                func.unaccent(models.Cliente.persona_contacto.cast(String)).ilike(func.unaccent(search)),
                func.unaccent(models.Cliente.email_admin.cast(String)).ilike(func.unaccent(search)),
                models.Cliente.cif.ilike(search)
            )
        )
    
    # 1. Definimos estado Online: Tiene un session_id Y ha estado activo en el periodo de vida del token (30 mins)
    umbral_online = datetime.now() - timedelta(minutes=30)
    is_online = case(
        (and_(models.Cliente.session_id.isnot(None), models.Cliente.ultima_actividad > umbral_online), 1), 
        else_=0
    )
    
    # 2. Definimos jerarquía de roles
    rango_prioridad = case(
        (models.Cliente.rol == "root", 0),
        (models.Cliente.rol == "admin", 1),
        else_=2
    )

    return query.order_by(
        is_online.desc(), 
        rango_prioridad.asc(), 
        models.Cliente.cliente_id.asc()
    ).offset(skip).limit(limit).all()


# --- 2. ESCRIBIR (INSERT) ---

def create_cliente(db: Session, cliente: schemas.ClienteCreate, rol: str = "cliente"):
    """
    Registra un nuevo cliente aplicando HASHING a la contraseña.
    """
    # Hashear la contraseña antes de guardarla
    hashed_password = auth.get_password_hash(cliente.password)
    
    db_cliente = models.Cliente(
        nombre_empresa=cliente.nombre_empresa,
        cif=cliente.cif,
        email_admin=cliente.email_admin,
        telefono=cliente.telefono,
        persona_contacto=cliente.persona_contacto,
        hash_contrasena=hashed_password, # <--- GUARDADO SEGURO
        rol=rol,
        activa=True
    )
    
    db.add(db_cliente)
    db.commit()
    db.refresh(db_cliente)
    
    return db_cliente


# --- 3. ACTUALIZAR (UPDATE) ---

def update_cliente(db: Session, cliente_id: int, cliente_update: schemas.ClienteUpdate):
    """Actualiza datos del cliente, incluyendo cambio seguro de contraseña."""
    db_cliente = db.query(models.Cliente).filter(models.Cliente.cliente_id == cliente_id).first()
    if not db_cliente:
        return None

    # Validar duplicidad de CIF si se intenta cambiar
    if cliente_update.cif is not None and cliente_update.confirmar_cambio_cif:
        otro = db.query(models.Cliente).filter(models.Cliente.cif == cliente_update.cif).first()
        if otro and otro.cliente_id != cliente_id:
            raise ValueError(f"El CIF {cliente_update.cif} ya está en uso.")
        db_cliente.cif = cliente_update.cif

    # Procesar actualización dinámica
    update_data = cliente_update.model_dump(exclude_unset=True)
    
    # Si viene una nueva contraseña, aplicamos seguridad extrema (Iron Fortress)
    if "password" in update_data:
        nueva_pass = update_data.pop("password")
        
        # 1. Validar complejidad antes de nada
        if not auth.validate_password_complexity(nueva_pass, rol=db_cliente.rol):
            m_len = 10 if db_cliente.rol in ["root", "admin"] else 8
            raise HTTPException(
                status_code=400, 
                detail=f"La contraseña no cumple los requisitos (Mínimo {m_len} caracteres, Mayús, Minús, Núm y Símbolo)"
            )
        
        # 2. Validar que no ha sido usada recientemente (JSON History)
        from .. import security_history
        if security_history.check_password_reuse(cliente_id, nueva_pass):
            raise HTTPException(
                status_code=400,
                detail="No puede ser una contraseña ya usada recientemente."
            )
            
        # 3. Todo OK -> Generar hash y Guardar en BBDD + JSON
        nuevo_hash = auth.get_password_hash(nueva_pass)
        db_cliente.hash_contrasena = nuevo_hash
        db_cliente.debe_cambiar_pw = False 
        
        security_history.record_new_password(cliente_id, nuevo_hash)

    # Limpiar campos de control
    update_data.pop("confirmar_cambio_cif", None)
    update_data.pop("cif", None)

    # Actualizar resto de campos
    for key, value in update_data.items():
        if hasattr(db_cliente, key):
            setattr(db_cliente, key, value)

    db.commit()
    db.refresh(db_cliente)
    return db_cliente


# --- 4. ESTADO Y BORRADO ---

def set_cliente_status(db: Session, cliente_id: int, activa: bool):
    """Cambio de estado (Soft Delete)."""
    db_cliente = get_cliente(db, cliente_id)
    if db_cliente:
        db_cliente.activa = activa
        db.commit()
        db.refresh(db_cliente)
        return db_cliente
    return None

def delete_cliente(db: Session, cliente_id: int):
    """Eliminado físico de la base de datos."""
    db_cliente = get_cliente(db, cliente_id)
    if db_cliente:
        db.delete(db_cliente)
        db.commit()
        return True
    return False
