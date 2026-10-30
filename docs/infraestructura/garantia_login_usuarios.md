# Garantía de login: ¿reconocerá SIRA a los usuarios en cualquier equipo?

**Pregunta que responde este documento:** si levanto SIRA en otro Linux, en un PC
con Windows y Docker Desktop o en una instancia de AWS, ¿podré entrar con los
usuarios que ya están en la base de datos?

**Respuesta corta:** sí. La autenticación **no depende del sistema operativo**:
los usuarios viven en PostgreSQL y el backend compara el CIF y el hash bcrypt.
Lo que sí puede fallar es que la base de datos no sea la que crees (volumen
distinto, volumen borrado o contraseña cambiada). Este documento explica qué
depende de la plataforma, qué no, y cómo comprobarlo en 10 segundos.

---

## 1. Cómo decide SIRA si un usuario es válido

| Paso | Dónde | Qué hace |
| :--- | :--- | :--- |
| 1 | `frontend/index.php` | Quita espacios del CIF y la contraseña y hace `POST` a `/api/auth/token` |
| 2 | `backend/app/routers/jwt.py` | Recibe `username` (CIF) y `password` |
| 3 | `backend/app/auth.py` | Busca el CIF exacto; si no aparece, reintenta sin distinguir mayúsculas |
| 4 | `backend/app/auth.py` | Comprueba `activa = TRUE` (soft delete) |
| 5 | `backend/app/auth.py` | `bcrypt.checkpw()` contra `hash_contrasena` |
| 6 | `routers/jwt.py` | Genera el JWT con `sub`, `rol`, `id`, `empresa` y `sid` |

Ninguno de esos pasos consulta el sistema operativo, la ruta del proyecto ni la
zona horaria. **El único dato que importa está en la tabla `cliente`.**

### Lo que sí influye

| Factor | ¿Afecta al login? | Detalle |
| :--- | :--- | :--- |
| Contenido de la tabla `cliente` | **Sí** | Es la única fuente de verdad |
| Que el seed `20-data.sql` se haya ejecutado | **Sí** | Solo corre al crear el volumen por primera vez |
| Mayúsculas/espacios al escribir el CIF | Ya no | Tras la mejora, `ROOT`, `Root` o ` root ` entran igual |
| Contraseña | Sí | Si se cambió desde la web, la del seed ya no vale |
| Sistema operativo del host | No | Todo ocurre dentro de los contenedores Linux |
| Versión de PostgreSQL | No (misma imagen) | `postgres:16-alpine` está fijada en `docker-compose.yml` |
| Ruta o nombre de la carpeta | **Sí, indirectamente** | Cambia el nombre del volumen (ver apartado 4.1) |
| Fin de línea CRLF de Windows | No | Probado: los `.sql` suben igual (ver apartado 3) |
| Caracteres especiales en `DB_PASSWORD` | Ya no | La URL se construye codificada en `database.py` |

---

## 2. Dónde viven realmente los usuarios

Los hashes **no están en el repositorio ni en la imagen**: están dentro del
volumen de Docker. Ese volumen se llama, por defecto,
`<nombre-de-la-carpeta>_postgres_data`:

```bash
docker volume ls | grep postgres
# proyecto_sira_postgres_data      <- carpeta /home/.../Proyecto_SIRA
# sira_project_postgres_data       <- carpeta anterior, si cambió de nombre
```

Consecuencia práctica: **si renombras o mueves la carpeta del proyecto, Docker
Compose crea un volumen nuevo y la base arranca desde el seed**. Los usuarios
del seed vuelven a funcionar, pero cualquier contraseña cambiada antes se pierde
(no los datos: esos quedan en el volumen antiguo, huérfano).

Comando para saber qué base está usando el contenedor en ese momento:

```bash
docker exec sira_db psql -U "$(awk -F= '$1=="DB_USER"{print $2}' .env)" \
  -d "$(awk -F= '$1=="DB_NAME"{print $2}' .env)" \
  -c "select count(*) as usuarios from cliente;"
```

---

## 3. Verificaciones realizadas sobre el sistema real

| Prueba | Resultado |
| :--- | :--- |
| Codificación de la base | `UTF8`, `en_US.utf8` |
| Versión | PostgreSQL 16.13 (Alpine) |
| Usuarios en `cliente` | 7, todos `activa = TRUE` |
| Formato de los hashes | `$2b$12$…`, 60 caracteres (bcrypt válido) |
| Login por API de los 7 usuarios del seed | **200** en los 7 |
| Contraseña incorrecta | 401 (rechazo correcto, no 500) |
| CIF con mayúsculas (`ROOT`) y con espacios (` root `) | 200 tras la mejora |
| `10-schema.sql` + `20-data.sql` con finales de línea **CRLF** (Windows) en una base limpia | 7 usuarios, 7 activos, hashes correctos |
| `.env` con CRLF en `docker compose config` | Variables leídas sin `\r` |
| Contraseña con `@ : / # %` en `DB_USER`/`DB_PASSWORD` | URL generada con `quote_plus`, credenciales recuperadas intactas |
| Script de verificación completo | `7 usuarios | 7 correctos | 0 fallos` |

---

## 4. Los cuatro riesgos reales y su solución

### 4.1. Volumen distinto (el más habitual)

**Síntoma:** acabas de levantar el stack y `root` / `root1234` no entra.

**Causa:** el volumen ya existía de un arranque anterior, o la carpeta cambió de
nombre y se creó uno nuevo. En el primer caso, las contraseñas son las que se
cambiaron en su día; en el segundo, las del seed.

**Comprobación:**

```bash
docker volume ls | grep postgres                              # ¿cuántos volúmenes hay?
docker exec sira_db psql -U <DB_USER> -d <DB_NAME> -tAc \
  "select cif, debe_cambiar_pw from cliente order by cliente_id;"
```

