import os
import json
import random
import re
from datetime import time as dt_time, timedelta, datetime
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session
from typing import List, Optional
from pydantic import BaseModel as PydanticBaseModel
from .. import crud, models, schemas
from ..database import get_db
from ..logic import control_brain

router = APIRouter(
    prefix="/api/v1/iot",
    tags=["Telemetría e IoT"]
)

# --- SENSORES ---
@router.get("/sensores/invernadero/{invernadero_id}", response_model=List[schemas.Sensor])
def listar_sensores_invernadero(invernadero_id: int, db: Session = Depends(get_db)):
    return db.query(models.Sensor).filter(models.Sensor.invernadero_id == invernadero_id).all()

# --- MEDICIONES ---
@router.get("/mediciones/sensor/{sensor_id}", response_model=List[schemas.Medicion])
def listar_mediciones_sensor(sensor_id: int, limit: int = 20, db: Session = Depends(get_db)):
    return db.query(models.Medicion)\
             .filter(models.Medicion.sensor_id == sensor_id)\
             .order_by(models.Medicion.fecha_hora.desc())\
             .limit(limit).all()

@router.post("/mediciones/", response_model=schemas.Medicion, status_code=status.HTTP_201_CREATED)
def crear_medicion(medicion: schemas.MedicionCreate, db: Session = Depends(get_db)):
    from ..crud import crud_operaciones
    return crud_operaciones.create_medicion(db=db, medicion=medicion)

# --- [ NUEVOS ENDPOINTS DE SIMULACIÓN Y CONTROL ] ---

def map_sensor_type(nombre_tipo: str) -> str:
    nombre = nombre_tipo.lower()
    if 'temp' in nombre: return 'temperatura'
    if 'luz' in nombre or 'rad' in nombre or 'sol' in nombre: return 'luz'
    if 'suelo' in nombre: return 'humedad_suelo'
    if 'vient' in nombre or 'aire' in nombre: return 'viento'
    if 'lluv' in nombre or 'agua' in nombre: return 'lluvia'
    return 'temperatura' # fallback

def get_ubicacion_invernadero(inv) -> dict:
    """Devuelve la ubicación real del invernadero desde la DB (parcela → localidad)."""
    # Zona horaria española: UTC+2 en verano (mar-oct), UTC+1 en invierno
    mes_actual = datetime.now().month
    tz = "UTC+2" if 3 <= mes_actual <= 10 else "UTC+1"

    try:
        localidad = inv.parcela.localidad
        municipio = localidad.municipio
        provincia = localidad.provincia
        return {"nombre": f"{municipio}, {provincia}, España", "tz": tz}
    except Exception:
        # Fallback si hay algún problema de relación
        return {"nombre": "España", "tz": tz}

