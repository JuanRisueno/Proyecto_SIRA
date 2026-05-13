# backend/app/schemas.py

"""
=============================================================================
            Definición de Schemas (Pydantic V2) - Versión SIRA V11.4 FINAL
=============================================================================

Propósito:
Este archivo define los "Contratos de Datos" de la API. Mientras que 'models.py'
define cómo se guardan los datos en SQL, estos Schemas definen cómo se envían
y reciben esos datos a través de peticiones HTTP (JSON).

Características clave:
1.  Validación: Pydantic comprueba que los datos tengan el tipo correcto.
2.  Seguridad: Filtramos qué campos enviamos al cliente (ej: no enviamos contraseñas).
3.  Documentación: Define la estructura que verás en /docs (Swagger).
4.  Conversión: Convierte automáticamente tipos complejos (Decimal, Date) a JSON.

Versión 11.4: Arquitectura de jerarquía "Non-Recursive" para evitar bucles.
"""

from pydantic import BaseModel, Field, ConfigDict
from typing import Optional, List
from decimal import Decimal
from datetime import date, datetime

# =============================================================================
# 1. CLIENTE (Empresas / Usuarios del sistema)
# =============================================================================

class ClienteBase(BaseModel):
    """Atributos comunes para lectura y escritura."""
    nombre_empresa: str
    cif: str = Field(..., min_length=9, max_length=9, description="DNI/CIF único de 9 caracteres")
    email_admin: str
    telefono: str = Field(..., min_length=9, max_length=9, pattern=r"^\d{9}$", description="9 dígitos numéricos")
    persona_contacto: str

class ClienteCreate(ClienteBase):
    """Schema para el registro de nuevos clientes (Recibe password plana)."""
    password: str 
    rol: Optional[str] = "cliente" # Define el nivel de acceso inicial

class ClienteRead(ClienteBase):
    """Schema para la lectura segura de datos (No expone contraseñas)."""
    cliente_id: int 
    rol: str
    activa: bool
    debe_cambiar_pw: bool
    ultima_actividad: Optional[datetime] = None
    session_id: Optional[str] = Field(None, exclude=False) # Para saber si el usuario tiene una sesión activa
    model_config = ConfigDict(from_attributes=True) # Permite leer desde modelos ORM

class Cliente(ClienteRead):
    """Alias para compatibilidad con código existente."""
    pass

class ClienteUpdate(BaseModel):
    """Permite actualizaciones parciales de los datos del perfil."""
    nombre_empresa: Optional[str] = None
    email_admin: Optional[str] = None
    telefono: Optional[str] = None
    persona_contacto: Optional[str] = None
    cif: Optional[str] = Field(None, min_length=9, max_length=9)
    password: Optional[str] = None # Para cambios de contraseña específicos
    confirmar_cambio_cif: bool = False

# =============================================================================
# 2. LOCALIDAD (Gestión Geográfica)
# =============================================================================

class LocalidadBase(BaseModel):
    codigo_postal: str = Field(..., min_length=5, max_length=5)
    municipio: str
    provincia: str

class LocalidadCreate(LocalidadBase):
    """Necesario para el endpoint POST /localidades/"""
    pass

class Localidad(LocalidadBase):
    num_parcelas: int = 0 # Campo calculado dinámicamente
    model_config = ConfigDict(from_attributes=True)

class LocalidadUpdate(BaseModel):
    """Para actualizar nombres o provincias si hay correcciones."""
    municipio: Optional[str] = None
    provincia: Optional[str] = None

# =============================================================================
# 3. CULTIVO (Catálogo Botánico y Parámetros Técnicos)
# =============================================================================

class ParametrosNested(BaseModel):
    """Sub-esquema para definir rangos óptimos de T/H."""
    temp_optima_min: Decimal
    temp_optima_max: Decimal
    humedad_optima_min: Decimal
    humedad_optima_max: Decimal
    necesidad_hidrica: Decimal
    ph_ideal: Optional[Decimal] = None
    model_config = ConfigDict(from_attributes=True)

class CultivoBase(BaseModel):
    nombre_cultivo: str
    cliente_id: Optional[int] = None # NULL = Cultivo global del sistema
    activa: bool = True

class CultivoCreate(CultivoBase):
    """Permite crear el cultivo y sus pautas técnicas en una sola operación."""
    parametros: Optional[ParametrosNested] = None

class Cultivo(CultivoBase):
    cultivo_id: int
    nombre_cliente: Optional[str] = None # Para saber quién registró la variedad
    parametros: Optional[ParametrosNested] = None
    model_config = ConfigDict(from_attributes=True)

# =============================================================================
# [ESTRATEGIA V11.4]: Definimos ParcelaRead antes que Invernadero para romper el bucle.
# =============================================================================

# 4. PARCELA (Definición Base y Lectura Ligera)
class ParcelaBase(BaseModel):
    nombre: Optional[str] = Field(None, max_length=100)
    direccion: str
    ref_catastral: str = Field(..., min_length=14, max_length=14)

