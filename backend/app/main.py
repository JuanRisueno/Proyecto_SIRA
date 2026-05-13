"""
=============================================================================
            Archivo Principal de la API (main.py)
=============================================================================

Propósito:
Este archivo es el punto de entrada principal (entrypoint) para la API REST de SIRA.
Es el archivo que inicializa la aplicación y conecta las partes.

Responsabilidades Técnicas:
1.  Inicializar FastAPI.
2.  Gestionar la conexión a BBDD.
3.  Conectar los ROUTERS.
4.  ENDPOINT de verificación.
5.  GESTIÓN DE AUTENTICACIÓN (Login).
"""
# --- Importaciones de Framework y Utilidades ---
from datetime import timedelta
from fastapi import FastAPI, Depends, HTTPException, status
from fastapi.security import OAuth2PasswordRequestForm
from sqlalchemy.orm import Session
import bcrypt  # Necesario para verificar la contraseña hasheada

# --- Importaciones Locales ---
from . import models, crud, schemas, auth
from .database import engine, get_db
from .routers import clientes, localidades, infraestructura, cultivos, jwt, telemetria, configuracion, sistema

# --- 1. CREACIÓN DE TABLAS Y CONFIGURACIÓN ---
# Genera las tablas en PostgreSQL al arrancar si no existen.
models.Base.metadata.create_all(bind=engine)

# [NUEVO] Asegurar que la extensión 'unaccent' esté activa (PostgreSQL)
# Esto soluciona problemas de búsqueda en bases de datos ya inicializadas.
from sqlalchemy import text
from sqlalchemy.exc import ProgrammingError

try:
    with engine.connect() as conn:
        conn.execute(text("CREATE EXTENSION IF NOT EXISTS unaccent;"))
        conn.commit()
        print("✅ Extensión 'unaccent' verificada/activada.")
except Exception as e:
    # Si falla (ej: usando SQLite o sin permisos de superusuario), ignoramos para no tirar la API
    print(f"⚠️ Aviso: No se pudo activar 'unaccent' automáticamente: {e}")

# --- 2. INICIALIZACIÓN DE LA APP ---
app = FastAPI(
    title="SIRA API",
    description="Backend para el Sistema Integral de Riego Automático",
    version="1.0.0"
)

# --- 3. CONEXIÓN DE ROUTERS ---
# Sustitución del God Router por piezas MODULARES (V11.0)
app.include_router(clientes.router)
app.include_router(localidades.router)
app.include_router(infraestructura.router)
app.include_router(cultivos.router)
app.include_router(jwt.router)
app.include_router(telemetria.router)
app.include_router(configuracion.router)
app.include_router(sistema.router)


# --- 4. ENDPOINT DE VERIFICACIÓN ---
@app.get("/")
def read_root():
    """Endpoint para comprobar desde Postman que el servidor arranca."""
    return {"mensaje": "SIRA API v1.0: Sistema Operativo y Escuchando."}