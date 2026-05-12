# Estrategia de Simulación IoT - Proyecto SIRA

Dado que no se dispone de sensores físicos para la defensa del proyecto, se ha desarrollado un sistema de simulación por software que permite demostrar cómo reacciona el backend de SIRA ante diferentes situaciones climáticas.

---

## 1. Escenarios de Prueba (Presets)

Se han creado una serie de escenarios fijos que se pueden activar durante la presentación para mostrar el funcionamiento del sistema:

1.  **Condiciones Ideales**: Todo funciona normal, el riego está apagado y las ventanas entreabiertas.
2.  **Tormenta**: Simula viento fuerte y lluvia. El sistema debe cerrar las ventanas por seguridad.
3.  **Ola de Calor**: La temperatura sube de 40 grados. El sistema debe activar los extractores y el riego si la humedad baja.
4.  **Helada nocturna**: La temperatura baja de 5 grados. Se activa la calefacción para proteger el cultivo.
5.  **Día Nublado**: Poca radiación solar, lo que obliga a encender las luces LED.

---

## 2. Funcionamiento Técnico del Simulador

- **Inserción de datos**: Se ha programado un script en Python que se conecta a la base de datos PostgreSQL e inserta nuevas mediciones cada 10 segundos.
- **Realismo**: Para que los datos no parezcan artificiales (líneas rectas), el script añade pequeñas variaciones aleatorias a los valores.
- **Respuesta Automática**: El backend de SIRA analiza estos datos entrantes y decide al momento si debe activar o desactivar los actuadores (luces, riego, ventanas, etc.).

---

## 3. Gráficas y Visualización

Para mostrar los datos en el dashboard sin usar librerías externas pesadas (como Chart.js), se ha optado por dibujar las gráficas directamente con **SVG** desde PHP:

- **Líneas sencillas**: Se usan etiquetas `<polyline>` para unir los puntos de los sensores. Es un método muy ligero y compatible con cualquier navegador.
- **Refresco automático**: La página se recarga cada 10-15 segundos mediante una etiqueta HTML `<meta refresh>`, permitiendo ver cómo evolucionan los datos sin tener que pulsar F5.

---

## 4. Dispositivos Simulados

Para el proyecto se han seleccionado 5 sensores y 5 actuadores que cubren las necesidades básicas de un invernadero en nuestra zona (Almería/Murcia):

### Sensores (Entrada)
1. **Temperatura**: Para el control del clima interior.
2. **Lluvia**: Detecta si está lloviendo para cerrar ventanas.
3. **Radiación Solar**: Controla la iluminación necesaria.
4. **Humedad del Suelo**: Indica cuándo es necesario regar.
5. **Viento**: Para proteger la estructura del invernadero si hay ráfagas fuertes.

### Actuadores (Salida)
1. **Riego**: Control de las electroválvulas de agua.
2. **Motor de Ventana**: Apertura y cierre de ventilación.
3. **Luces LED**: Iluminación artificial para las plantas.
4. **Extractores**: Para renovar el aire y bajar la temperatura.
5. **Calefacción**: Para evitar que las plantas se hielen por la noche.

---

**Plan de Simulación - SIRA**  
*Versión 1.0 Final - Abril 2026*