class ParcelaRead(ParcelaBase):
    """
    Versión LIGERA de Parcela. 
    Contiene todo sobre la finca PERO NO sus invernaderos.
    Esto rompe el bucle infinito cuando un invernadero quiere mencionar a su parcela.
    """
    parcela_id: int
    cliente_id: int
    codigo_postal: str
    cliente: Cliente 
    localidad: Localidad
    activa: bool = True
    model_config = ConfigDict(from_attributes=True)

# 5. INVERNADERO (Unidades de Producción)
class InvernaderoBase(BaseModel):
    nombre: str = Field(..., max_length=50)
    fecha_plantacion: Optional[date] = None # Puede estar vacío si no hay siembra
    largo_m: Decimal
    ancho_m: Decimal

class InvernaderoCreate(InvernaderoBase):
    parcela_id: int 
    cultivo_id: Optional[int] = None 

class Invernadero(InvernaderoBase):
    """Representación de un invernadero. Usa ParcelaRead para evitar recursión."""
    invernadero_id: int 
    parcela_id: int     
    cultivo_id: Optional[int] = None
    cultivo: Optional[Cultivo] = None
    parcela: ParcelaRead # <--- ¡CLAVE!: Usa la versión sin hijos.
    activa: bool = True
    model_config = ConfigDict(from_attributes=True)

class InvernaderoUpdate(BaseModel):
    """Schema para edición rápida y siembra rápida."""
    nombre: Optional[str] = Field(None, max_length=50)
    fecha_plantacion: Optional[date] = None
    largo_m: Optional[Decimal] = None
    ancho_m: Optional[Decimal] = None
    cultivo_id: Optional[int] = None
    activa: Optional[bool] = None

# 6. PARCELA COMPLETA (Para el Dashboard)
class Parcela(ParcelaRead):
    """
    Versión COMPLETA de Parcela.
    Incluye la lista de invernaderos. Se usa para las tarjetas de "Mis Parcelas".
    Al usar 'Invernadero' (que usa ParcelaRead), el bucle se detiene ahí.
    """
    invernaderos: List[Invernadero] = [] 
    model_config = ConfigDict(from_attributes=True)

class ParcelaCreate(ParcelaBase):
    cliente_id: int
    codigo_postal: str

class ParcelaUpdate(BaseModel):
    """Actualización de fincas."""
    nombre: Optional[str] = Field(None, max_length=100)
    direccion: Optional[str] = None
    cliente_id: Optional[int] = None
    ref_catastral: Optional[str] = Field(None, min_length=14, max_length=14)
    activa: Optional[bool] = None
    confirmar_cambio_ref: bool = False

# =============================================================================
# 7. CAPA IOT (Telemetría y Control de Sensores)
# =============================================================================

class ParametrosOptimosBase(BaseModel):
    fase_crecimiento: str
    temp_optima_min: Decimal
    temp_optima_max: Decimal
    humedad_optima_min: Decimal
    humedad_optima_max: Decimal
    necesidad_hidrica: Decimal
    ph_ideal: Optional[Decimal] = None
    model_config = ConfigDict(from_attributes=True)

class ParametrosOptimosCreate(ParametrosOptimosBase):
    cultivo_id: int 

class ParametrosOptimos(ParametrosOptimosBase):
    parametro_id: int 
    cultivo_id: int   
    cultivo: Cultivo
    model_config = ConfigDict(from_attributes=True)

class RecomendacionRiegoBase(BaseModel):
    fecha_recomendacion: Optional[datetime] = None
    cantidad_ml: Decimal
    duracion_min: int
    razon_logica: str = Field(..., max_length=255)

class RecomendacionRiegoCreate(RecomendacionRiegoBase):
    invernadero_id: int 

class RecomendacionRiego(RecomendacionRiegoBase):
    recomendacion_id: int 
    invernadero_id: int   
    invernadero: Invernadero
    model_config = ConfigDict(from_attributes=True)

class TipoActuadorBase(BaseModel):
    nombre_tipo: str = Field(..., max_length=100)

class TipoActuadorCreate(TipoActuadorBase):
    pass    

class TipoActuador(TipoActuadorBase):
    tipo_actuador_id: int 
    model_config = ConfigDict(from_attributes=True)

class TipoSensorBase(BaseModel):
    nombre_tipo: str = Field(..., max_length=100)
    unidad_medida: str = Field(..., max_length=20)
    
class TipoSensorCreate(TipoSensorBase):
    pass    

class TipoSensor(TipoSensorBase):
    tipo_sensor_id: int 
    model_config = ConfigDict(from_attributes=True)

class SensorBase(BaseModel):
    ubicacion_sensor: Optional[str] = Field(None, max_length=100)
    estado_sensor: Optional[str] = Field(None, max_length=20)

