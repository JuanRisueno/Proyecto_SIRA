# backend/app/crud/__init__.py
# Centralización de importaciones para facilitar el acceso desde los Routers

from .crud_clientes import (
    get_cliente, 
    get_cliente_by_cif, 
    get_cliente_by_nombre,
    get_clientes, 
    create_cliente,
    set_cliente_status,
    update_cliente,
    delete_cliente
)

from .crud_infraestructura import (
    get_localidad, get_localidades, create_localidad, update_localidad,
    get_parcela, get_parcelas, create_parcela, get_parcelas_por_cliente, get_parcelas_por_localidad, update_parcela, delete_parcela,
    get_invernadero, get_invernaderos, create_invernadero, get_invernaderos_por_cliente, update_invernadero, delete_invernadero,
    get_jerarquia_datos # <--- [NUEVO V11.0]
)

from .crud_cultivos import ( # <--- [NUEVO V11.0]
    get_cultivo, get_cultivos, create_cultivo, get_cultivo_by_nombre, 
    get_cultivo_completo, update_cultivo, set_cultivo_status
)

from .crud_dispositivos import (
    get_sensores, create_sensor,
    get_actuadores, create_actuador,
    get_tipos_sensor, create_tipo_sensor,
    get_tipos_actuador, create_tipo_actuador
)

from .crud_operaciones import (
    create_medicion, get_mediciones,
    create_accion,
    create_recomendacion
)
