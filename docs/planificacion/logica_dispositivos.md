# Lógica de Control: Sensores y Actuadores - Proyecto SIRA

En este documento se detalla cómo funciona la lógica de automatización de SIRA. Se ha programado una serie de reglas y prioridades para que el sistema sepa cómo actuar según los datos que recibe de los sensores, siempre buscando proteger el cultivo y la estructura del invernadero.

---

## 1. Funcionamiento del Sistema de Control

Dado que el proyecto utiliza datos simulados, el sistema analiza las mediciones que entran en la base de datos cada pocos segundos y decide qué dispositivos (actuadores) deben encenderse o apagarse.

### Prioridades de Seguridad
Se ha establecido un orden de importancia para que las órdenes no se contradigan:
1. **Seguridad Estructural**: Lo más importante es proteger el invernadero contra el viento fuerte o granizo.
2. **Seguridad del Cultivo**: Evitar que las plantas se hielen o se quemen por el calor.
3. **Optimización**: Mantener las mejores condiciones de luz y humedad para que las plantas crezcan bien.

---

## 2. Reglas de Automatización (Backend)

A continuación se explican las reglas programadas para cada dispositivo:

### Riego (Electroválvulas)
- **Encendido**: Si la humedad del suelo baja del 60%.
- **Apagado**: Cuando la humedad alcanza el 85%.
- **Alerta**: Si la humedad baja del 40%, se considera una situación crítica y se avisa al usuario.

### Ventanas (Motores)
- **Ventilación**: Se abren si la temperatura interior supera los 30ºC.
- **Cierre por Seguridad**: Si el viento sopla a más de 45 km/h o si el sensor detecta lluvia, las ventanas se cierran automáticamente, aunque haga calor dentro. La seguridad de la estructura es lo primero.

### Iluminación LED
Las luces dependen de dos factores: la luz natural y el horario de trabajo.
- **Durante la jornada laboral**: Las luces se encienden si está nublado o atardece (radiación solar menor a 200 W/m²) para que los operarios puedan trabajar.
- **Fuera de horario**: Las luces permanecen apagadas para ahorrar energía, a menos que el usuario las encienda manualmente.

### Extractores de Aire
- Se activan si la humedad relativa dentro del invernadero es muy alta (más del 90%), ayudando a renovar el aire y evitar enfermedades en las plantas.

### Calefacción
- Se enciende si la temperatura baja de los 10ºC para evitar heladas. Se apaga cuando sube a los 12ºC (histéresis para evitar que el motor arranque y pare constantemente).

---

## 3. Control Manual y "Cortesía"

Aunque el sistema es automático, se ha añadido una función para que el agricultor pueda tomar el control:
- Si el usuario enciende o apaga un dispositivo manualmente desde el panel, el sistema automático "respeta" esa decisión durante 2 horas. 
- Pasado ese tiempo, el sistema vuelve al modo automático para evitar descuidos (por ejemplo, dejarse el riego encendido toda la noche).

---

## 4. Gestión por Horarios (Jornada Laboral)

Para el control de las luces y el personal, el sistema consulta un archivo de configuración (`jornada.json`) donde se guardan:
- Los días de la semana que se trabaja.
- Las horas de entrada y salida (pudiendo tener jornada partida).

---
**Lógica de Operación - SIRA**  
*Versión 1.0 Final - Abril 2026*
