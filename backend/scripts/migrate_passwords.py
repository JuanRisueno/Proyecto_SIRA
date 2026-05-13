import sys
import os
import bcrypt
from sqlalchemy import create_engine, text
from sqlalchemy.orm import sessionmaker

# Configuración de la base de datos
DATABASE_URL = os.getenv("DATABASE_URL")

if not DATABASE_URL:
    print("❌ ERROR: DATABASE_URL no encontrada.")
    sys.exit(1)

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def migrate():
    print(f"🚀 Iniciando migración de seguridad...")
    print(f"🔗 Conectando a la base de datos...")
    db = SessionLocal()
    try:
        # Obtener todos los clientes
        print("🔍 Buscando usuarios en la tabla 'cliente'...")
        result = db.execute(text("SELECT cliente_id, hash_contrasena, cif FROM cliente"))
        clients = result.fetchall()
        print(f"👥 Encontrados {len(clients)} usuarios.")
        
        count = 0
        skipped = 0
        for client_id, password, cif in clients:
            # Los hashes de bcrypt empiezan por $2b$ o $2a$
            is_hashed = password and (password.startswith("$2b$") or password.startswith("$2a$"))
            
            if password and not is_hashed:
                print(f"🔄 Hasheando contraseña para: {cif}")
                # Generar el hash profesional
                hashed = bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
                
                # Actualizar el registro
                db.execute(
                    text("UPDATE cliente SET hash_contrasena = :hash WHERE cliente_id = :id"),
                    {"hash": hashed, "id": client_id}
                )
                count += 1
            else:
                skipped += 1
        
        db.commit()
        print(f"\n✅ Migración completada.")
        print(f"📊 Resumen: {count} actualizados, {skipped} omitidos.")
    except Exception as e:
        print(f"❌ Error crítico: {e}")
        db.rollback()
    finally:
        db.close()

if __name__ == "__main__":
    migrate()
