# Gestión de Horarios y Configuración Global - Proyecto SIRA

En este documento se explica cómo se ha diseñado el sistema de horarios laborables en SIRA. Se ha implementado una lógica que permite configurar un horario general para toda la empresa o personalizarlo para cada invernadero de forma individual.

---

## 1. Niveles de Configuración

Para facilitar el trabajo al usuario, se han creado dos niveles de configuración de horarios:

1.  **Horario General (Cliente)**: Es el horario base que se aplica a todos los invernaderos del mismo dueño. Se guarda en un archivo JSON asociado al ID del cliente.
2.  **Horario Individual (Invernadero)**: Si un invernadero concreto necesita un horario diferente (por ejemplo, por el tipo de planta), se puede configurar de forma independiente.

---

## 2. Lógica de Herencia (Sincronización)

Se ha añadido una función llamada "Heredar de Global":

- **Si está activada**: El invernadero ignora su propia configuración y usa siempre el horario general del cliente. Esto es muy útil porque si el dueño cambia la hora de entrada de toda la empresa, solo tiene que hacerlo una vez y se aplica a todos sus invernaderos automáticamente.
- **Si está desactivada**: El invernadero usa su horario específico, permitiendo tener turnos especiales.

---

## 3. Funcionamiento en el Backend (API)

El servidor de SIRA (FastAPI) gestiona estos horarios mediante archivos JSON. Se han creado rutas específicas para que el frontend pueda leer y guardar estas configuraciones tanto a nivel global como individual.

Cuando el sistema necesita saber si se está trabajando en un momento dado, sigue estos pasos:
1. Comprueba si el invernadero hereda el horario global.
2. Carga el archivo JSON correspondiente.
3. Mira qué día de la semana es y si la hora actual está dentro de los tramos de trabajo definidos.

---

## 4. Interfaz de Usuario

En el panel de control, se han añadido varios elementos para que esto sea fácil de usar:
- **Icono de enlace (🔗)**: Aparece en los invernaderos que están siguiendo el horario general.
- **Bloqueo de formulario**: Si un invernadero está configurado para heredar el horario global, el formulario de edición individual se bloquea para evitar errores, informando al usuario de que debe cambiarlo en la configuración general.

---

## 5. Conclusión

Este sistema de gestión por archivos JSON permite que la aplicación sea muy rápida, ya que no tiene que consultar constantemente tablas complejas en la base de datos para saber si debe encender las luces o no. Además, facilita mucho la gestión cuando un cliente tiene muchas naves bajo su cargo.

---
**Gestión de Horarios - SIRA**  
*Versión 1.0 Final - Abril 2026*
