# Documentación de la Lógica PHP - Proyecto SIRA

En este documento se explica cómo se ha organizado el código PHP que forma el frontend de SIRA. El objetivo ha sido crear una aplicación web funcional que se comunique de forma segura con la API del backend.

---

## 1. Organización del Código

Para que el proyecto sea modular y fácil de mantener, se ha dividido el código PHP en varias partes:

### Gestión de Infraestructura
- **view_localidades.php**: Es la página de inicio del panel. Agrupa los datos del cliente por municipio y provincia.
- **view_infrastructure.php**: Es el motor principal de visualización. Dependiendo de dónde haga clic el usuario, muestra las Localidades, las Parcelas o los Invernaderos, pasando los datos necesarios mediante variables de sesión o parámetros GET.
- **Validación de datos**: En los formularios de alta (como añadir una parcela), se han programado comprobaciones para asegurar que el código postal es correcto antes de guardar la información.

### Módulos de Gestión (CRUD)
- **Gestión de Usuarios**: Se han creado páginas para dar de alta y editar tanto a clientes como a administradores, comprobando siempre los permisos de cada usuario.
- **Formularios Dinámicos**: Los formularios detectan quién está conectado. Por ejemplo, si eres un cliente, algunos campos críticos de los invernaderos aparecen bloqueados y solo los puede tocar un administrador.

---

## 2. Seguridad y Acceso

Se ha implementado un sistema de seguridad basado en tokens para proteger el acceso a los datos.

### Uso de JWT (JSON Web Token)
- **Comunicación Segura**: Tras el login, el servidor PHP guarda un token (JWT). En cada petición que el frontend hace a la API, se envía este token para validar quién es el usuario.
- **Roles y Permisos**: En el archivo `header.php`, que se carga en todas las páginas, el sistema extrae del token el rol del usuario (Root, Admin o Cliente). Esto permite ocultar o mostrar botones y funciones según el nivel de acceso del usuario.
- **Cierre de Sesión**: Si el token caduca o es incorrecto, el sistema redirige automáticamente al usuario a la página de inicio de sesión.

---

## 3. Visualización y Simulación IoT

### Motor de Clima (`weather_engine.php`)
Este componente se encarga de cambiar el aspecto visual del dashboard según el clima simulado:
- Lee el estado de los sensores desde la base de datos.
- Decide qué archivos CSS de clima debe cargar (lluvia, sol, nubes, etc.).
- Asegura que el efecto visual se mantenga mientras el usuario navega por las diferentes páginas.

### Panel de Control de Sensores (`sensores.php`)
- **Datos en tiempo real**: Llama a la API para obtener las lecturas de temperatura, humedad y viento.
- **Control de dispositivos**: Permite al usuario encender o apagar dispositivos (como ventanas o riego) mediante botones que envían órdenes autenticadas a la API.

---

## 4. Control de Estado

- **Borrado Lógico**: En lugar de borrar definitivamente los datos de la base de datos, se utiliza un campo llamado `activa`. Esto permite "archivar" elementos y recuperarlos si es necesario, evitando que se pierda el historial de los sensores.
- **Restricción de Acceso**: Se han añadido comprobaciones al inicio de cada script de gestión para asegurar que un cliente no pueda entrar en las funciones de administración escribiendo la URL directamente.

---

**Documentación de Lógica PHP - SIRA**  
*Versión 1.0 Final - Abril 2026*
