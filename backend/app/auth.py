# backend/app/auth.py

"""
=============================================================================
            Módulo de Autenticación y Seguridad (auth.py)
=============================================================================
Propósito:
Gestionar la seguridad de la API mediante JWT y hashing de contraseñas Bcrypt.
"""

import os
from datetime import datetime, timedelta, timezone
from typing import Optional, Annotated

import bcrypt
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer
from jose import jwt, JWTError
from sqlalchemy.orm import Session

# --- IMPORTACIONES LOCALES ---
from .database import get_db
from .models import Cliente
from . import schemas

# --- CONFIGURACIÓN ---
# Prioridad: Variable de entorno > Valor por defecto seguro
SECRET_KEY = os.getenv("JWT_SECRET_KEY", "SIRA_SECRET_KEY_SUPER_SECRETA_PARA_DESARROLLO")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 720 # 12 horas (El timeout real por inactividad de 30m se controla en DB)

# OAuth2PasswordBearer: El estándar para extraer el token del Header Authorization
# El tokenUrl DEBE coincidir con la ruta definida en el router de JWT
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="api/auth/token")
oauth2_scheme_optional = OAuth2PasswordBearer(tokenUrl="api/auth/token", auto_error=False)


# ==========================================
# 1. SEGURIDAD CRIPTOGRÁFICA (Bcrypt)
# ==========================================

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verifica una contraseña contra su hash."""
    try:
        return bcrypt.checkpw(plain_password.encode('utf-8'), hashed_password.encode('utf-8'))
    except Exception:
        return False

import re
def validate_password_complexity(password: str, rol: str = "cliente") -> bool:
    """
    Verifica que la contraseña cumpla con los requisitos de SIRA:
    - Mínimo 8 caracteres para clientes, 10 para Root/Admin
    - Al menos un número
    - Al menos una minúscula
    - Al menos una mayúscula
    - Al menos un símbolo (!@#$%^&*...)
    """
    min_len = 10 if rol in ["root", "admin"] else 8
    
    if len(password) < min_len: return False
    if not re.search(r"[a-z]", password): return False
    if not re.search(r"[A-Z]", password): return False
    if not re.search(r"\d", password): return False
    if not re.search(r"[!@#$%^&*(),.?\":{}|<>]", password): return False
    return True

def get_password_hash(password: str) -> str:
    """Genera un hash seguro a partir de una contraseña."""
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')


# ==========================================
# 2. GESTIÓN DE TOKENS (JWT)
# ==========================================

def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    """Crea un token JWT firmado."""
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.now(timezone.utc) + expires_delta
    else:
        expire = datetime.now(timezone.utc) + timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt


# ==========================================
# 3. LÓGICA DE AUTENTICACIÓN (LOGIN)
# ==========================================

def authenticate_user(db: Session, username: str, password: str):
    """
    Valida las credenciales (CIF como username) y devuelve el usuario si es correcto y está activo.
    """
    # Buscamos al cliente por su CIF
    user = db.query(Cliente).filter(Cliente.cif == username).first()
    
    if not user:
        return False
        
    # [V15.0] Bloqueo de seguridad: Evitar acceso a cuentas desactivadas (Soft Deleted)
    if not user.activa:
        return False
    
    # Verificación del hash de la contraseña
    if not verify_password(password, user.hash_contrasena):
        return False
    
    return user


# ==========================================
# 4. DEPENDENCIAS DE ACCESO (PORTERO)
# ==========================================

async def get_current_user(token: Annotated[str, Depends(oauth2_scheme)], db: Session = Depends(get_db)):
    """Protege rutas requiriendo un token válido y comprobando la exclusividad de sesión."""
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Credenciales no válidas o token expirado",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    session_invalidated_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="SESSION_INVALIDATED",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    session_timeout_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="SESSION_TIMEOUT",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    # [V15.0] Excepción explícita para cuentas desactivadas
    inactive_user_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Usuario inactivo o suspendido",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        cif_usuario: str = payload.get("sub")
        token_sid: str = payload.get("sid")
        
        if cif_usuario is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception
 
    user = db.query(Cliente).filter(Cliente.cif == cif_usuario).first()
    if user is None:
        raise credentials_exception
        
    # [V15.0] Bloqueo de seguridad: Rechazar peticiones de cuentas desactivadas
    if not user.activa:
        raise inactive_user_exception
    
    # CONTROL DE CONCURRENCIA (Iron Fortress)
    # Comparamos el SID del token con el guardado en la base de datos.
    if not token_sid or token_sid != user.session_id:
        raise session_invalidated_exception
    
    # SLIDING WINDOW TIMEOUT (30 Minutos de Inactividad)
    # Comprobamos si la última actividad fue hace más de 30 minutos
    from datetime import datetime, timezone, timedelta
    if user.ultima_actividad:
        ahora = datetime.now(timezone.utc)
        ultima_act = user.ultima_actividad
        if ultima_act.tzinfo is None:
            ultima_act = ultima_act.replace(tzinfo=timezone.utc)
        
        if (ahora - ultima_act) > timedelta(minutes=30):
            # Sesión expirada por inactividad
            user.session_id = None
            db.commit()
            raise session_timeout_exception

    # MONITOR DE ACTIVIDAD (Iron Fortress)
    # Actualizamos la huella digital del usuario en cada interacción (renovando el timeout)
    from sqlalchemy.sql import func
    user.ultima_actividad = func.now()
    db.commit()
        
    return user

async def get_current_user_optional(token: Optional[str] = Depends(oauth2_scheme_optional), db: Session = Depends(get_db)):
    """Permite el paso aunque no haya token, pero identifica al usuario si existe."""
    if not token:
        return None
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
        cif_usuario: str = payload.get("sub")
        if cif_usuario is None:
            return None
    except JWTError:
        return None

    return db.query(Cliente).filter(Cliente.cif == cif_usuario).first()


# ==========================================
# 5. CONTROL DE ROLES
# ==========================================

def require_admin(current_user: Cliente = Depends(get_current_user)):
    """Solo permite el paso a administradores o root."""
    if current_user.rol not in ["admin", "root"]:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Operación no permitida. Requiere permisos de administrador."
        )
    return current_user

def require_root(current_user: Cliente = Depends(get_current_user)):
    """Solo permite el paso al superusuario root."""
    if current_user.rol != "root":
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Operación no permitida. Requiere permisos de superusuario (root)."
        )
    return current_user