**Solución:** usar `scripts/reset_password_sira.sh <CIF>` sobre la base que
realmente está en marcha. **No** hace falta `docker compose down -v`.

### 4.2. Contraseña cambiada

`debe_cambiar_pw = TRUE` obliga a fijar una contraseña nueva tras el primer
login. Desde ese momento, la del seed deja de ser válida y el repositorio no lo
sabe. Es el comportamiento esperado, no un fallo.

**Solución:** reset con el script, o entrar con la contraseña nueva.

### 4.3. CIF escrito de otra forma

Antes, `ROOT` o ` root ` devolvían 401. Ahora el backend recorta espacios y hace
un segundo intento sin distinguir mayúsculas, así que un teclado de móvil que
auto-capitaliza ya no rompe el acceso. La contraseña **sí** distingue mayúsculas:
`Root1234` y `root1234` no son la misma.

### 4.4. Contraseña de la base con caracteres especiales

Una URL `postgresql://usuario:clave@host/db` se rompe si la clave contiene `@`,
`:`, `/`, `#` o `%`. En AWS es frecuente usar contraseñas fuertes con símbolos.
Ahora `backend/app/database.py` monta la URL a partir de `DB_USER`,
`DB_PASSWORD`, `DB_NAME`, `DB_HOST` y `DB_PORT` aplicando `quote_plus`, y
`docker-compose.yml` pasa esas variables al contenedor `api`. `DATABASE_URL`
sigue existiendo como alternativa para conexiones externas.

> Si ejecutas la API **fuera** de Docker (modo desarrollo del README), define
> `DB_HOST=localhost`; dentro de Compose el valor correcto es `db` y ya viene
> puesto.

---

## 5. Checklist por plataforma

### Linux (este equipo)

```bash
docker compose up -d
./scripts/verificar_usuarios_sira.sh
```

### Windows con Docker Desktop

| Punto | Qué hacer |
| :--- | :--- |
| Backend de Docker | WSL 2 (no el hipervisor antiguo): bind mounts y red más fiables |
| Clonado | `git clone` (respeta `.gitattributes` y los LF). Evitar copiar/pegar scripts en el Bloc de notas |
| Ejecutar `.sh` | Desde **Git Bash** o **WSL**; PowerShell no ejecuta bash |
| `.env` | `cp env.ejemplo .env` y rellenar. Compose tolera CRLF, ya probado |
| Permisos | Si el script no arranca: `git update-index --chmod=+x scripts/*.sh` o `chmod +x` desde WSL |
| Comprobación | `./scripts/verificar_usuarios_sira.sh` |

### AWS EC2 (ver también `docs/infraestructura/guia_despliegue_aws.md`)

| Punto | Qué hacer |
| :--- | :--- |
| Puerto | `SIRA_PORT=80` en `.env` |
| Security Group | Abrir 80 (o el puerto elegido); **no** abrir 5432 |
| Instalación | Docker + plugin `docker compose` (v2) |
| Arranque | `docker compose up -d --build` (la `--build` es obligatoria tras cambiar `requirements.txt`) |
| Comprobación | `./scripts/verificar_usuarios_sira.sh` |
| Persistencia | El volumen vive en el disco EBS: si la instancia se destruye sin ese disco, la base vuelve al seed |

---

## 6. Comprobación en 10 segundos

```bash
./scripts/verificar_usuarios_sira.sh
```

Salida esperada:

```
CIF        ROL      ACTIVA   HASH      LOGIN
---------- -------- -------- --------- -----
root       root     t        OK        OK [ok]
admin      admin    t        OK        OK [ok]
...
Resumen: 7 usuarios | 7 correctos | 0 avisos | 0 fallos
RESULTADO: TODO CORRECTO. SIRA reconoce a los usuarios de la base de datos.
```

Cómo interpretar la columna `LOGIN`:

| Valor | Significado |
| :--- | :--- |
| `OK` | El usuario entra con la contraseña del seed |
| `clave distinta a la del seed` | El hash es válido, pero alguien cambió la contraseña: usa `reset_password_sira.sh` |
| `sin clave conocida` | Usuario añadido después del seed; solo se comprueba el hash |
| `API NO RESPONDE` | La API está caída: `docker logs sira_api` |
| `INACTIVO` | `activa = FALSE` (borrado lógico): reactívalo con el script de reseteo |
| `[FALLO]` | Hash corrupto, cuenta inactiva o API con error: hay que intervenir |

> **Aviso:** cada login de prueba invalida la sesión abierta de ese usuario (el
> control de concurrencia cambia el `session_id`). Usa `--sin-login` si alguien
> está trabajando en ese momento.

---

## 7. Qué no cubre esta garantía

- **`docker compose down -v`**: borra el volumen. Al levantar de nuevo, la base
  vuelve al seed (no se queda vacía), pero se pierden parcelas, invernaderos y
  mediciones.
- **Restaurar un backup antiguo**: los hashes vuelven a la fecha del volcado.
- **Cambiar `JWT_SECRET_KEY`**: no afecta a los usuarios, pero invalida los
  tokens emitidos (todos tendrán que volver a iniciar sesión).
- **Perder el volumen `sira_security_history`**: no impide el login, pero se
  pierde el historial que impide reutilizar contraseñas antiguas.

---

## 8. Resumen para la defensa

> Los usuarios de SIRA están en PostgreSQL, no en el código. El login compara el
> CIF y un hash bcrypt, operaciones idénticas en cualquier máquina. La plataforma
> solo puede afectar al **empaquetado** (fin de línea, contraseñas con símbolos,
> nombre del volumen), y esos tres puntos están corregidos y verificados con un
> script que cualquiera puede ejecutar tras el despliegue.
