import json
import os
from datetime import datetime, timedelta, time
from sqlalchemy.orm import Session
from .. import models, schemas
from ..crud import crud_operaciones

# Constantes Lógicas
PRIORIDAD_VIENTO_KMH = 45.0
TEMP_RESCATE_HELADA = 10.0
TEMP_PARADA_HELADA = 12.0
TEMP_VENTILACION = 30.0
HUMEDAD_SUELO_RIEGO_ON = 65.0
HUMEDAD_SUELO_RIEGO_OFF = 80.0
HUMEDAD_AIRE_EXTRACTOR = 90.0
RADIACION_LUCES_ON = 200.0
RADIACION_LUCES_OFF = 250.0
MINUTOS_CORTESIA = 120

def provisionar_iot_defecto(db: Session, invernadero_id: int):
    """Instala automáticamente los 5 sensores y 5 actuadores si el invernadero está plantado."""
    # 1. Definir los tipos base
    tipos_sensores = [
        {"nombre": "Temperatura", "unidad": "ºC"},
        {"nombre": "Lluvia", "unidad": "mm/h"},
        {"nombre": "Radiación Solar", "unidad": "W/m²"},
        {"nombre": "Humedad Suelo", "unidad": "%"},
        {"nombre": "Viento", "unidad": "km/h"}
    ]
    
    tipos_actuadores = [
        "Electroválvula Riego", "Motor Ventana", "Iluminación LED", 
        "Ventilador Extractor", "Calefacción"
    ]
    
    # 2. Asegurar que existan en el sistema y crear instancias para el invernadero
    for ts in tipos_sensores:
        db_tipo = db.query(models.TipoSensor).filter(models.TipoSensor.nombre_tipo == ts["nombre"]).first()
        if not db_tipo:
            db_tipo = models.TipoSensor(nombre_tipo=ts["nombre"], unidad_medida=ts["unidad"])
            db.add(db_tipo)
            db.commit()
            db.refresh(db_tipo)
        
        # Crear sensor si no existe
        existente = db.query(models.Sensor).filter(
            models.Sensor.invernadero_id == invernadero_id, 
            models.Sensor.tipo_sensor_id == db_tipo.tipo_sensor_id
        ).first()
        if not existente:
            db.add(models.Sensor(invernadero_id=invernadero_id, tipo_sensor_id=db_tipo.tipo_sensor_id, ubicacion_sensor="Sector Central", estado_sensor="ACTIVO"))
            
    for ta in tipos_actuadores:
        db_tipo = db.query(models.TipoActuador).filter(models.TipoActuador.nombre_tipo == ta).first()
        if not db_tipo:
            db_tipo = models.TipoActuador(nombre_tipo=ta)
            db.add(db_tipo)
            db.commit()
            db.refresh(db_tipo)
            
        # Crear actuador si no existe
        existente = db.query(models.Actuador).filter(
            models.Actuador.invernadero_id == invernadero_id, 
            models.Actuador.tipo_actuador_id == db_tipo.tipo_actuador_id
        ).first()
        if not existente:
            db.add(models.Actuador(invernadero_id=invernadero_id, tipo_actuador_id=db_tipo.tipo_actuador_id, ubicacion_actuador="Sector Central", estado_actuador="APAGADO"))
            
    db.commit()

def esta_en_jornada_laboral(cliente_id: int, hora_test: time = None) -> tuple[bool, bool]:
    """
    Verifica si una hora (u hora actual) está dentro de la jornada definida.
    Retorna: (está_en_jornada: bool, configurada: bool)
    """
    import os
    base_dir = os.path.dirname(os.path.abspath(__file__))
    ruta_json = os.path.join(base_dir, "..", "config_clientes", f"jornada_cliente_{cliente_id}.json")
    
    if not os.path.exists(ruta_json):
        # Si no hay configuración, NO hay jornada (y marcamos configurada=False)
        return False, False
    
    try:
        with open(ruta_json, "r", encoding="utf-8") as f:
            config = json.load(f)
            
        tramos = config.get("default", [])
        if not tramos:
            return False, True
            
        ahora = hora_test if hora_test else datetime.now().time()
        for tramo in tramos:
            inicio = datetime.strptime(tramo["inicio"], "%H:%M").time()
            fin = datetime.strptime(tramo["fin"], "%H:%M").time()
            if inicio <= ahora <= fin:
                return True, True
        return False, True
    except Exception:
        return False, False

