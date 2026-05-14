from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List, Optional
from .. import crud, models, schemas, auth
from ..database import SessionLocal

router = APIRouter(
    prefix="/api/v1",
    tags=["Gestión de Clientes"]
)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

@router.post("/clientes/", response_model=schemas.Cliente, status_code=status.HTTP_201_CREATED, summary="Crear Cliente")
def crear_cliente(
    cliente: schemas.ClienteCreate, 
    db: Session = Depends(get_db),
    current_user: Optional[models.Cliente] = Depends(auth.get_current_user_optional)
):
    """Registra una nueva empresa/agricultor en SIRA."""
    if cliente.rol != "cliente":
        if not current_user or current_user.rol != "root":
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail="Solo el usuario Root tiene permisos para asignar roles administrativos."
            )
        if cliente.rol == "root":
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail="No se pueden crear más usuarios de tipo 'root'."
            )

    db_cliente = crud.get_cliente_by_cif(db, cif=cliente.cif)
    if db_cliente:
        raise HTTPException(status_code=400, detail="El cliente con este CIF ya está registrado.")
        
    # Validación: No puede haber 2 admins o root con el mismo nombre
    if cliente.rol in ["admin", "root"]:
        existente_nombre = crud.get_cliente_by_nombre(db, nombre_empresa=cliente.nombre_empresa)
        if existente_nombre and existente_nombre.rol in ["admin", "root"]:
            raise HTTPException(status_code=400, detail=f"Ya existe un administrador con el nombre '{cliente.nombre_empresa}'.")
    
    return crud.create_cliente(db=db, cliente=cliente, rol=cliente.rol)

@router.get("/clientes/", response_model=List[schemas.Cliente], summary="Listar Clientes")
def listar_clientes(
    skip: int = 0, 
    limit: int = 1000, 
    q: Optional[str] = None,
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.require_admin)
):
    """Lista todos los clientes registrados. Solo para administradores."""
    return crud.get_clientes(db, skip=skip, limit=limit, q=q)

@router.get("/clientes/{cliente_id}", response_model=schemas.Cliente, summary="Obtener Cliente Individual")
def obtener_cliente(
    cliente_id: int, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """Obtiene los detalles de un cliente. Solo admin o el propio usuario."""
    db_cliente = crud.get_cliente(db, cliente_id=cliente_id)
    if not db_cliente:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")
    
    # Verificación de permisos
    if current_user.rol not in ["admin", "root"] and current_user.cliente_id != cliente_id:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN, 
            detail="No tienes permiso para ver este perfil."
        )
    
    return db_cliente

@router.patch("/clientes/{cliente_id}/status", response_model=schemas.Cliente, summary="Activar/Desactivar Cliente")
def cambiar_estado_cliente(
    cliente_id: int, 
    activa: bool, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.require_admin)
):
    """Cambia la visibilidad de un cliente (Borrado Lógico)."""
    db_cliente = crud.get_cliente(db, cliente_id=cliente_id)
    if not db_cliente:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")
    
    if current_user.cliente_id == cliente_id:
        raise HTTPException(status_code=403, detail="Operación denegada. No puedes desactivar tu propia cuenta.")
    
    if db_cliente.rol == "root" and not activa:
        raise HTTPException(status_code=403, detail="El administrador principal (root) no puede ser desactivado para garantizar el acceso al sistema.")

    if current_user.rol == "admin" and db_cliente.rol != "cliente":
        raise HTTPException(status_code=403, detail="Un administrador no tiene permisos para cambiar el estado de otros perfiles administrativos.")
    
    return crud.set_cliente_status(db, cliente_id=cliente_id, activa=activa)

