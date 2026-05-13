from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List, Optional
from .. import crud, schemas
from ..database import SessionLocal
from ..utils import geo_logic  # Importamos nuestra nueva utilidad

router = APIRouter(
    prefix="/api/v1",
    tags=["Geografía y Localidades"]
)

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

@router.post("/localidades/", response_model=schemas.Localidad, status_code=status.HTTP_201_CREATED, summary="Crear Localidad")
def crear_localidad(localidad: schemas.LocalidadCreate, db: Session = Depends(get_db)):
    db_localidad = crud.get_localidad(db, codigo_postal=localidad.codigo_postal)
    if db_localidad:
        raise HTTPException(status_code=400, detail="Este Código Postal ya existe.")
    return crud.create_localidad(db=db, localidad=localidad)

@router.get("/localidades/{codigo_postal}", response_model=schemas.Localidad, summary="Leer Localidad")
def leer_localidad(codigo_postal: str, db: Session = Depends(get_db)):
    db_localidad = crud.get_localidad(db, codigo_postal=codigo_postal)
    if db_localidad is None:
        raise HTTPException(status_code=404, detail="Localidad no encontrada")
    return db_localidad

@router.put("/localidades/{codigo_postal}", response_model=schemas.Localidad, summary="Actualizar Localidad")
def actualizar_localidad(
    codigo_postal: str, 
    localidad_update: schemas.LocalidadUpdate, 
    db: Session = Depends(get_db)
):
    """Actualiza los datos de municipio o provincia de un CP."""
    db_localidad = crud.update_localidad(db=db, codigo_postal=codigo_postal, localidad_update=localidad_update)
    if db_localidad is None:
        raise HTTPException(status_code=404, detail="Localidad no encontrada")
    return db_localidad

@router.get("/localidades/", response_model=List[schemas.Localidad], summary="Listar Localidades")
def listar_localidades(
    skip: int = 0, 
    limit: int = 1000, 
    q: Optional[str] = None,
    db: Session = Depends(get_db)
):
    """Lista municipios con soporte para búsqueda (ilike + unaccent)."""
    return crud.get_localidades(db, skip=skip, limit=limit, q=q)

@router.get("/geo/check-cp/{cp}", summary="Validar CP (Local + Externo)")
def validar_cp_inteligente(cp: str, db: Session = Depends(get_db)):
    """
    Busca un CP en BBDD local. Si no está, consulta la API de Zippopotam.
    Utiliza la utilidad modular geo_logic.
    """
    if len(cp) != 5 or not cp.isdigit():
        raise HTTPException(status_code=400, detail="CP inválido.")

    # 1. Búsqueda local
    db_loc = crud.get_localidad(db, codigo_postal=cp)
    if db_loc:
        return {
            "codigo_postal": db_loc.codigo_postal,
            "municipio": db_loc.municipio,
            "provincia": geo_logic.obtener_provincia_por_cp(cp, db_loc.provincia),
            "origen": "local"
        }

    # 2. Búsqueda externa (Delegada a utilidad)
    externo = geo_logic.consultar_zippopotam(cp)
    if externo:
        return externo

    raise HTTPException(status_code=404, detail="CP no encontrado.")

@router.get("/geo/search-municipio/{nombre}", summary="Buscar CPs por Municipio (Híbrido)")
def buscar_municipio_inteligente(nombre: str, db: Session = Depends(get_db)):
    """
    Búsqueda híbrida: busca en BBDD local y complementa con OpenStreetMap
    para ofrecer una cobertura total de España.
    """
    if len(nombre) < 3:
        raise HTTPException(status_code=400, detail="Mínimo 3 caracteres.")

    # 1. Resultados locales
    db_locs = crud.get_localidades(db, q=nombre, limit=10)
    resultados = [
        {
            "codigo_postal": loc.codigo_postal,
            "municipio": loc.municipio,
            "provincia": geo_logic.obtener_provincia_por_cp(loc.codigo_postal, loc.provincia),
            "origen": "local"
        } for loc in db_locs
    ]

    # 2. Si no hay suficientes locales, buscamos fuera (ampliamos a 20 resultados externos)
    if len(resultados) < 10:
        externos = geo_logic.consultar_municipio_externo(nombre)
        cps_locales = {r["codigo_postal"] for r in resultados}
        for ext in externos:
            if ext["codigo_postal"] not in cps_locales:
                resultados.append(ext)

    if not resultados:
        raise HTTPException(status_code=404, detail="No se encontraron coincidencias en toda España.")

    # 3. Ordenación inteligente: Priorizar los que EMPIEZAN por el nombre buscado
    nombre_buscado = nombre.lower()
    resultados.sort(key=lambda x: (
        0 if x["municipio"].lower().startswith(nombre_buscado) else 1,
        x["municipio"]
    ))

    return resultados[:20] # Devolvemos un top 20 de calidad

