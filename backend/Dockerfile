# 1. Usar una imagen oficial de Python como base. 'slim' es una versión ligera.
FROM python:3.11-slim

# 2. Establecer el directorio de trabajo dentro del contenedor.
# A partir de aquí, todos los comandos se ejecutan en /app.
WORKDIR /app

# 3. Copiar el fichero de dependencias y luego instalarlas.
# Se hace antes del código para aprovechar la caché de Docker.
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# 4. Copiar TODO el código del proyecto.
# CORRECCIÓN IMPORTANTE: Usamos "COPY . ." en lugar de "COPY ./app ."
# Esto copia 'app/', 'database/', etc., manteniendo la estructura de carpetas
# necesaria para que Python reconozca el paquete "app".
COPY . .

# 5. El comando que se ejecutará para iniciar la API.
# Ahora sí funcionará "app.main:app" porque la carpeta 'app' existe dentro del contenedor.
#
# SIN --reload, y es a propósito:
#   Con --reload, uvicorn arranca DOS procesos: un padre que vigila los ficheros y
#   un hijo que sirve la API. Si el hijo muere al importar el código (por ejemplo,
#   porque no consigue conectar con PostgreSQL), el padre SIGUE VIVO. Resultado:
#   el contenedor aparece "Up", no hay nadie escuchando en el 8000 y el reinicio
#   automático de Docker nunca se dispara. El usuario solo ve un "no se puede
#   contactar con el servicio" sin ninguna pista.
#   Sin --reload, un fallo de arranque mata el contenedor, Docker lo reintenta y el
#   problema se ve de inmediato en `docker ps` (estado "Restarting") y en los logs.
#
#   Si estás desarrollando y quieres recarga automática, arráncalo aparte:
#     docker exec sira_api uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload
#   O cambia este CMD solo en tu copia local, nunca en la versión de entrega.
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]