def evaluar_estado_cortesia(db: Session, actuador_id: int) -> bool:
    """Retorna True si el actuador está bloqueado por intervención manual reciente."""
    # Obtenemos directamente la última acción sea cual sea
    ultima_accion = db.query(models.AccionActuador).filter(
        models.AccionActuador.actuador_id == actuador_id
    ).order_by(models.AccionActuador.fecha_hora.desc()).first()
    
    if not ultima_accion:
        return False
        
    if ultima_accion.accion_detalle.startswith("MANUAL_PERM"):
        return True # Bloqueo manual permanente concedido
        
    if not ultima_accion.accion_detalle.startswith("MANUAL"):
        return False # Fue devuelto a AUTO o está en control de Cerebro
        
    ahora = datetime.now(ultima_accion.fecha_hora.tzinfo) if ultima_accion.fecha_hora.tzinfo else datetime.now()
    tiempo_transcurrido = ahora - ultima_accion.fecha_hora
    return tiempo_transcurrido <= timedelta(minutes=MINUTOS_CORTESIA)

def ejecutar_ciclo_control(db: Session, invernadero_id: int, lecturas: dict, info_jornada: tuple = None):
    """
    Motor Neural de SIRA: Recibe las últimas lecturas de los sensores del invernadero
    y determina el estado óptimo de cada actuador respetando jerarquías de seguridad.
    lecturas format: {'temperatura': 25, 'lluvia': 0, 'viento': 10, ...}
    info_jornada: tuple (en_jornada: bool, jornada_configurada: bool). Si None, se calcula.
    """
    # Obtenemos los actuadores del invernadero para poder modificar su estado
    actuadores = db.query(models.Actuador).filter(models.Actuador.invernadero_id == invernadero_id).all()
    
    # Si no hay sensores o actuadores, no hay nada que controlar
    if not actuadores or not lecturas:
        return {"status": "ok", "message": "Faltan dispositivos para el control."}

    # Determinamos la jornada laboral usando el parámetro recibido o calculándola
    if info_jornada is not None:
        en_jornada, jornada_configurada = info_jornada
    else:
        inv = db.query(models.Invernadero).filter(models.Invernadero.invernadero_id == invernadero_id).first()
        cliente_id = inv.parcela.cliente_id if inv else 1
        en_jornada, jornada_configurada = esta_en_jornada_laboral(cliente_id)

    # Identificadores de actuadores (simplificado: basamos la acción en el nombre_tipo)
    # y control de cortesía. Mapeamos TipoActuador.nombre_tipo a su ID
    mapa_actuadores = {}
    for act in actuadores:
        nombre_tipo = db.query(models.TipoActuador).filter(models.TipoActuador.tipo_actuador_id == act.tipo_actuador_id).first().nombre_tipo.lower()
        mapa_actuadores[nombre_tipo] = act

    decisiones = {}

    # === ALGORITMOS DE DECISIÓN (SIRA JERARQUÍA) ===

    # 1. MOTOR VENTANA (Seguridad vs Clima)
    act_ventana = next((v for k, v in mapa_actuadores.items() if "ventana" in k), None)
    if act_ventana and not evaluar_estado_cortesia(db, act_ventana.actuador_id):
        viento = lecturas.get('viento', 0)
        lluvia = lecturas.get('lluvia', 0)
        temp = lecturas.get('temperatura', 20)
        
        # Prioridad Absoluta 1: Seguridad
        if viento > PRIORIDAD_VIENTO_KMH or lluvia > 0:
            decisiones[act_ventana.actuador_id] = "CERRADO"
        # Prioridad 3: Ventilación térmica
        elif temp > TEMP_VENTILACION:
            decisiones[act_ventana.actuador_id] = "ABIERTO 100%"
        else:
            decisiones[act_ventana.actuador_id] = "ENTREABIERTO 20%"

    # 2. ILUMINACIÓN LED (Dependiente de Jornada Configurada y Fotoperiodo)
    act_led = next((v for k, v in mapa_actuadores.items() if "luz" in k or "iluminación" in k or "led" in k), None)
    if act_led:
        # SI NO HAY JORNADA CONFIGURADA -> SIEMPRE APAGADO (A menos que manual)
        if not jornada_configurada:
             decisiones[act_led.actuador_id] = "APAGADO"
        elif not evaluar_estado_cortesia(db, act_led.actuador_id):
            luz_solar = lecturas.get('luz', 1000)
            if not en_jornada:
                decisiones[act_led.actuador_id] = "APAGADO"
            else:
                if luz_solar < RADIACION_LUCES_ON:
                    decisiones[act_led.actuador_id] = "ENCENDIDO"
                elif luz_solar > RADIACION_LUCES_OFF:
                    decisiones[act_led.actuador_id] = "APAGADO"

    # 3. ELECTROVÁLVULA RIEGO (Humedad Suelo)
    act_riego = next((v for k, v in mapa_actuadores.items() if "riego" in k or "valvula" in k or "válvula" in k), None)
    if act_riego and not evaluar_estado_cortesia(db, act_riego.actuador_id):
        hum_suelo = lecturas.get('humedad_suelo', 100)
        lluvia = lecturas.get('lluvia', 0)
        
        # Prioridad 1: Si llueve, riego bloqueado
        if lluvia > 0:
            decisiones[act_riego.actuador_id] = "APAGADO"
        # Prioridad 2: Lógica de humedad
        elif hum_suelo < HUMEDAD_SUELO_RIEGO_ON:
            decisiones[act_riego.actuador_id] = "ENCENDIDO"
        elif hum_suelo >= HUMEDAD_SUELO_RIEGO_OFF:
            decisiones[act_riego.actuador_id] = "APAGADO"

    # 4. CALEFACCIÓN (Protección de Heladas)
    act_calefaccion = next((v for k, v in mapa_actuadores.items() if "calefaccion" in k or "calefacción" in k), None)
    if act_calefaccion and not evaluar_estado_cortesia(db, act_calefaccion.actuador_id):
        temp = lecturas.get('temperatura', 20)
        if temp < TEMP_RESCATE_HELADA:
            decisiones[act_calefaccion.actuador_id] = "ENCENDIDO"
        elif temp >= TEMP_PARADA_HELADA:
            decisiones[act_calefaccion.actuador_id] = "APAGADO"

    # 5. VENTILADOR EXTRACTOR (Por Humedad o Exceso de Calor)
    act_extractor = next((v for k, v in mapa_actuadores.items() if "extractor" in k or "ventilador" in k), None)
    if act_extractor and not evaluar_estado_cortesia(db, act_extractor.actuador_id):
        temp = lecturas.get('temperatura', 20)
        hum_relativa = lecturas.get('humedad_relativa', 50)
        if hum_relativa > HUMEDAD_AIRE_EXTRACTOR or temp > 35.0:
             decisiones[act_extractor.actuador_id] = "ENCENDIDO 100%"
        else:
             decisiones[act_extractor.actuador_id] = "APAGADO"

    # === EJECUCIÓN Y REGISTRO ===
    # Solo registramos un cambio si el estado decidido es diferente al actual
    cambios = []
    for actuador in actuadores:
        nuevo_estado = decisiones.get(actuador.actuador_id)
        if nuevo_estado and actuador.estado_actuador != nuevo_estado:
            # Actualiza físicamente en BD
            crud_operaciones.update_estado_actuador(db, actuador.actuador_id, nuevo_estado)
            # Log de la acción (AUTOMÁTICA)
            crud_operaciones.create_accion(db, schemas.AccionActuadorCreate(
                actuador_id=actuador.actuador_id,
                accion_detalle=f"AUTO: {nuevo_estado}"
            ))
            cambios.append(f"{actuador.tipo_actuador.nombre_tipo} -> {nuevo_estado}")
            
    return {"status": "ok", "decisiones_ejecutadas": len(cambios), "detalles": cambios}

