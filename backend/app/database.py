"""
=============================================================================
            Archivo de Configuración Central de la Base de Datos
=============================================================================

Propósito:
Este archivo es el ÚNICO responsable de configurar y establecer la conexión
con la base de datos PostgreSQL del proyecto SIRA.

Separa la lógica de "cómo conectar" (database.py) de la lógica de "qué tablas
existen" (models.py), lo cual es una práctica estándar de ASIR.

Aquí definimos 3 elementos clave que usará el resto de la aplicación:
1. El 'engine' (motor) de SQLAlchemy.
2. La 'SessionLocal' (fábrica de sesiones) para interactuar con la BBDD.
3. La 'Base' declarativa de la cual heredarán todos nuestros modelos ORM.
"""

# --- Importaciones Necesarias ---
import os # Para poder leer variables de entorno (el .env)
import sys # Para detener el programa si falta configuración crítica
from sqlalchemy import create_engine
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker

# --- 1. URL DE CONEXIÓN (Seguridad - Leído del .env) ---
# Leemos la variable de entorno 'DATABASE_URL' que Docker Compose nos inyecta.
# Esto evita escribir contraseñas en el código (Práctica 12-Factor App).
SQLALCHEMY_DATABASE_URL = os.getenv("DATABASE_URL")

# [MEJORA v1.5] Validación de Seguridad
# Si la variable no existe, detenemos el programa con un mensaje claro
# en lugar de dejar que explote más adelante con un error extraño.
if not SQLALCHEMY_DATABASE_URL:
    print("❌ ERROR CRÍTICO: No se encontró la variable de entorno 'DATABASE_URL'.")
    print("   Asegúrate de lanzar esto con Docker Compose o definirla en tu .env")
    sys.exit(1)


# --- 2. EL MOTOR (Engine) ---
# 'create_engine' es el punto de entrada principal a la BBDD.
# Es el "enchufe" que gestiona el "pool" de conexiones (las "tuberías") a PostgreSQL.
#
# [MEJORA v1.5] pool_pre_ping=True
# Esto es vital para Docker. Antes de usar una conexión, SQLAlchemy le hace un "ping".
# Si la BBDD se reinició y la conexión es vieja, la descarta y crea una nueva.
# Evita errores de "connection closed" en producción.
engine = create_engine(
    SQLALCHEMY_DATABASE_URL, 
    pool_pre_ping=True
)


# --- 3. LA FÁBRICA DE SESIONES (SessionLocal) ---
# 'sessionmaker' NO crea una sesión, sino una "fábrica" que produce sesiones.
# Cada vez que un endpoint de la API necesite hablar con la BBDD, pedirá
# una sesión nueva a esta fábrica (SessionLocal).
#
# Parámetros de Integridad (GBD):
#   autocommit=False: La BBDD no guardará nada hasta que hagamos explícitamente 'db.commit()'.
#                     Esto garantiza transacciones atómicas (todo o nada).
#   autoflush=False:  No enviar datos a la BBDD a mitad de una transacción automáticamente.
#   bind=engine:      Le dice a esta fábrica que use el motor que creamos arriba.
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)


# --- 4. LA BASE DECLARATIVA (El Lienzo ORM) ---
# Creamos la clase 'Base' de la cual heredarán TODOS nuestros modelos
# en 'models.py' (Cliente, Parcela, Invernadero, etc.).
# Así es como SQLAlchemy sabe qué clases de Python mapear a qué tablas de SQL.
Base = declarative_base()

# =============================================================================
# 5. DEPENDENCIA (Dependency Injection)
# =============================================================================
def get_db():
    """
    Función generadora que crea una sesión de BBDD para una petición
    y la cierra automáticamente cuando la petición termina.
    
    Se usa en los endpoints así: db: Session = Depends(get_db)
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()