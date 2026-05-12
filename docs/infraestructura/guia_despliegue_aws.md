# Guía de Despliegue en AWS - Proyecto SIRA

Esta guía explica los pasos seguidos para desplegar el proyecto SIRA en la nube utilizando **Amazon Web Services (AWS)**. El objetivo es que el sistema sea accesible a través de Internet para la defensa del proyecto.

---

## 1. Configuración de la Instancia (EC2)

Para este proyecto, se ha utilizado el servicio **EC2** de Amazon para crear un servidor virtual.

### Elección de la máquina
*   **Instancia**: Se ha usado una `t2.micro` (disponible en la capa gratuita de AWS). 
*   **Sistema Operativo**: **Ubuntu Server 24.04 LTS**.
*   **Memoria Swap**: Como la instancia gratuita solo tiene 1 GB de RAM, se ha configurado un archivo Swap de 2GB para asegurar que la base de datos y la API no se queden sin memoria y el sistema no se caiga.

---

## 2. Configuración de Red (Security Groups)

Se han configurado las reglas del firewall de Amazon (Security Groups) para permitir los siguientes tráficos:

| Tipo | Puerto | Descripción |
| :--- | :--- | :--- |
| SSH | 22 | Para poder conectar una terminal al servidor y subir el código. |
| HTTP | 80 | Tráfico web para que los usuarios puedan entrar al dashboard. |
| Custom | 8085 | Puerto configurado en Nginx para el acceso a la aplicación. |

---

## 3. Instalación de Software en el Servidor

Una vez conectado al servidor por SSH, los pasos para preparar el entorno son:

1. **Actualizar el sistema**:
   ```bash
   sudo apt update && sudo apt upgrade -y
   ```
2. **Instalar Docker y Docker Compose**:
   ```bash
   sudo apt install -y docker.io docker-compose
   sudo usermod -aG docker $USER
   ```

---

## 4. Configuración de la Memoria Virtual (Swap)

Para evitar fallos por falta de memoria en la instancia gratuita, se han ejecutado estos comandos:

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 5. Despliegue de la Aplicación

Para poner en marcha el sistema, se clona el código del repositorio y se levantan los contenedores:

```bash
# Clonar el proyecto
git clone https://github.com/MiUsuario/SIRA_Project.git
cd SIRA_Project

# Configurar el archivo .env con las contraseñas
cp .env.example .env
nano .env

# Lanzar los contenedores con Docker Compose
docker-compose up -d --build
```

---

## 6. Acceso al Sistema

Una vez que los contenedores están arriba, se puede acceder a la aplicación escribiendo la dirección IP del servidor en el navegador:

`http://DIRECCION_IP_AWS:8085`

---
**Guía de Despliegue en la Nube - SIRA**  
*Versión 1.0 Final - 30 de Abril de 2026*
