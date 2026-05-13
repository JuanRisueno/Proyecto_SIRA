"""
=============================================================================
            Definición de Modelos ORM (SQLAlchemy)
=============================================================================

Propósito:
Este archivo define la capa de abstracción de la base de datos (ORM) para
el proyecto SIRA.

Cada clase definida aquí (ej. 'Cliente', 'Parcela', 'Sensor') representa
una tabla en la base de datos PostgreSQL.

Este archivo actúa como la "fuente única de la verdad" (Single Source of Truth)
para la lógica de datos de Python:
1.  Traduce las clases de Python a tablas SQL.
2.  Define las columnas, tipos de datos y restricciones (PK, FK, UK, NOT NULL).
3.  Establece las relaciones lógicas (ej. 'parcelas', 'invernaderos') que
    permiten a la API navegar por los datos usando objetos, en lugar de
    escribir consultas JOIN complejas.

Este archivo depende de 'database.py' (que gestiona la conexión) y es
utilizado por 'schemas.py' (Pydantic) y toda la lógica de la API (CRUD).
"""

# Importamos los tipos de datos y funciones necesarios de SQLAlchemy.
from sqlalchemy import (Column, Integer, String, Date, ForeignKey, DateTime, CHAR, Numeric, Index, Boolean)
from sqlalchemy.orm import relationship
from sqlalchemy.sql import func
from decimal import Decimal # Importación explícita para Type Hinting correcto

# --- Definición de la Base Declarativa ---
# Importamos la 'Base' centralizada desde nuestro archivo database.py
from .database import Base

# =============================================================================
# --- Definición de Modelos de Base de Datos (ORM) ---
# =============================================================================

# 1. CLIENTE
class Cliente(Base):
    """
    Representa la tabla 'cliente' en la base de datos.
    Almacena información sobre los clientes (empresas) de la plataforma.
    """
    __tablename__ = 'cliente'

    cliente_id: int = Column(Integer, primary_key=True)
    nombre_empresa: str = Column(String(150), nullable=False)
    cif: str = Column(CHAR(9), unique=True, nullable=False) # CHAR(9) fijo para DNI/CIF
    email_admin: str = Column(String(150), nullable=False)
    telefono: str = Column(String(13), nullable=False) # String para permitir prefijos (+34)
    persona_contacto: str = Column(String(100), nullable=False) 
    hash_contrasena: str = Column(String(255), nullable=False) # Hash bcrypt (nunca texto plano)
    rol: str = Column(String(20), nullable=False, default='cliente') # root, admin, cliente
    activa: bool = Column(Boolean, default=True) # Soft Delete
    debe_cambiar_pw: bool = Column(Boolean, default=True) # Obligar a cambio en primer login
    session_id: str = Column(String(255), nullable=True) # Identificador de sesión activa para control de concurrencia
    ultima_actividad = Column(DateTime(timezone=True), nullable=True) # Huella digital para estado "En Línea"

    # --- Relaciones (ORM) ---
    parcelas = relationship("Parcela", back_populates="cliente")

# 2. LOCALIDAD
class Localidad(Base):
    """
    Tabla auxiliar 'localidad' (CP, Municipio, Provincia).
    """
    __tablename__ = 'localidad'
    
    # PK es String porque un CP puede empezar por '0' (Ej: 08001)
    codigo_postal: str = Column(CHAR(5), primary_key=True)
    municipio: str = Column(String(100), nullable=False)
    provincia: str = Column(String(100), nullable=False)

    # --- Relaciones ---
    parcelas = relationship("Parcela", back_populates="localidad")

    @property
    def num_parcelas(self):
        """Devuelve el número de parcelas asociadas a esta localidad."""
        return len(self.parcelas)


# 3. PARCELA
class Parcela(Base):
    """
    Terreno físico asociado a un cliente y una localidad.
    """
    __tablename__ = 'parcela'
    
    parcela_id: int = Column(Integer, primary_key=True)
    nombre: str = Column(String(100), nullable=True) # Alias personalizado
    direccion: str = Column(String(150), nullable=False)
    ref_catastral: str = Column(CHAR(14), unique=True, nullable=False)
    activa: bool = Column(Boolean, default=True) # Soft Delete
    
    # --- Claves Foráneas (FK) ---
    cliente_id: int = Column(Integer, ForeignKey('cliente.cliente_id'), nullable=False) 
    codigo_postal: str = Column(CHAR(5), ForeignKey('localidad.codigo_postal'), nullable=False)
    
    # --- Relaciones ---
    cliente = relationship("Cliente", back_populates="parcelas")
    localidad = relationship("Localidad", back_populates="parcelas")
    invernaderos = relationship("Invernadero", back_populates="parcela")
    
