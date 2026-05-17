# backend/app/routers/jwt.py

"""
=============================================================================
            Router de Autenticación y JWT (routers/jwt.py)
=============================================================================
Propósito:
Gestionar el ciclo de vida de la sesión del usuario (Registro y Login).
"""

from datetime import timedelta
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session

# --- Importaciones Locales ---
from ..database import get_db
from ..models import Cliente
from .. import schemas, auth, crud

router = APIRouter(prefix="/api/auth", tags=["Autenticación"])


# ==========================================
# 1. REGISTRO PÚBLICO
# ==========================================

@router.post("/register", response_model=schemas.ClienteRead, status_code=status.HTTP_201_CREATED)
def register_user(cliente: schemas.ClienteCreate, db: Session = Depends(get_db)):
    """
    Permite a un nuevo usuario registrarse en la plataforma.
    La contraseña se hashea automáticamente antes de guardarse.
    """
    # Verificar si el CIF ya existe
    user_existente = crud.get_cliente_by_cif(db, cif=cliente.cif)
    if user_existente:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST, 
            detail="El usuario con este CIF ya existe en el sistema."
        )

    # El hasheo ocurre dentro de crud.create_cliente (que actualizaremos a continuación)
    return crud.create_cliente(db=db, cliente=cliente, rol="cliente")


# ==========================================
# 2. LOGIN (OBTENCIÓN DE TOKEN)
# ==========================================

@router.post("/token", response_model=schemas.Token)
def login_for_access_token(
    form_data: Annotated[OAuth2PasswordRequestForm, Depends()],
    db: Session = Depends(get_db)
):
    """
    Endpoint estándar OAuth2 para obtener el token de acceso.
    Recibe 'username' (CIF) y 'password' en el cuerpo de la petición.
    """
    # Delegamos la validación en la función centralizada de auth.py
    user = auth.authenticate_user(db, form_data.username, form_data.password)
    
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Credenciales incorrectas (CIF o Contraseña inválidos)",
            headers={"WWW-Authenticate": "Bearer"},
        )
    
    # 2. CONTROL DE CONCURRENCIA (Iron Fortress)
    # Generamos un identificador de sesión único para invalidar sesiones previas
    import uuid
    from sqlalchemy.sql import func
    new_sid = str(uuid.uuid4())
    user.session_id = new_sid
    user.ultima_actividad = func.now()
    db.commit()

    # Generar el Token JWT con el payload necesario para el Frontend e incluyeno el SID
    access_token_expires = timedelta(minutes=auth.ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = auth.create_access_token(
        data={
            "sub": user.cif, 
            "rol": user.rol, 
            "id": user.cliente_id,
            "empresa": user.nombre_empresa,
            "sid": new_sid
        },
        expires_delta=access_token_expires
    )
    
    # 3. Comprobar si la contraseña ha caducado por tiempo o flag (Iron Fortress)
    from .. import security_history
    caducada = security_history.is_password_expired(user.cliente_id)
    debe_cambiar = user.debe_cambiar_pw or caducada

    return {
        "access_token": access_token, 
        "token_type": "bearer", 
        "debe_cambiar_pw": debe_cambiar
    }

# ==========================================
# 3. VERIFICAR SESIÓN (IRON FORTRESS)
# ==========================================

@router.get("/verify", status_code=status.HTTP_200_OK)
def verify_session(current_user: Cliente = Depends(auth.get_current_user)):
    """
    Endpoint ligero para verificar que el token y la sesión siguen activos.
    """
    return {"status": "valid", "rol": current_user.rol}

# ==========================================
# 4. LOGOUT (CIERRE DE SESIÓN)
# ==========================================

@router.post("/logout", status_code=status.HTTP_200_OK)
def logout(
    current_user: Cliente = Depends(auth.get_current_user),
    db: Session = Depends(get_db)
):
    """
    Invalida la sesión activa del usuario borrando su session_id.
    """
    current_user.session_id = None
    db.commit()
    return {"message": "Sesión cerrada correctamente"}