def generar_resumen_humano(sensores: list, actuadores: list, info_jornada: tuple[bool, bool], escenario_id: str = None, contexto_extra: dict = None) -> str:
    """
    Traduce los valores crudos a un diagnóstico natural comprensible.
    escenario_id: ID del preset activo (ej: 'helada') o 'random'.
    contexto_extra: dict con 'ubicacion', 'tz' y 'hora'.
    """
    en_jornada, jornada_configurada = info_jornada
    
    # Búsqueda segura con paréntesis para precedencia correcta
    temp_val = next((s['valor'] for s in sensores if ('temp' in s['tipo'].lower() and s['valor'] is not None)), None)
    hum_val = next((s['valor'] for s in sensores if ('suelo' in s['tipo'].lower() and s['valor'] is not None)), None)
    luz_val = next((s['valor'] for s in sensores if (('rad' in s['tipo'].lower() or 'luz' in s['tipo'].lower()) and s['valor'] is not None)), None)
    viento_val = next((s['valor'] for s in sensores if ('viento' in s['tipo'].lower() and s['valor'] is not None)), None)
    lluvia_val = next((s['valor'] for s in sensores if ('lluvia' in s['tipo'].lower() and s['valor'] is not None)), None)

    if temp_val is None:
        return "SIRA está analizando el entorno... Las sondas aún no han estabilizado las lecturas."

    resumen = []
    
    # 0. LÓGICA DE ESCENARIO ESPECÍFICO (Prefijos de Contexto)
    if escenario_id == 'ideal':
        resumen.append("🌟 SIRA analiza el Escenario Ideal: Condiciones de crecimiento perfecto forzadas.")
    elif escenario_id == 'tormenta':
        resumen.append("⛈️ Análisis de Escenario de Tormenta: Priorizando drenaje y cerramientos.")
    elif escenario_id == 'calor':
        resumen.append("🔥 Análisis de Escenario de Ola de Calor: Mitigando estrés térmico mediante ventilación.")
    elif escenario_id == 'helada':
        resumen.append("❄️ Análisis de Escenario de Helada: Manteniendo balance térmico por encima de nivel crítico.")
    elif escenario_id == 'nublado':
        resumen.append("☁️ Análisis de Escenario Nublado: Compensando la baja radiación con luz artificial.")
    elif escenario_id == 'sequia':
        resumen.append("🏜️ Análisis de Escenario de Sequía: Maximizando eficiencia de riego localizada.")
    elif escenario_id == 'random':
        resumen.append("🎲 MODO ANALISTA ACTIVO (Simulación Aleatoria): Reporte exhaustivo de subsistemas.")

    # 1. TEMPERATURA Y CLIMA
    if temp_val is not None:
        if temp_val < 5:
            resumen.append("⚠️ RIESGO DE HELADA. El sistema prioriza la calefacción para proteger el cultivo.")
        elif temp_val > 35:
            resumen.append("🔥 ESTRÉS TÉRMICO EXTREMO. Se recomienda ventilación máxima inmediata.")
        elif 18 <= temp_val <= 26:
            resumen.append("✅ Temperatura interior óptima para el desarrollo vegetativo.")
        elif escenario_id == 'random':
            resumen.append(f"🌡️ La temperatura se mantiene estable ({temp_val}ºC).")
    
    # 2. SUELO Y RIEGO
    if hum_val is not None:
        if hum_val < 35:
            resumen.append("💧 Déficit hídrico detectado en suelo; activando riego automático.")
        elif hum_val > 85:
            resumen.append("🌊 Suelo saturado; suspensión preventiva de aportes hídricos.")
        elif escenario_id == 'random':
            resumen.append(f"🌱 Humedad del suelo en niveles nominales ({hum_val}%).")

    # 3. SEGURIDAD VIENTO/LLUVIA
    if lluvia_val is not None:
        if lluvia_val > 0:
            resumen.append("🌧️ Precipitación registrada: Cerramientos y desagües en modo seguridad.")
        elif escenario_id == 'random':
            resumen.append("🌤️ Ausencia de precipitaciones detectada.")

    if viento_val is not None:
        if viento_val > 45:
            resumen.append("🌪️ Viento de riesgo: Protocolo de protección de infraestructuras activo.")
        elif escenario_id == 'random':
            resumen.append(f"💨 Velocidad del viento estabilizada ({viento_val} km/h).")

    # 4. ILUMINACIÓN Y JORNADA
    if not jornada_configurada:
        resumen.append("ℹ️ Iluminación asistida en pausa (falta configuración de jornada).")
    elif not en_jornada:
        resumen.append("🌙 Ciclo de descanso nocturno: Ahorro energético y luces en OFF.")
    elif luz_val is not None:
        if luz_val < 200:
            resumen.append(f"💡 Poca radiación ({luz_val:0.2f} W/m2): Luz artificial compensatoria activa.")
        elif escenario_id == 'random':
            resumen.append(f"☀️ Niveles de radiación solar adecuados ({luz_val:0.2f} W/m2).")

    if not resumen:
        resumen.append("📊 El sistema SIRA opera bajo parámetros nominales de estabilidad.")

    return " ".join(resumen)