@router.put("/clientes/{cliente_id}", response_model=schemas.Cliente, summary="Actualizar Cliente")
def actualizar_cliente(
    cliente_id: int, 
    cliente_update: schemas.ClienteUpdate, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.get_current_user)
):
    """Permite a un usuario editar su propia información o al Admin editar perfiles."""
    db_cliente_actual = crud.get_cliente(db, cliente_id=cliente_id)
    if not db_cliente_actual:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")

    # --- LÓGICA DE PERMISOS JERÁRQUICOS ---
    
    # 1. Root: Control total, pero autoprotegido
    if current_user.rol == "root":
        # Impedir que el root deje de ser root o se degrade
        if cliente_update.rol and cliente_update.rol != "root" and db_cliente_actual.rol == "root":
            raise HTTPException(status_code=403, detail="El sistema requiere exactamente un usuario 'root'. No puedes cambiar tu propio rol.")
        # Impedir que el root cree otro root (ya se controla en POST, pero reforzamos aquí)
        if cliente_update.rol == "root" and db_cliente_actual.rol != "root":
            raise HTTPException(status_code=403, detail="Ya existe un usuario 'root' en el sistema. No se permiten duplicados.")
    
    # 2. Admin: Solo puede editar a 'clientes' o a SÍ MISMO
    elif current_user.rol == "admin":
        if db_cliente_actual.rol != "cliente" and current_user.cliente_id != cliente_id:
             raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail="Un administrador solo tiene permiso para gestionar cuentas de clientes estándar."
            )
        # RESTRICCIÓN DE ROL: Solo el root puede cambiar el rol de un usuario
        if cliente_update.rol and cliente_update.rol != db_cliente_actual.rol:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail="Operación denegada. Solo el usuario Root tiene permisos para cambiar el tipo de usuario (rol)."
            )
    
    # 3. Cliente: Autogestión limitada (No puede cambiar su identidad legal ni su rol)
    elif current_user.rol == "cliente":
        if current_user.cliente_id != cliente_id:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail="No tienes permiso para editar el perfil de otro usuario."
            )
        
        # Validar intento de cambio en campos protegidos
        upd_data = cliente_update.model_dump(exclude_unset=True)
        # Campos que el cliente tiene prohibido CAMBIAR el valor original
        protegidos = {
            "cif": db_cliente_actual.cif,
            "rol": db_cliente_actual.rol,
            "confirmar_cambio_cif": False
        }
        
        prohibidos = [k for k, v in upd_data.items() if k in protegidos and v != protegidos[k]]
        
        # Detectar campos desconocidos o maliciosos no permitidos por el esquema
        permitidos = {"nombre_empresa", "persona_contacto", "email_admin", "telefono", "password"}.union(protegidos.keys())
        desconocidos = [k for k in upd_data.keys() if k not in permitidos]
        
        if prohibidos or desconocidos:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN, 
                detail=f"Operación no permitida. Los siguientes campos están bloqueados para el perfil cliente: {prohibidos + desconocidos}"
            )
    else:
        raise HTTPException(status_code=403, detail="Rol no reconocido.")

    try:
        # Validación extra: Si cambia el nombre y es admin, comprobar unicidad
        if cliente_update.nombre_empresa and db_cliente_actual.rol in ["admin", "root"]:
            existente_nombre = crud.get_cliente_by_nombre(db, nombre_empresa=cliente_update.nombre_empresa)
            if existente_nombre and existente_nombre.cliente_id != cliente_id and existente_nombre.rol in ["admin", "root"]:
                raise HTTPException(status_code=400, detail=f"Ya existe un administrador con el nombre '{cliente_update.nombre_empresa}'.")

        db_cliente = crud.update_cliente(db=db, cliente_id=cliente_id, cliente_update=cliente_update)
        if db_cliente is None:
            raise HTTPException(status_code=404, detail="Cliente no encontrado")
        return db_cliente
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

@router.delete("/clientes/{cliente_id}", status_code=status.HTTP_204_NO_CONTENT, summary="Borrar Cliente Definitivamente")
def borrar_cliente(
    cliente_id: int, 
    db: Session = Depends(get_db),
    current_user: models.Cliente = Depends(auth.require_admin)
):
    """Elimina permanentemente un cliente (Uso restringido)."""
    if current_user.cliente_id == cliente_id:
        raise HTTPException(status_code=403, detail="No puedes eliminar permanentemente tu propia cuenta mientras estás en sesión.")
    
    db_cliente = crud.get_cliente(db, cliente_id=cliente_id)
    if db_cliente and db_cliente.rol == "root":
        raise HTTPException(status_code=403, detail="La cuenta 'root' es vital para el sistema y no puede ser eliminada físicamente.")

    exito = crud.delete_cliente(db, cliente_id=cliente_id)
    if not exito:
        raise HTTPException(status_code=404, detail="Cliente no encontrado")
    return None