@router.post("/simular/{invernadero_id}/{escenario}", status_code=status.HTTP_200_OK)
def simular_escenario(invernadero_id: int, escenario: str, db: Session = Depends(get_db)):
    """Inyecta un preset climático y ejecuta el cerebro lógico."""
    # Construir ruta absoluta basada en la ubicación de este script
    base_dir = os.path.dirname(os.path.abspath(__file__))
    ruta_presets = os.path.join(base_dir, "..", "logic", "presets_clima.json")
    
    try:
        with open(ruta_presets, "r", encoding="utf-8") as f:
            presets = json.load(f)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error leyendo presets_clima.json en ruta: {ruta_presets}")

    escenarios_disponibles = list(presets.keys())
    
    escenario_original = escenario
    if escenario == "random":
        escenario = random.choice(escenarios_disponibles)
    elif escenario not in escenarios_disponibles:
        raise HTTPException(status_code=400, detail=f"Escenario inválido. Disponibles: {escenarios_disponibles}")
        
    preset = presets[escenario]
    lecturas_preset = preset["sensores"]

    # 1. Verificar invernadero y traer sensores
    inv = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
    if not inv:
         raise HTTPException(status_code=404, detail="Invernadero no encontrado")
         
    sensores = db.query(models.Sensor).filter(models.Sensor.invernadero_id == invernadero_id).all()
    
    # Auto-Aprovisionamiento SIRA: Si está plantado, asumimos "Sensórica Activa"
    if not sensores and inv.cultivo_id is not None:
         control_brain.provisionar_iot_defecto(db, invernadero_id)
         sensores = db.query(models.Sensor).filter(models.Sensor.invernadero_id == invernadero_id).all()
         
    if not sensores:
         raise HTTPException(status_code=400, detail="Invernadero sin iniciar (Barbecho). No hay telemetría.")

    # 2. Inyectar mediciones en BBDD
    from ..crud import crud_operaciones
    lecturas_invernadero = {}
    
    for sensor in sensores:
        tipo_str = db.query(models.TipoSensor).filter(models.TipoSensor.tipo_sensor_id == sensor.tipo_sensor_id).first().nombre_tipo
        clave_preset = map_sensor_type(tipo_str)
        # Añadir algo de ruido para realismo
        valor_base = lecturas_preset.get(clave_preset, 20.0)
        ruido = random.uniform(-0.5, 0.5)
        valor_final = round(valor_base + ruido, 2)
        
        crud_operaciones.create_medicion(db, schemas.MedicionCreate(sensor_id=sensor.sensor_id, valor=valor_final))
        lecturas_invernadero[clave_preset] = valor_final

    # 3. Parsear hora virtual y ALEATORIZARLA (v8.2)
    momento_str = preset.get("momento", "")
    hora_virtual = None
    match = re.search(r"\((\d{2}):(\d{2})\)", momento_str)
    if match:
        hora_h = int(match.group(1))
        hora_m = int(match.group(2))
        # Añadir un offset aleatorio de +/- 45 minutos para que no sea siempre igual
        offset = random.randint(-45, 45)
        base_dt = datetime.combine(datetime.today(), dt_time(hora_h, hora_m))
        randomized_dt = base_dt + timedelta(minutes=offset)
        hora_virtual = randomized_dt.time()
        momento_str = f"{preset['momento']} -> Real: {hora_virtual.strftime('%H:%M')}"
    
    # Obtener ubicación real desde la DB (invernadero → parcela → localidad)
    ubicacion = get_ubicacion_invernadero(inv)

    cliente_id = inv.parcela.cliente_id if inv.parcela else 1
    info_jornada = control_brain.esta_en_jornada_laboral(cliente_id, hora_test=hora_virtual)

    # 4. Ejecutar el Control Brain
    resultado_control = control_brain.ejecutar_ciclo_control(db, invernadero_id, lecturas_invernadero, info_jornada)
    
    # 5. Generar diagnóstico inicial con contexto del preset (Convertimos sensores a dict para compatibilidad)
    sensores_dict = [{"tipo": k, "valor": v} for k, v in lecturas_invernadero.items()]
    try:
        contexto_extra = {
            "ubicacion": ubicacion["nombre"],
            "tz": ubicacion["tz"],
            "hora": hora_virtual.strftime("%H:%M") if hora_virtual else "--:--"
        }
        diagnostico_inicial = control_brain.generar_resumen_humano(sensores_dict, [], info_jornada, escenario_id=escenario_original, contexto_extra=contexto_extra)
    except Exception as e:
        diagnostico_inicial = f"⚠️ Error en SIRA Brain (Simulación): {str(e)}"

    # El contexto completo se devuelve en la respuesta HTTP.
    # No se persiste en disco — la BD ya tiene los valores inyectados.

    return {
        "escenario_aplicado": preset["nombre"],
        "momento": momento_str,
        "hora_virtual": hora_virtual.strftime("%H:%M") if hora_virtual else None,
        "ubicacion_simulada": f"{ubicacion['nombre']} ({ubicacion['tz']})",
        "descripcion": preset.get("descripcion", ""),
        "sensores_inyectados": len(sensores),
        "lecturas": lecturas_invernadero,
        "control_brain": resultado_control,
        "diagnostico_humano": diagnostico_inicial,
        "jornada_activa": info_jornada[0],
        "jornada_configurada": info_jornada[1]
    }

