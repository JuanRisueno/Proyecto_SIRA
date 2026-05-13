from sqlalchemy.orm import Session
from .. import models, schemas

# --- TIPOS DE SENSOR ---
def get_tipos_sensor(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.TipoSensor).offset(skip).limit(limit).all()

def create_tipo_sensor(db: Session, tipo: schemas.TipoSensorCreate):
    db_tipo = models.TipoSensor(**tipo.dict())
    db.add(db_tipo)
    db.commit()
    db.refresh(db_tipo)
    return db_tipo

# --- SENSORES ---
def get_sensores(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Sensor).offset(skip).limit(limit).all()

def create_sensor(db: Session, sensor: schemas.SensorCreate):
    db_sensor = models.Sensor(**sensor.dict())
    db.add(db_sensor)
    db.commit()
    db.refresh(db_sensor)
    return db_sensor

# --- TIPOS DE ACTUADOR ---
def get_tipos_actuador(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.TipoActuador).offset(skip).limit(limit).all()

def create_tipo_actuador(db: Session, tipo: schemas.TipoActuadorCreate):
    db_tipo = models.TipoActuador(**tipo.dict())
    db.add(db_tipo)
    db.commit()
    db.refresh(db_tipo)
    return db_tipo

# --- ACTUADORES ---
def get_actuadores(db: Session, skip: int = 0, limit: int = 100):
    return db.query(models.Actuador).offset(skip).limit(limit).all()

def create_actuador(db: Session, actuador: schemas.ActuadorCreate):
    db_actuador = models.Actuador(**actuador.dict())
    db.add(db_actuador)
    db.commit()
    db.refresh(db_actuador)
    return db_actuador