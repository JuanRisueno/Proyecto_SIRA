# Guía de Copias de Seguridad y Restauración - Proyecto SIRA

Como la base de datos de SIRA funciona dentro de un contenedor Docker (`sira_db`), los comandos para hacer copias de seguridad (backups) deben ejecutarse a través de Docker. En esta guía explico los pasos necesarios para guardar y recuperar la información del proyecto.

---

## 1. Cómo hacer una copia de seguridad (Backup)

Para guardar todos los datos actuales de la base de datos en un archivo en nuestro ordenador, debemos ejecutar el siguiente comando desde la terminal:

```bash
docker exec -t sira_db pg_dump -U juanrisueno -d sira_db > backup_sira.sql
```

*Nota: Asegúrate de que el nombre de usuario (`-U`) y el de la base de datos (`-d`) coinciden con los que hayas configurado en tu archivo `.env`.*

Este comando creará un archivo llamado `backup_sira.sql` que contiene toda la estructura de las tablas y los datos que hayamos introducido (clientes, invernaderos, lecturas, etc.).

---

## 2. Cómo restaurar los datos

Si por algún motivo perdemos la información o queremos mover el proyecto a otro servidor, podemos restaurar el archivo de backup siguiendo estos pasos:

1. Levantar los contenedores de Docker:
   ```bash
   docker compose up -d
   ```
2. Inyectar los datos del archivo de backup en el contenedor de la base de datos:
   ```bash
   cat backup_sira.sql | docker exec -i sira_db psql -U juanrisueno -d sira_db
   ```

---

## 3. Gestión de los Volúmenes de Docker

Docker guarda los datos en un "volumen" persistente para que no se borren al apagar el ordenador. Si lo que queremos es limpiar la base de datos por completo para empezar de cero, podemos usar este comando:

```bash
# Borra los contenedores y el volumen de datos
docker compose down -v
```

Después, al ejecutar `docker compose up -d`, la base de datos se volverá a crear vacía y se ejecutará el script de inicialización para crear las tablas de nuevo.

---
**Guía de Mantenimiento de Base de Datos - SIRA**  
*Versión 1.0 Final - Abril 2026*