@router.get("/estado/{invernadero_id}")
def obtener_estado_iot(invernadero_id: int, hora_virtual: str = None, escenario: str = None, ubicacion: str = None, tz: str = None, db: Session = Depends(get_db)):
    """Devuelve el estado actual de los sensores y actuadores de un invernadero."""
    
    # Nota: no hay persistencia en disco — si no vienen parámetros,
    # se usan valores por defecto (hora real, sin ubicación de simulación).

    inv = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
    if not inv:
         raise HTTPException(status_code=404, detail="Invernadero no encontrado")
         
    sensores = db.query(models.Sensor).filter(models.Sensor.invernadero_id == invernadero_id).all()
    
    # Auto-Aprovisionamiento en Vista: Si está plantado, asumimos "Sensórica Activa"
    if not sensores and inv.cultivo_id is not None:
         control_brain.provisionar_iot_defecto(db, invernadero_id)
         sensores = db.query(models.Sensor).filter(models.Sensor.invernadero_id == invernadero_id).all()
         
    actuadores = db.query(models.Actuador).filter(models.Actuador.invernadero_id == invernadero_id).all()
    
    res_sensores = []
    for s in sensores:
        ultima_med = db.query(models.Medicion).filter(models.Medicion.sensor_id == s.sensor_id).order_by(models.Medicion.fecha_hora.desc()).first()
        res_sensores.append({
            "sensor_id": s.sensor_id,
            "ubicacion": s.ubicacion_sensor,
            "tipo": s.tipo_sensor.nombre_tipo,
            "unidad": s.tipo_sensor.unidad_medida,
            "valor": ultima_med.valor if ultima_med else None
        })

    from ..crud import crud_operaciones
    res_actuadores = []
    for a in actuadores:
        en_cortesia = control_brain.evaluar_estado_cortesia(db, a.actuador_id)
        res_actuadores.append({
            "actuador_id": a.actuador_id,
            "ubicacion": a.ubicacion_actuador,
            "tipo": a.tipo_actuador.nombre_tipo,
            "estado": a.estado_actuador,
            "modo_manual": en_cortesia
        })
        
    # Verificar si está en jornada laboral (con soporte para hora virtual)
    inv = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
    cliente_id = inv.parcela.cliente_id if inv else 1
    
    hora_v = None
    if hora_virtual:
        try:
            # Soporta "HH:MM"
            partes = hora_virtual.split(":")
            from datetime import time as dt_time
            hora_v = dt_time(int(partes[0]), int(partes[1]))
        except:
            pass

    info_jornada = control_brain.esta_en_jornada_laboral(cliente_id, hora_test=hora_v)
    en_jornada, jornada_configurada = info_jornada

    nombre_cultivo_str = inv.cultivo.nombre_cultivo if inv and inv.cultivo else "Barbecho (Sin Plantar)"
    
    parametros_optimos = None
    if inv and inv.cultivo:
        parametros = db.query(models.ParametrosOptimos).filter(models.ParametrosOptimos.cultivo_id == inv.cultivo_id).first()
        if parametros:
            parametros_optimos = {
                "fase": parametros.fase_crecimiento,
                "temp_min": float(parametros.temp_optima_min),
                "temp_max": float(parametros.temp_optima_max),
                "hum_min": float(parametros.humedad_optima_min),
                "hum_max": float(parametros.humedad_optima_max),
                "agua": float(parametros.necesidad_hidrica),
                "ph": float(parametros.ph_ideal) if parametros.ph_ideal else None
            }
    
    # Diagnóstico detallado con contexto de escenario
    try:
        contexto_extra = None
        if ubicacion or tz or hora_virtual:
            contexto_extra = {
                "ubicacion": ubicacion or "Desconocida",
                "tz": tz or "---",
                "hora": hora_virtual or "--:--"
            }
        diagnostico_humano = control_brain.generar_resumen_humano(res_sensores, res_actuadores, info_jornada, escenario_id=escenario, contexto_extra=contexto_extra)
    except Exception as e:
        diagnostico_humano = f"⚠️ Error en SIRA Brain (Estado): {str(e)}"
    
    return {
        "sensores": res_sensores,
        "actuadores": res_actuadores,
        "jornada_activa": en_jornada,
        "jornada_configurada": jornada_configurada,
        "cultivo_nombre": nombre_cultivo_str,
        "parametros_optimos": parametros_optimos,
        "diagnostico_humano": diagnostico_humano,
        "contexto_simulacion": {
            "ubicacion": ubicacion or "Desconocida",
            "tz": tz or "---",
            "hora": hora_virtual or "--:--"
        }
    }

