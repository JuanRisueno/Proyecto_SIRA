# Gestión de Cultivos - Proyecto SIRA

En este documento se explica cómo se ha diseñado el sistema de gestión de cultivos en SIRA. Se ha decidido usar una base de datos local de conocimientos para asegurar que el sistema sea rápido y funcione siempre, sin depender de conexiones a internet externas.

---

## 1. Diseño del Sistema de Cultivos

Para que el proyecto sea robusto y no falle durante la presentación, se ha descartado el uso de APIs externas (como Perenual). En su lugar, se ha creado una base de datos propia con la información de los cultivos más comunes.

### Ventajas de este diseño:
- **Independencia**: El sistema funciona al 100% en local, sin necesidad de internet.
- **Velocidad**: Las consultas son inmediatas al estar los datos en el mismo servidor.
- **Sencillez**: Se ha eliminado código complejo de conexión con servidores externos, centrándose en la lógica del proyecto.

---

## 2. Funcionamiento para el Usuario

Se ha diseñado un proceso muy sencillo para que el agricultor configure sus invernaderos:

1.  **Selección asistida**: Al añadir un cultivo, el usuario puede elegir entre variedades típicas (Tomate, Pimiento, Sandía, Pepino, Melón). Al seleccionarlas, el sistema rellena automáticamente los valores ideales de temperatura y humedad.
2.  **Modo libre**: Si el usuario cultiva algo diferente, puede elegir la opción "Personalizado" y escribir el nombre y los parámetros que él considere oportunos.
3.  **Validación**: El sistema comprueba que los números introducidos tienen sentido (por ejemplo, que la temperatura máxima sea mayor que la mínima) antes de guardar.

---

## 3. Modelo de Datos (SQL)

Se ha creado una tabla maestra con los parámetros científicos de los cultivos seleccionados para la demo:

```sql
-- Tabla con los datos de referencia
CREATE TABLE CULTIVO_MAESTRO (
    id SERIAL PRIMARY KEY,
    nombre_comun VARCHAR(50) UNIQUE,
    temp_min_ideal DECIMAL(4,2),
    temp_max_ideal DECIMAL(4,2),
    hum_min_ideal INT,
    hum_max_ideal INT,
    ph_ideal DECIMAL(3,1)
);

-- Datos reales para Almería y Murcia
INSERT INTO CULTIVO_MAESTRO (nombre_comun, temp_min_ideal, temp_max_ideal, hum_min_ideal, hum_max_ideal, ph_ideal)
VALUES 
('Tomate', 18.00, 27.00, 60, 80, 6.0),
('Pimiento', 20.00, 28.00, 65, 85, 6.5),
('Sandía', 22.00, 32.00, 60, 75, 6.2),
('Pepino', 18.00, 25.00, 70, 90, 6.0),
('Melón', 25.00, 35.00, 55, 70, 6.8);
```

---

## 4. Conclusión

Este enfoque permite demostrar que el sistema es capaz de gestionar reglas biológicas y de control climático de forma autónoma. Durante la defensa, se podrá mostrar cómo SIRA reacciona de forma diferente según el cultivo que se haya configurado en cada nave.

---
**Gestión de Cultivos - SIRA**  
*Versión 1.0 Final - Abril 2026*
