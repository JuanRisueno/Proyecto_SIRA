# Restablecer Contraseñas en SIRA (`reset_password_sira.sh`)

Utilidad de administración para **cambiar la contraseña de cualquier usuario de SIRA** directamente en la base de datos, sin borrar el volumen de PostgreSQL ni perder los datos del sistema.

| Dato | Valor |
| :--- | :--- |
| **Script** | `scripts/reset_password_sira.sh` |
| **Documento** | `docs/infraestructura/reset_password.md` |
| **Requisito** | Contenedores `sira_api` y `sira_db` en marcha |
| **Riesgo** | Bajo (afecta a un único usuario, no borra datos) |

---

## 1. El problema que resuelve

Los usuarios iniciales del sistema (`root`, `admin` y los clientes de ejemplo) se crean mediante los scripts de inicialización de PostgreSQL:

- `backend/database/10-schema.sql` → estructura de tablas.
- `backend/database/20-data.sql` → datos de ejemplo, incluidos los usuarios.

Estos scripts **solo se ejecutan una vez**, cuando Docker crea el volumen `proyecto_sira_postgres_data` por primera vez. A partir de ahí:

1. El volumen persiste entre reinicios; `docker compose up` **no** vuelve a lanzar los `INSERT`.
2. Además, el bloque de usuarios termina en `ON CONFLICT (cif) DO NOTHING`, de modo que **nunca sobreescribe** una contraseña ya existente.

La consecuencia práctica es que las credenciales del seed (`root` / `root1234`) solo son válidas hasta que alguien cambia la contraseña desde la interfaz web. Después de ese cambio, la contraseña real vive únicamente dentro del volumen, y el `20-data.sql` del repositorio queda desactualizado respecto al estado real del sistema.

Síntoma típico: se levanta SIRA después de semanas sin tocarla, se introduce `root` / `root1234` y el login responde **"CIF o contraseña incorrectos"**, aunque el seed del repositorio siga diciendo que esa es la clave.

Este script soluciona ese escenario sin recurrir a la opción destructiva (`docker compose down -v`), que borraría también parcelas, invernaderos, mediciones y el historial de seguridad.

---

## 2. Uso

Desde la raíz del proyecto:

```bash
./scripts/reset_password_sira.sh <CIF>
```

Ejemplos:

```bash
# Resetear root (obliga a elegir una contraseña nueva al entrar)
./scripts/reset_password_sira.sh root

# Resetear un administrador dejando la clave tal cual, sin forzar cambio
./scripts/reset_password_sira.sh admin --sin-cambio-obligatorio

# Resetear un cliente de ejemplo
./scripts/reset_password_sira.sh B04XXXXXX
```

| Argumento | Obligatorio | Descripción |
| :--- | :---: | :--- |
| `<CIF>` | Sí | Identificador del usuario (`cliente.cif`). Es el mismo valor que se teclea en el login. |
| `--sin-cambio-obligatorio` | No | No marca `debe_cambiar_pw`. Sin este flag, SIRA pedirá una contraseña nueva al iniciar sesión. |

La contraseña **no se pasa como argumento**: el script la pide por teclado dos veces, sin mostrarla, y exige un mínimo de 8 caracteres.

---

## 3. Qué hace el script paso a paso

1. **Deduce la ruta del proyecto** a partir de su propia ubicación (`scripts/` → carpeta padre), así que funciona aunque el repositorio se mueva o se renombre. Se puede forzar con la variable `SIRA_DIR`.
2. **Lee `DB_USER` y `DB_NAME`** desde el `.env` del proyecto. No hay credenciales escritas a fuego en el script.
3. **Comprueba que `sira_api` y `sira_db` estén arrancados**; si no, aborta indicando el comando para levantarlos.
4. **Pide la contraseña** por teclado con `read -s` (no aparece en pantalla, ni en el historial de bash, ni en la lista de procesos).
5. **Genera el hash bcrypt (12 rondas)** ejecutando Python dentro del contenedor `sira_api`, que es la imagen que ya lleva el módulo `bcrypt` instalado. La contraseña viaja por `stdin`, nunca como argumento de comando.
6. **Actualiza la fila del usuario** en la tabla `cliente`:
   - `hash_contrasena` → nuevo hash bcrypt.
   - `debe_cambiar_pw` → `TRUE` o `FALSE` según el flag.
   - `activa = TRUE` → reactiva la cuenta si estaba desactivada (borrado lógico).
   - `session_id = NULL` y `ultima_actividad = NULL` → cierra cualquier sesión abierta, evitando el aviso de "sesión iniciada desde otro dispositivo".
7. **Avisa si el CIF no existe**: si el `UPDATE` afecta a 0 filas, no se ha cambiado nada.

---

## 4. Detalles técnicos relevantes

### Por qué bcrypt y no un `UPDATE` con texto plano

La columna `cliente.hash_contrasena` guarda hashes bcrypt (`$2b$12$...`, 60 caracteres). El backend valida con `bcrypt.checkpw()` en `backend/app/auth.py`. Escribir la contraseña en claro rompería el login: el hash sería inválido y `checkpw()` fallaría siempre.

### Por qué el CIF y no el nombre

`authenticate_user()` busca al usuario con `Cliente.cif == username`. El campo `cif` es de tipo `CHAR(9)`, por lo que PostgreSQL rellena con espacios hasta 9 caracteres; la comparación ignora esos espacios finales, así que `root` funciona igual que `root     `.

### Interacción con el historial de contraseñas

El backend guarda un historial de hashes en el volumen `sira_security_history` (`backend/app/security_history.py`) para impedir la reutilización de contraseñas. Ese control se aplica **cuando el usuario cambia la clave desde la web**. Si el script deja `debe_cambiar_pw = TRUE` y la nueva contraseña coincide con una usada antes, el formulario de cambio la rechazará y pedirá otra distinta. Es el comportamiento esperado.

### Alternativa destructiva (no recomendada)

```bash
docker compose down -v && docker compose up -d
```

Recrea el volumen desde cero y devuelve el sistema al estado del seed (`root` / `root1234`, `admin` / `admin1234`, clientes de ejemplo). Es útil en un entorno de desarrollo recién empezado, pero **elimina todos los datos introducidos** y el historial de seguridad. Antes de ejecutarlo conviene volcar la base:

```bash
docker exec sira_db pg_dump -U "$DB_USER" -d "$DB_NAME" > backups/sira_backup_$(date +%Y%m%d_%H%M).sql
```

---

## 5. Copias de seguridad

La carpeta `backups/` de la raíz del proyecto está incluida en `.gitignore`: los volcados contienen datos y hashes de contraseñas reales y **no deben subirse al repositorio**.

| Ruta | Contenido | ¿Se versiona? |
| :--- | :--- | :---: |
| `backups/` | Volcados SQL manuales (`pg_dump`) | ❌ No |
| `$HOME/sira_backups/` | Copias generadas por `scripts/backup_sira.sh` (fuera del repo) | ❌ No |

Restauración de un volcado:

```bash
docker exec -i sira_db psql -U "$DB_USER" -d "$DB_NAME" < backups/sira_backup_AAAAMMDD_HHMM.sql
```

---

## 6. Resumen rápido

```bash
# ¿No puedo entrar con root/root1234? -> resetear y entrar con una clave nueva
./scripts/reset_password_sira.sh root

# Comprobar por API sin abrir el navegador (200 = credenciales válidas)
curl -o /dev/null -w '%{http_code}\n' -X POST http://localhost:8085/api/auth/token \
     -d 'username=root&password=LA_CLAVE_NUEVA'
```
