# Parámetros Óptimos de Cultivos - Almería y Murcia

Este documento recoge los parámetros ambientales ideales para los cultivos más comunes en Almería y Murcia. Se han utilizado estos datos para configurar las reglas automáticas del sistema SIRA.

## 1. Datos de Cultivos (Almería y Murcia)

Según los datos de superficie cultivada en invernaderos:

### Almería:
- **Sandía:** 31,6%
- **Pimiento:** 22,6%
- **Pepino:** 14,4%
- **Tomate:** 12,6%
- **Calabacín:** 10,6%
- **Berenjena:** 5,3%
- **Melón:** 2,9%
- **Judía verde:** 0,1%

### Murcia:
- **Tomate:** 57,04%
- **Pimientos:** 25,91%
- **Melón:** 6,80%
- **Sandía:** 2,49%
- **Pepino:** 2,14%

---

## 2. Tabla de Parámetros de Referencia

Se ha configurado el sistema con estos rangos ideales para que el simulador pueda mostrar cómo actúan los actuadores:

| Cultivo | Temp. Día (ºC) | Temp. Noche (ºC) | Humedad (%) | pH Óptimo | Riego (L/m²) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Tomate** | 18 - 30 | 15 - 20 | 60 - 80 | 6.0 - 6.8 | 5.0 |
| **Pimiento** | 21 - 26 | 18 - 21 | 65 - 85 | 5.5 - 7.0 | 4.5 |
| **Pepino** | 20 - 30 | 17 - 20 | 70 - 90 | 5.5 - 7.0 | 4.2 |
| **Sandía** | 20 - 30 | 15 - 20 | 60 - 70 | 6.0 - 6.8 | 3.8 |
| **Melón** | 20 - 30 | 15 - 20 | 60 - 70 | 6.0 - 6.8 | 3.8 |
| **Calabacín** | 25 - 35 | 10 - 15 | 65 - 80 | 5.6 - 6.8 | 4.0 |
| **Berenjena** | 25 - 30 | 15 - 18 | 60 - 80 | 5.5 - 6.8 | 5.0 |
| **Judía verde** | 20 - 25 | 15 - 18 | 65 - 75 | 6.0 - 7.5 | 3.2 |

---

## 3. Notas sobre el Cultivo

- **Calor extremo**: Temperaturas de más de 35ºC pueden dañar el fruto, por lo que el sistema debe abrir ventanas o usar extractores.
- **Humedad**: Si la humedad sube del 80% hay riesgo de hongos, y si baja del 50% la planta deja de crecer.
- **pH**: Es importante controlar el pH del agua de riego para que las plantas absorban bien los nutrientes.

---

## 4. Fuentes Consultadas

Se han obtenido estos datos técnicos de las siguientes fuentes:
1.  **Fundación Cajamar**: Guía de cultivos en invernadero.
2.  **MAPA**: Fichas técnicas de cultivos.
3.  **InfoAgro Almería**: Portal especializado en agricultura.

---

> [!NOTE]
> Estos datos se utilizan como base de conocimiento para la lógica de automatización de SIRA.

**Proyecto SIRA - Parámetros Técnicos**  
*Fecha: 30 de Abril de 2026*