class SensorCreate(SensorBase):
    tipo_sensor_id: int
    invernadero_id: Optional[int] = None 

class Sensor(SensorBase):
    sensor_id: int 
    tipo_sensor_id: int
    invernadero_id: Optional[int] = None
    tipo_sensor: TipoSensor
    # [V11.2] Mantenemos relación opcional al invernadero
    invernadero: Optional[Invernadero] = None 
    model_config = ConfigDict(from_attributes=True)

class ActuadorBase(BaseModel):
    ubicacion_actuador: Optional[str] = Field(None, max_length=100)
    estado_actuador: Optional[str] = Field(None, max_length=20)

class ActuadorCreate(ActuadorBase):
    tipo_actuador_id: int 
    invernadero_id: Optional[int] = None 

class Actuador(ActuadorBase):
    actuador_id: int 
    tipo_actuador_id: int 
    invernadero_id: Optional[int] = None 
    tipo_actuador: TipoActuador
    invernadero: Optional[Invernadero] = None
    model_config = ConfigDict(from_attributes=True)

class MedicionBase(BaseModel):
    fecha_hora: Optional[datetime] = None
    valor: Decimal    

class MedicionCreate(MedicionBase):
    sensor_id: int

class Medicion(MedicionBase):
    medicion_id: int 
    sensor_id: int   
    model_config = ConfigDict(from_attributes=True)

class AccionActuadorBase(BaseModel):
    fecha_hora: Optional[datetime] = None
    accion_detalle: str = Field(..., max_length=100)

class AccionActuadorCreate(AccionActuadorBase):
    actuador_id: int

class AccionActuador(AccionActuadorBase):
    accion_id: int   
    actuador_id: int 
    model_config = ConfigDict(from_attributes=True)

# =============================================================================
# 8. SEGURIDAD (JWT e Identidad)
# =============================================================================

class Token(BaseModel):
    access_token: str
    token_type: str
    debe_cambiar_pw: bool = False

class TokenData(BaseModel):
    cif: Optional[str] = None
    sid: Optional[str] = None

# =============================================================================
# 9. JERARQUÍA DEL DASHBOARD (Motor de Navegación)
# =============================================================================

class InvernaderoJerarquia(BaseModel):
    """Optimizado para el árbol de jerarquía rápido."""
    invernadero_id: int
    nombre: str
    largo_m: Decimal
    ancho_m: Decimal
    cultivo: Optional[str] = None
    activa: bool = True

class ParcelaJerarquia(BaseModel):
    parcela_id: int
    nombre: Optional[str] = None
    direccion: str
    ref_catastral: str
    activa: bool = True
    num_invernaderos: int = 0
    invernaderos: List[InvernaderoJerarquia] = []

class LocalidadJerarquia(BaseModel):
    codigo_postal: str
    municipio: str
    provincia: str
    num_parcelas: int = 0
    num_invernaderos_total: int = 0
    parcelas: List[ParcelaJerarquia] = []

class JerarquiaCliente(BaseModel):
    """Schema maestro que consume el Dashboard en modo Jerarquía."""
    cliente_id: int
    nombre_empresa: str
    num_localidades: int = 0
    localidades: List[LocalidadJerarquia] = []
# =============================================================================
# 7. CONFIGURACIÓN IOT Y JORNADA (JSON STORAGE)
# =============================================================================

class TramoHorario(BaseModel):
    """Define un intervalo de tiempo para la jornada laboral."""
    inicio: str = Field(..., pattern=r"^\d{2}:\d{2}(:\d{2})?$", description="Formato HH:MM o HH:MM:SS")
    fin: str = Field(..., pattern=r"^\d{2}:\d{2}(:\d{2})?$", description="Formato HH:MM o HH:MM:SS")

class ConfigJornada(BaseModel):
    """Estructura completa de la jornada semanal de un invernadero."""
    es_laborable: bool = Field(True, description="Si es falso, se considera un almacén/sin jornada")
    heredar_de_global: bool = Field(False, description="Si es verdadero, utiliza la configuración del cliente")
    default: List[TramoHorario] = Field(default_factory=list, max_length=3)
    d0: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="0")
    d1: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="1")
    d2: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="2")
    d3: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="3")
    d4: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="4")
    d5: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="5")
    d6: Optional[List[TramoHorario]] = Field(None, max_length=3, alias="6")

# =============================================================================
# 10. CONFIGURACIÓN DEL SISTEMA (Global)
# =============================================================================

class ConfigSocial(BaseModel):
    """Enlaces a redes sociales y contacto oficial del sistema."""
    twitter: Optional[str] = Field("", description="URL de X (Twitter)")
    instagram: Optional[str] = Field("", description="URL de Instagram")
    facebook: Optional[str] = Field("", description="URL de Facebook")
    whatsapp: Optional[str] = Field("", description="URL o número de WhatsApp")
    email_soporte: Optional[str] = Field("sira@sira.es", description="Email de contacto")
