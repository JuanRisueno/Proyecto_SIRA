#!/bin/bash

# ==============================================================================
# SCRIPT DE COPIA DE SEGURIDAD V6.0 (INTELIGENTE) - PROYECTO SIRA
# Autor: Juan Antonio Risueño
# Lógica:
# 1. Si no hay backup de ESTE AÑO -> Crea ANUAL.
# 2. Si no hay backup de ESTE MES -> Crea MENSUAL.
# 3. Si ya existen ambos -> Crea DIARIO.
# ==============================================================================

# 1. CONFIGURACIÓN
# ----------------
ORIGEN="$HOME/Proyecto_SIRA"
RAIZ_BACKUPS="$HOME/sira_backups"

# Identificadores de tiempo actuales
YEAR_ID=$(date +"%Y")       # Ej: 2026
MONTH_ID=$(date +"%Y-%m")   # Ej: 2026-01
FULL_DATE=$(date +"%Y-%m-%d_%H%M%S")

# Rutas de carpetas
DIR_ANUALES="$RAIZ_BACKUPS/anuales"
DIR_MENSUALES="$RAIZ_BACKUPS/mensuales"
DIR_DIARIOS="$RAIZ_BACKUPS/diarios"

# Colores
VERDE='\033[0;32m'
AZUL='\033[0;34m'
AMARILLO='\033[1;33m'
CYAN='\033[0;36m'
ROJO='\033[0;31m'
NC='\033[0m'

echo -e "${AZUL}🧠 Analizando historial de backups SIRA...${NC}"

# 2. CEREBRO DE DECISIÓN (State-Check)
# ------------------------------------
# Nota: Buscamos archivos que contengan el patrón del año o mes en su nombre.

# Flag para saber si existe
TIENE_ANUAL=0
TIENE_MENSUAL=0

# A) Comprobar ANUAL (¿Existe algo en anuales con "2026"?)
if [ -d "$DIR_ANUALES" ]; then
    if ls "$DIR_ANUALES"/*backup_anual_$YEAR_ID* >/dev/null 2>&1; then
        TIENE_ANUAL=1
    fi
fi

# B) Comprobar MENSUAL (¿Existe algo en mensuales con "2026-01"?)
if [ -d "$DIR_MENSUALES" ]; then
    if ls "$DIR_MENSUALES"/*backup_mensual_$MONTH_ID* >/dev/null 2>&1; then
        TIENE_MENSUAL=1
    fi
fi

# C) TOMA DE DECISIÓN
if [ "$TIENE_ANUAL" -eq 0 ]; then
    # CASO 1: Primer backup del año (Prioridad Máxima)
    TIPO="anual"
    MAX_COPIAS=2
    DESTINO_FINAL="$DIR_ANUALES/backup_anual_$FULL_DATE"
    echo -e "${AMARILLO}✨ No se detectó copia del $YEAR_ID. Creando backup ANUAL.${NC}"

elif [ "$TIENE_MENSUAL" -eq 0 ]; then
    # CASO 2: Ya hay anual, pero es el primero del mes
    TIPO="mensual"
    MAX_COPIAS=3
    DESTINO_FINAL="$DIR_MENSUALES/backup_mensual_$FULL_DATE"
    echo -e "${CYAN}📅 No se detectó copia de este mes ($MONTH_ID). Creando backup MENSUAL.${NC}"

else
    # CASO 3: Ya tenemos anual y mensual cubiertos
    TIPO="diario"
    MAX_COPIAS=10
    DESTINO_FINAL="$DIR_DIARIOS/backup_diario_$FULL_DATE"
    echo -e "${VERDE}✅ Cobertura mensual completa. Creando backup DIARIO.${NC}"
fi

# 3. CREAR DIRECTORIO DESTINO
# ---------------------------
mkdir -p "$DESTINO_FINAL"

# 4. GENERAR METADATOS
# --------------------
echo "Tipo: $TIPO" > "$DESTINO_FINAL/info_backup.txt"
echo "Fecha: $(date)" >> "$DESTINO_FINAL/info_backup.txt"
echo "Usuario: $USER" >> "$DESTINO_FINAL/info_backup.txt"

# 5. COMPRESIÓN
# -------------
echo "📦 Comprimiendo..."
nice -n 19 tar -czf "$DESTINO_FINAL/codigo_fuente.tar.gz" \
    --exclude='.git' \
    --exclude='.idea' \
    --exclude='__pycache__' \
    --exclude='venv' \
    --exclude='*.pyc' \
    --exclude='pg_data' \
    --exclude='.DS_Store' \
    -C "$HOME" "Proyecto_SIRA"

if [ $? -ne 0 ]; then
    echo -e "${ROJO}❌ Error al comprimir.${NC}"
    exit 1
fi

echo -e "${VERDE}💾 Copia guardada en: $DESTINO_FINAL${NC}"

# 6. LIMPIEZA AUTOMÁTICA (ROTACIÓN)
# ---------------------------------
CARPETA_PADRE=$(dirname "$DESTINO_FINAL")
echo -e "${AZUL}🧹 Mantenimiento: Revisando límite de $MAX_COPIAS copias en $TIPO...${NC}"

cd "$CARPETA_PADRE"
# Listamos carpetas que empiecen por "backup_"
NUM_BACKUPS=$(ls -1d backup_* 2>/dev/null | wc -l)

if [ "$NUM_BACKUPS" -gt "$MAX_COPIAS" ]; then
    SOBRAN=$((NUM_BACKUPS - MAX_COPIAS))
    echo -e "${AMARILLO}✂ Eliminando las $SOBRAN copias más antiguas...${NC}"
    # Borrado seguro de las más viejas
    ls -1dr backup_* | tail -n +$(($MAX_COPIAS + 1)) | xargs rm -rf
    echo "Limpieza finalizada."
else
    echo "Espacio correcto ($NUM_BACKUPS/$MAX_COPIAS)."
fi

echo "---------------------------------------------------"