class OverrideRequest(PydanticBaseModel):
    actuador_id: int
    nuevo_estado: str
    duracion: Optional[str] = "2h" # '2h' o 'perm'

@router.post("/override/")
def control_manual(override: OverrideRequest, db: Session = Depends(get_db)):
    from ..crud import crud_operaciones
    # Guardar lógica de acción en log (El cerebro reacciona basándose en si empieza por MANUAL)
    prefix = "MANUAL_PERM" if override.duracion == "perm" else "MANUAL"
    is_reverting_to_auto = override.nuevo_estado.upper() == "AUTO"
    detalle = "AUTO: RESTABLECER CORTESÍA O SIMULADOR" if is_reverting_to_auto else f"{prefix}: {override.nuevo_estado}"
    
    # 1. Registrar la acción en el LOG (Crítico para que el Brain sepa si tiene permiso)
    accion = crud_operaciones.create_accion(db, schemas.AccionActuadorCreate(
        actuador_id=override.actuador_id,
        accion_detalle=detalle
    ))

    # 2. Si el usuario pide un estado específico (ON/OFF/%), actualizamos YA.
    if not is_reverting_to_auto:
         crud_operaciones.update_estado_actuador(db, override.actuador_id, override.nuevo_estado)
    else:
        # 3. Si el usuario pide VOLVER A AUTO, forzamos una ejecución del Brain para este actuador.
        # Así el cambio de estado es instantáneo y no hay que esperar a otro ciclo.
        act = db.query(models.Actuador).filter(models.Actuador.actuador_id == override.actuador_id).first()
        if act:
            inv_id = act.invernadero_id
            # Recuperar últimas lecturas de sensores
            sensores = db.query(models.Sensor).filter(models.Sensor.invernadero_id == inv_id).all()
            lecturas = {}
            for s in sensores:
                ult = db.query(models.Medicion).filter(models.Medicion.sensor_id == s.sensor_id).order_by(models.Medicion.fecha_hora.desc()).first()
                if ult:
                    lecturas[map_sensor_type(s.tipo_sensor.nombre_tipo)] = float(ult.valor)
            
            # El contexto de hora virtual no se persiste en disco.
            # Se usa la hora real del sistema para recalcular jornada.
            hora_v = None

            cliente_id = act.invernadero.parcela.cliente_id if act.invernadero and act.invernadero.parcela else 1
            info_j = control_brain.esta_en_jornada_laboral(cliente_id, hora_test=hora_v)
            
            # Ejecutar cerebro (Ahora sí, evaluar_estado_cortesia devolverá False gracias al log de arriba)
            decisiones = control_brain.ejecutar_ciclo_control(db, inv_id, lecturas, info_j)
            nuevo_estado_auto = decisiones.get(act.actuador_id)
            if nuevo_estado_auto:
                crud_operaciones.update_estado_actuador(db, act.actuador_id, nuevo_estado_auto)

    return {"status": "ok", "message": f"Orden {detalle} procesada."}
