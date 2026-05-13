import sys
import os

# Añadir el path para importar modelos
sys.path.append(os.getcwd())

from backend.app.database import SessionLocal
from backend.app import models

db = SessionLocal()
try:
    print("--- Diagnóstico de Localidades y Parcelas ---")
    
    # 1. Todas las localidades
    locs = db.query(models.Localidad).all()
    print(f"Total localidades: {len(locs)}")
    bcn_cp = None
    for l in locs:
        print(f"- Loc: '{l.codigo_postal}' (len={len(l.codigo_postal)}), Municipio: {l.municipio}")
        if "Barcelona" in l.municipio:
            bcn_cp = l.codigo_postal
            
    if bcn_cp:
        print(f"\nBarcelona encontrada con CP: '{bcn_cp}'")
        
        # 2. Buscar parcelas con ese CP exacto
        parcs_exact = db.query(models.Parcela).filter(models.Parcela.codigo_postal == bcn_cp).all()
        print(f"Parcelas encontradas con CP exacto '{bcn_cp}': {len(parcs_exact)}")
        
        # 3. Buscar parcelas que contengan ese CP (por si hay espacios raros)
        parcs_like = db.query(models.Parcela).filter(models.Parcela.codigo_postal.like(f"%{bcn_cp.strip()}%")).all()
        print(f"Parcelas encontradas con CP LIKE '%{bcn_cp.strip()}%': {len(parcs_like)}")
        
        for p in parcs_like:
            print(f"  * ID: {p.parcela_id}, CP Parcela: '{p.codigo_postal}' (len={len(p.codigo_postal)}), Dir: {p.direccion}")
            
    else:
        print("\nNo se encontró ninguna localidad llamada 'Barcelona'")

finally:
    db.close()
