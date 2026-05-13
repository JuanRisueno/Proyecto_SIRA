# Justificación Técnica: Uso de Nginx como Proxy Inverso

Para el desarrollo del proyecto SIRA, se ha decidido incluir un servidor **Nginx** por delante de la API de FastAPI. Aunque FastAPI ya utiliza internamente Uvicorn para servir la aplicación, existen varias razones técnicas por las que, como administrador de sistemas (ASIR), se considera que esta arquitectura es la más adecuada.

## 1. Servidor de Aplicación vs Servidor Web

*   **Uvicorn**: Es un servidor ASGI muy eficiente para ejecutar el código Python asíncrono, pero su función principal es la lógica de la aplicación. No está diseñado para gestionar de forma segura y eficiente miles de conexiones directas desde Internet.
*   **Nginx**: Es un servidor web maduro y muy robusto. Al colocarlo delante, actúa como una primera barrera. Nginx se encarga de recibir todas las peticiones, filtrar las que sean incorrectas y pasarle a Uvicorn solo el tráfico limpio. Esto descarga de trabajo al backend y mejora la seguridad.

## 2. Ventajas para la Infraestructura de SIRA

1.  **Seguridad y Aislamiento de Puertos**: Gracias a Nginx y Docker, se pueden mantener los puertos de la base de datos (5432) y de la API (8000) cerrados al exterior. Solo Nginx expone el puerto estándar HTTP (80). Esto reduce la superficie de ataque del servidor.
2.  **Gestión de Certificados SSL (HTTPS)**: Es mucho más sencillo y eficiente configurar certificados de seguridad (como Let's Encrypt) en Nginx que hacerlo directamente en el código Python. Nginx puede encargarse de cifrar y descifrar el tráfico, liberando recursos del backend.
3.  **Servicio de Archivos Estáticos**: Si en el futuro el proyecto crece y necesita servir imágenes o archivos pesados, Nginx puede hacerlo de forma nativa sin pasar por la API, lo que mejora drásticamente la velocidad de respuesta.
4.  **Enrutamiento Inteligente (Routing Unificado)**: Nginx permite unificar todo el proyecto SIRA bajo un mismo punto de entrada. Redirige dinámicamente el tráfico según la ruta: las peticiones a `/api`, `/docs`, `/redoc` y `/openapi.json` se envían al backend (FastAPI), mientras que el resto de tráfico (`/`) se envía al panel de control frontend (PHP/Apache).
5.  **Soporte Avanzado para APIs (WebSockets y Payloads Grandes)**: Se ha configurado Nginx específicamente para las necesidades de FastAPI, habilitando el protocolo `HTTP/1.1` y las cabeceras `Upgrade` para permitir conexiones WebSockets. Asimismo, se ha ampliado la directiva `client_max_body_size` a 50MB para prevenir errores 413 (Payload Too Large) al subir archivos grandes a la API.

## 3. Portabilidad y Abstracción de Puertos (Local vs AWS)

Una ventaja clave de esta arquitectura es la total abstracción de puertos gracias a la dupla Nginx + Docker. En el archivo `nginx.conf`, el proxy escucha estáticamente en el puerto **80** interno. Sin embargo, la exposición al exterior se gestiona de forma dinámica en el `docker-compose.yml` usando variables de entorno (`ports: - "${SIRA_PORT:-8085}:80"`).

Esto permite que:
*   **En entorno local**: Se utilice por defecto el puerto `8085` (evitando conflictos con otros servicios que el desarrollador pueda tener en el puerto 80).
*   **En entorno de producción (AWS)**: Simplemente configurando la variable `SIRA_PORT=80` en el archivo `.env` de producción, Docker mapea el tráfico público estándar al proxy sin necesidad de alterar en absoluto la configuración interna de Nginx ni el código de la aplicación.

## Conclusión

El uso de un proxy inverso no solo es una buena práctica profesional en el despliegue de aplicaciones web modernas, sino que también permite demostrar los conocimientos de redes y servidores adquiridos durante el ciclo de ASIR, garantizando un entorno más estable y seguro para la defensa del proyecto.
