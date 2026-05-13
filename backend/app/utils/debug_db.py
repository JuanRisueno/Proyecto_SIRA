import sys
import os

# Añadir el path del proyecto para importar los modelos
sys.path.append(os.getcwd())

from backend.app.database import SessionLocal
from backend.app import models

db = SessionLocal()
try:
    print("--- LOCALIDADES ---")
    locs = db.query(models.Localidad).all()
    for l in locs:
        print(f"CP: '{l.codigo_postal}', Municipio: {l.municipio}")
        
    print("\n--- PARCELAS ---")
    parcs = db.query(models.Parcela).all()
    for p in parcs:
        print(f"ID: {p.parcela_id}, CP: '{p.codigo_postal}', Dir: {p.direccion}")
        
    # Verificar Barcelona específicamente
    bcn_cp = '08001'
    parcs_bcn = db.query(models.Parcela).filter(models.Parcela.codigo_postal == bcn_cp).all()
    print(f"\nParcelas encontradas para '{bcn_cp}': {len(parcs_bcn)}")
    for p in parcs_bcn:
        print(f" - {p.direccion}")

finally:
    db.close()
