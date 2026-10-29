# Guía de Copias de Seguridad y Restauración - Proyecto SIRA

Como la base de datos de SIRA funciona dentro de un contenedor Docker (`sira_db`), los comandos para hacer copias de seguridad (backups) deben ejecutarse a través de Docker. En esta guía explico los pasos necesarios para guardar y recuperar la información del proyecto.

---

## 1. Cómo hacer una copia de seguridad (Backup)

Para guardar todos los datos actuales de la base de datos en un archivo en nuestro ordenador, debemos ejecutar el siguiente comando desde la terminal:

```bash
# Leemos las credenciales del propio contenedor (POSTGRES_USER / POSTGRES_DB),
# así el comando sirve sin tocar nada aunque cambie el .env.
mkdir -p backups
docker exec sira_db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
    > "backups/backup_sira_$(date +%Y%m%d_%H%M).sql"
```

*Nota: la carpeta `backups/` está incluida en `.gitignore`, porque los volcados contienen datos reales y hashes de contraseñas que no deben subirse al repositorio.*

Este comando creará un archivo `backups/backup_sira_AAAAMMDD_HHMM.sql` que contiene toda la estructura de las tablas y los datos que hayamos introducido (clientes, invernaderos, lecturas, etc.).

---

## 2. Cómo restaurar los datos

Si por algún motivo perdemos la información o queremos mover el proyecto a otro servidor, podemos restaurar el archivo de backup siguiendo estos pasos:

1. Levantar los contenedores de Docker:
   ```bash
   docker compose up -d
   ```
2. Inyectar los datos del archivo de backup en el contenedor de la base de datos:
   ```bash
   docker exec -i sira_db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' \
       < backups/backup_sira_AAAAMMDD_HHMM.sql
   ```

---

## 3. Gestión de los Volúmenes de Docker

Docker guarda los datos en un "volumen" persistente para que no se borren al apagar el ordenador. Si lo que queremos es limpiar la base de datos por completo para empezar de cero, podemos usar este comando:

```bash
# Borra los contenedores Y los volúmenes (datos + historial de seguridad)
docker compose down -v
```

Después, al ejecutar `docker compose up -d`, PostgreSQL crea el volumen de nuevo y ejecuta automáticamente los scripts de `backend/database/` (`10-schema.sql` y `20-data.sql`). Es decir, **la base no queda vacía**: se recrea la estructura y se vuelven a insertar los datos de ejemplo, incluidas las cuentas semilla (`root` / `root1234`, `admin` / `admin1234`).

> ⚠️ **Aviso**: este comando destruye los datos introducidos y el historial de contraseñas. Si solo necesitas recuperar el acceso a una cuenta, **no lo uses**: emplea `scripts/reset_password_sira.sh` (ver [reset_password.md](../infraestructura/reset_password.md)).

---
**Guía de Mantenimiento de Base de Datos - SIRA**  
*Versión 1.0 Final - Abril 2026*
