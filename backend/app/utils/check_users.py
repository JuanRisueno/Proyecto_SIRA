from sqlalchemy import create_engine, text
import os

# Intento de obtener la URL de conexión
db_url = os.getenv("DATABASE_URL")

if not db_url:
    # Si no hay variable, probamos con la ruta de SQLite por si acaso existe el fichero sira.db
    if os.path.exists("sira.db"):
        db_url = "sqlite:///sira.db"
    else:
        print("No se encontró DATABASE_URL ni sira.db")
        exit(1)

print(f"Conectando a: {db_url}")
engine = create_engine(db_url)

try:
    with engine.connect() as conn:
        result = conn.execute(text("SELECT cliente_id, nombre_empresa, cif, rol FROM cliente;"))
        rows = result.fetchall()
        print(f"\n--- USUARIOS ENCONTRADOS ({len(rows)}) ---")
        for row in rows:
            print(f"ID: {row[0]} | Empresa: {row[1]} | CIF: {row[2]} | Rol: {row[3]}")
except Exception as e:
    print(f"Error al consultar la base de datos: {e}")
