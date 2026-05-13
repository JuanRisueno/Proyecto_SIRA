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
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]