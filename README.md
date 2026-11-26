# Sistema de Control de Préstamos de Equipo Tecnológico

Proyecto integrador para la asignatura **Taller de Base de Datos**.  
**Alumno:** Carlos Daniel Arroyo Aldama  
**Motor de Base de Datos:** MariaDB (InnoDB / UTF8MB4)  
**Backend y Frontend:** PHP 8 (PDO), HTML5, CSS3 y JavaScript  

---

## Descripción de la Aplicación

Sistema web diseñado para administrar y controlar el préstamo de equipo tecnológico dentro de un laboratorio o centro de cómputo. Permite llevar un registro actualizado del inventario disponible, gestionar los usuarios autorizados (administradores, encargados y solicitantes), registrar vales de préstamo con múltiples equipos y documentar el estado físico de cada artículo tanto al entregarse como al devolverse.

---

## Contenido del Repositorio

* `index.php`: Panel principal (Dashboard) con indicadores en tiempo real y movimientos recientes.
* `equipos.php`: Módulo CRUD para la gestión del inventario de equipos tecnológicos.
* `solicitantes.php`: Módulo CRUD para la administración de usuarios, encargados y solicitantes.
* `prestamos.php`: Registro de vales de préstamo con múltiples equipos y control de devoluciones mediante transacciones PDO.
* `consultas.php`: Reportes dinámicos, filtros y consultas multitabla (`JOIN`).
* `login.php` / `logout.php`: Control de inicio y cierre de sesión con verificación segura de contraseñas.
* `config/conexion.php`: Plantilla de configuración para la conexión PDO con MariaDB (`db_inventario-03`).
* `includes/auth.php`: Verificación de sesiones activas en las vistas protegidas.
* `css/` y `js/`: Hojas de estilo e interactividad de la interfaz web.
* `control_prestamos_db.sql`: Script DDL completo para crear la base de datos, sus tablas, llaves primarias, llaves foráneas, índices únicos y restricciones de integridad referencial.
* `README.md`: Documentación general del proyecto y descripción del modelo de datos.

---

## Modelo de Datos (3.ª Forma Normal)

La base de datos (`db_inventario-03`) está estructurada en 5 tablas relacionales:

1. **`usuarios`**: Almacena los datos de las personas que interactúan con el sistema (alumnos, docentes, encargados y administradores), incluyendo su número de control, correo institucional, contraseña encriptada (`password_hash`) y rol.
2. **`categorias`**: Clasifica los equipos tecnológicos por tipo (por ejemplo: Laptops, Proyectores, Redes, Audio y Video, Accesorios) para mantener ordenado el catálogo.
3. **`equipos`**: Registra cada dispositivo físico del inventario con su código interno, marca, modelo, número de serie y estado operativo (`disponible`, `prestado`, `mantenimiento`, `baja`).
4. **`prestamos`**: Representa el encabezado o vale de cada operación de préstamo, vinculando al solicitante y al encargado que autoriza la salida, junto con las fechas de préstamo, devolución estimada, devolución real y estatus.
5. **`detalle_prestamo`**: Tabla intermedia que desglosa los equipos incluidos en cada vale de préstamo y registra la condición física de entrega y devolución de cada dispositivo.

### Relaciones y Cardinalidades

* **`categorias (1) ── (N) equipos`**: Una categoría agrupa múltiples equipos, mientras que cada equipo pertenece a una sola categoría.
* **`usuarios (1) ── (N) prestamos`**: Un usuario puede realizar múltiples solicitudes de préstamo (`id_solicitante`) y un encargado puede autorizar múltiples préstamos (`id_encargado`).
* **`prestamos (N) ── (M) equipos`** *(resuelta mediante `detalle_prestamo`)*: Un mismo vale de préstamo puede incluir varios equipos simultáneamente, y un equipo puede ser prestado en distintas ocasiones a lo largo del tiempo.

---

## Instalación y Despliegue

1. **Base de datos:** Abrir HeidiSQL o la consola de MariaDB, crear la base de datos `db_inventario-03` y ejecutar el script DDL (`control_prestamos_db.sql`) verificando que las 5 tablas utilicen el motor `InnoDB`.
2. **Conexión PHP:** Editar el archivo `config/conexion.php` con las credenciales locales de MariaDB (`$user` y `$pass`).
3. **Servidor Web:** Colocar los archivos del proyecto dentro del directorio raíz de Apache (por ejemplo, `/var/www/html/`) con PHP 8 y la extensión `pdo_mysql` habilitada.
