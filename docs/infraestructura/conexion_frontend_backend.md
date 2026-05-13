# Arquitectura de Conexión: Frontend y Backend en SIRA

Este documento explica cómo se estructura y comunica el sistema, que cuenta con un Frontend desarrollado en PHP, HTML y CSS, un Backend desarrollado en Python utilizando FastAPI, y un orquestador de red basado en Nginx y Docker.

## 1. Visión General de la Arquitectura

El proyecto SIRA sigue un modelo de arquitectura **Cliente-Servidor (API REST)** con una clara separación de responsabilidades:
- **Backend (Python + FastAPI):** Actúa como el proveedor exclusivo de datos y de la lógica de negocio profunda. Es el único que interactúa directamente con la Base de Datos (PostgreSQL).
- **Frontend (PHP + HTML + CSS):** Actúa como el consumidor de la API. Se encarga de la presentación de los datos, la gestión de la sesión del usuario a nivel web, y el renderizado de la interfaz gráfica.

## 2. Orquestación y Redes (Docker & Nginx)

La verdadera magia de la conexión ocurre a nivel de infraestructura, gestionada por **Docker Compose**:
- **Red Interna Aislada (`sira-network`):** Todos los componentes (Frontend, Backend, Base de Datos, Nginx) operan de forma segura dentro de una misma red puente interna de Docker.
- **El Proxy Inverso (Nginx):** El punto único de entrada desde el exterior (el navegador del usuario) es el contenedor `sira_nginx` (normalmente mapeado al puerto `8085` o el definido en `.env`). Ni el Frontend ni el Backend exponen sus puertos directamente a internet.
- **Enrutamiento Inteligente:** Nginx lee la URL solicitada y actúa como un guardián de tráfico:
    - Si la URL empieza por `/api/` (o `/docs`), Nginx enruta la petición de forma transparente al contenedor `api` (FastAPI) en el puerto interno 8000.
    - Cualquier otra ruta (ej: `/`, `/view_sensores.php`) es redirigida al contenedor `frontend` (PHP/Apache) en el puerto interno 80.

## 3. El Backend: FastAPI (Python)

El backend está construido con **FastAPI**, un framework web moderno y rápido.
- **Endpoints REST:** Expone rutas específicas (ej: `/api/v1/usuarios`) que aceptan métodos HTTP estándar.
- **Respuesta Estándar:** Devuelve todos los resultados estrictamente en formato **JSON**.
- **Autenticación:** Gestiona la seguridad mediante tokens JWT (JSON Web Tokens), esperando que las solicitudes autorizadas incluyan el token en la cabecera `Authorization: Bearer`.

## 4. El Frontend: PHP como Intermediario

Aunque la vista final está en HTML/CSS, la comunicación real con el backend de FastAPI no se hace directamente desde el navegador, sino que utiliza **PHP como capa intermedia (Proxy/Gateway)**.

### El rol de cURL
En el código fuente (ej: `api_helper.php`), PHP utiliza la librería **cURL** (`curl_init()`) para comunicarse con la API de Python.

### Flujo de Comunicación Típico (Paso a Paso)

1. **Solicitud del Navegador:** El usuario accede a `http://dominio:8085/view_sensores.php`.
2. **Proxy Nginx (Entrada):** Nginx ve que no es una ruta `/api/`, así que manda la petición al contenedor `frontend`.
3. **Petición PHP (cURL):** El script PHP se ejecuta. Necesita los datos, así que abre una conexión cURL y hace una petición HTTP `GET` a `http://localhost:8085/api/sensores` (o mediante el nombre interno del contenedor Nginx).
4. **Proxy Nginx (Re-enrutamiento):** Nginx intercepta esta petición interna a `/api/` y la redirige al contenedor `api` (FastAPI) al puerto 8000.
5. **Procesamiento y Respuesta:** FastAPI consulta la base de datos (PostgreSQL), extrae los valores y devuelve un objeto JSON.
6. **Renderizado HTML:** PHP recibe el JSON, lo decodifica usando `json_decode()`, y genera dinámicamente el código HTML y CSS.
7. **Entrega al Usuario Final:** Finalmente, el servidor entrega la vista completa al navegador del usuario.

## 5. Ventajas de este Enfoque

- **Seguridad Perimetral:** Al usar Nginx como Proxy Inverso, ocultamos la topología real de la red. FastAPI y PostgreSQL son inaccesibles desde el exterior salvo por las rutas que Nginx permita.
- **Desacoplamiento:** Si en el futuro se quiere crear una aplicación móvil nativa, esta podrá conectarse directamente a las rutas `/api/` a través de Nginx sin pasar por PHP.
- **Rendimiento:** PHP entrega plantillas cacheadas rápidamente, mientras que FastAPI maneja todo el procesamiento asíncrono y los cálculos en segundo plano.