# 4. INVERNADERO
class Invernadero(Base):
    """
    Estructura dentro de una parcela donde se cultiva.
    """
    __tablename__ = 'invernadero'
    
    invernadero_id: int = Column(Integer, primary_key=True)
    nombre: str = Column(String(50), nullable=False)
    fecha_plantacion: Date = Column(Date, nullable=True) # Null = En construcción/vacío
    # Usamos Numeric/Decimal para precisión exacta en medidas físicas
    largo_m: Decimal = Column(Numeric(8,2), nullable=False)
    ancho_m: Decimal = Column(Numeric(8,2), nullable=False)
    activa: bool = Column(Boolean, default=True) # Soft Delete
        
    # --- Claves Foráneas ---
    parcela_id: int = Column(Integer, ForeignKey('parcela.parcela_id'), nullable=False)
    cultivo_id: int = Column(Integer, ForeignKey('cultivo.cultivo_id'), nullable=True) # Null = Barbecho

    # --- Relaciones ---
    parcela = relationship("Parcela", back_populates="invernaderos")
    cultivo = relationship("Cultivo", back_populates="invernaderos")
    sensores = relationship("Sensor", back_populates="invernadero")
    actuadores = relationship("Actuador", back_populates="invernadero")
    recomendaciones_riego = relationship("RecomendacionRiego", back_populates="invernadero")
    
# 5. CULTIVO
class Cultivo(Base):
    """
    Catálogo de cultivos (Tomate, Lechuga...).
    """
    __tablename__ = 'cultivo'
    
    cultivo_id: int = Column(Integer, primary_key=True)
    nombre_cultivo: str = Column(String(100), unique=True, nullable=False)
    
    # [NUEVO V5.0] Gestión de Cultivos Personalizados
    cliente_id: int = Column(Integer, ForeignKey('cliente.cliente_id'), nullable=True) # NULL = Sistema
    activa: bool = Column(Boolean, default=True) # Soft Delete
    
    # --- Relaciones ---
    cliente = relationship("Cliente")
    invernaderos = relationship("Invernadero", back_populates="cultivo")
    parametros_optimos = relationship("ParametrosOptimos", back_populates="cultivo")

# 6. PARÁMETROS ÓPTIMOS
class ParametrosOptimos(Base):
    """
    Rangos ideales (Temp, Humedad) para un cultivo en una fase concreta.
    """
    __tablename__ = 'parametros_optimos'
    
    parametro_id: int = Column(Integer, primary_key=True)
    fase_crecimiento: str = Column(String(50), nullable=False) # Ej: "Germinación"
    # Rangos definidos con precisión Decimal
    temp_optima_min: Decimal = Column(Numeric(5,2), nullable=False)
    temp_optima_max: Decimal = Column(Numeric(5,2), nullable=False)
    humedad_optima_min: Decimal = Column(Numeric(5,2), nullable=False)
    humedad_optima_max: Decimal = Column(Numeric(5,2), nullable=False)
    necesidad_hidrica: Decimal = Column(Numeric(8,2), nullable=False)
    ph_ideal: Decimal = Column(Numeric(3,1), nullable=True) # [NUEVO] Robust MVP
    
    # --- Clave Foránea ---
    cultivo_id: int = Column(Integer, ForeignKey('cultivo.cultivo_id'), nullable=False)
    
    # --- Relaciones ---
    cultivo = relationship("Cultivo", back_populates="parametros_optimos")
    
# 7. RECOMENDACION_RIEGO
class RecomendacionRiego(Base):
    """
    Registro histórico de riegos sugeridos por el sistema inteligente.
    """
    __tablename__ = 'recomendacion_riego'
    
    recomendacion_id: int = Column(Integer, primary_key=True)
    fecha_recomendacion: DateTime = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    cantidad_ml: Decimal = Column(Numeric(8,2), nullable=False)
    duracion_min: int = Column(Integer, nullable=False)
    razon_logica: str = Column(String(255), nullable=False) # Ej: "Humedad < 30%"
    
    # --- Clave Foránea ---
    invernadero_id: int = Column(Integer, ForeignKey('invernadero.invernadero_id'), nullable=False)
    
    # --- Relaciones ---
    invernadero = relationship("Invernadero", back_populates="recomendaciones_riego")

# 8. TIPO_SENSOR
class TipoSensor(Base):
    """
    Catálogo de tipos de sensores (Ej: Temp Aire, Humedad Suelo).
    """
    __tablename__ = 'tipo_sensor'
    
    tipo_sensor_id: int = Column(Integer, primary_key=True)
    nombre_tipo: str = Column(String(100), unique=True, nullable=False)
    unidad_medida: str = Column(String(20), nullable=False) # Ej: 'ºC', '%'
    
    # --- Relaciones ---
    sensores = relationship("Sensor", back_populates="tipo_sensor")

