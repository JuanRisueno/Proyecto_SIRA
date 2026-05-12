# Justificación Técnica: Uso de Nginx como Proxy Inverso

Para el desarrollo del proyecto SIRA, se ha decidido incluir un servidor **Nginx** por delante de la API de FastAPI. Aunque FastAPI ya utiliza internamente Uvicorn para servir la aplicación, existen varias razones técnicas por las que, como administrador de sistemas (ASIR), se considera que esta arquitectura es la más adecuada.

## 1. Servidor de Aplicación vs Servidor Web

*   **Uvicorn**: Es un servidor ASGI muy eficiente para ejecutar el código Python asíncrono, pero su función principal es la lógica de la aplicación. No está diseñado para gestionar de forma segura y eficiente miles de conexiones directas desde Internet.
*   **Nginx**: Es un servidor web maduro y muy robusto. Al colocarlo delante, actúa como una primera barrera. Nginx se encarga de recibir todas las peticiones, filtrar las que sean incorrectas y pasarle a Uvicorn solo el tráfico limpio. Esto descarga de trabajo al backend y mejora la seguridad.

## 2. Ventajas para la Infraestructura de SIRA

1.  **Seguridad y Aislamiento de Puertos**: Gracias a Nginx y Docker, se pueden mantener los puertos de la base de datos (5432) y de la API (8000) cerrados al exterior. Solo Nginx expone el puerto estándar HTTP (80). Esto reduce la superficie de ataque del servidor.
2.  **Gestión de Certificados SSL (HTTPS)**: Es mucho más sencillo y eficiente configurar certificados de seguridad (como Let's Encrypt) en Nginx que hacerlo directamente en el código Python. Nginx puede encargarse de cifrar y descifrar el tráfico, liberando recursos del backend.
3.  **Servicio de Archivos Estáticos**: Si en el futuro el proyecto crece y necesita servir imágenes o archivos pesados, Nginx puede hacerlo de forma nativa sin pasar por la API, lo que mejora drásticamente la velocidad de respuesta.

## Conclusión

El uso de un proxy inverso no solo es una buena práctica profesional en el despliegue de aplicaciones web modernas, sino que también permite demostrar los conocimientos de redes y servidores adquiridos durante el ciclo de ASIR, garantizando un entorno más estable y seguro para la defensa del proyecto.