# 9. SENSOR
class Sensor(Base):
    """
    Dispositivo físico (Hardware) instalado o en inventario.
    """
    __tablename__ = 'sensor'
    
    sensor_id: int = Column(Integer, primary_key=True)
    ubicacion_sensor: str = Column(String(100), nullable=True)
    estado_sensor: str = Column(String(20), nullable=True) # "Activo", "Mantenimiento"
    
    # --- Claves Foráneas ---
    invernadero_id: int = Column(Integer, ForeignKey('invernadero.invernadero_id'), nullable=True) # Null = Inventario
    tipo_sensor_id: int = Column(Integer, ForeignKey('tipo_sensor.tipo_sensor_id'), nullable=False)
    
    # --- Relaciones ---
    invernadero = relationship("Invernadero", back_populates="sensores")
    tipo_sensor = relationship("TipoSensor", back_populates="sensores")
    mediciones = relationship("Medicion", back_populates="sensor")

# 10. MEDICION
class Medicion(Base):
    """
    Dato atómico capturado por un sensor (Serie Temporal).
    """
    __tablename__ = 'medicion'
    
    medicion_id: int = Column(Integer, primary_key=True)
    fecha_hora: DateTime = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    valor: Decimal = Column(Numeric(10,2), nullable=False)
    
    # --- Clave Foránea ---
    sensor_id: int = Column(Integer, ForeignKey('sensor.sensor_id'), nullable=False)
    
    # --- Relaciones ---
    sensor = relationship("Sensor", back_populates="mediciones")

# 11. TIPO_ACTUADOR
class TipoActuador(Base):
    """
    Catálogo de tipos de actuadores (Ej: Electroválvula, Motor Ventana).
    """
    __tablename__ = 'tipo_actuador'
    
    tipo_actuador_id: int = Column(Integer, primary_key=True)
    nombre_tipo: str = Column(String(100), unique=True, nullable=False)
    
    # --- Relaciones ---
    actuadores = relationship("Actuador", back_populates="tipo_actuador")

# 12. ACTUADOR
class Actuador(Base):
    """
    Dispositivo físico que ejecuta acciones.
    """
    __tablename__ = 'actuador'
    
    actuador_id: int = Column(Integer, primary_key=True)
    ubicacion_actuador: str = Column(String(100), nullable=True)
    estado_actuador: str = Column(String(20), nullable=True)
    
    # --- Claves Foráneas ---
    invernadero_id: int = Column(Integer, ForeignKey('invernadero.invernadero_id'), nullable=True) # Null = Inventario
    tipo_actuador_id: int = Column(Integer, ForeignKey('tipo_actuador.tipo_actuador_id'), nullable=False)
    
    # --- Relaciones ---
    invernadero = relationship("Invernadero", back_populates="actuadores")
    tipo_actuador = relationship("TipoActuador", back_populates="actuadores")
    acciones_actuador = relationship("AccionActuador", back_populates="actuador") 
    
# 13. ACCION_ACTUADOR
class AccionActuador(Base):
    """
    Historial de operaciones (Log de auditoría de actuadores).
    """
    __tablename__ = 'accion_actuador'
    
    accion_id: int = Column(Integer, primary_key=True)
    fecha_hora: DateTime = Column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    accion_detalle: str = Column(String(100), nullable=False) # Ej: "APERTURA 100%"
    
    # --- Clave Foránea ---
    actuador_id: int = Column(Integer, ForeignKey('actuador.actuador_id'), nullable=False)
    
    # --- Relaciones ---
    actuador = relationship("Actuador", back_populates="acciones_actuador") 

# =============================================================================
# --- Índices de Rendimiento (Coincidencia exacta con 10-schema.sql) ---
# =============================================================================
# Índices B-Tree estándar para claves foráneas (aceleran JOINs)
Index('idx_parcela_cliente', Parcela.cliente_id)
Index('idx_parcela_codpostal', Parcela.codigo_postal)
Index('idx_invernadero_parcela', Invernadero.parcela_id)
Index('idx_invernadero_cultivo', Invernadero.cultivo_id)
Index('idx_parametros_cultivo', ParametrosOptimos.cultivo_id)
Index('idx_sensor_invernadero', Sensor.invernadero_id)
Index('idx_sensor_tipo', Sensor.tipo_sensor_id)
Index('idx_actuador_invernadero', Actuador.invernadero_id)
Index('idx_actuador_tipo', Actuador.tipo_actuador_id)
Index('idx_accion_actuador', AccionActuador.actuador_id)
Index('idx_recomendacion_invernadero', RecomendacionRiego.invernadero_id)

# Índices Críticos para IoT (Series Temporales)
Index('idx_medicion_sensor', Medicion.sensor_id)
# Índice descendente para optimizar "Dame la última temperatura"
Index('idx_medicion_fecha', Medicion.fecha_hora.desc())
