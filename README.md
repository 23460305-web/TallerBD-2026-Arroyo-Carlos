# Sistema de Control de Préstamos de Equipo Tecnológico

Proyecto integrador para la asignatura **Taller de Base de Datos**.  
**Alumno:** Carlos Daniel Arroyo Aldama  
**Grupo:** 
**Motor de Base de Datos:** MariaDB (InnoDB / UTF8MB4)

---

## Descripción de la Aplicación

Sistema web diseñado para administrar y controlar el préstamo de equipo tecnológico dentro de un laboratorio o centro de cómputo. Permite llevar un registro actualizado del inventario disponible, gestionar los usuarios autorizados (administradores, encargados y solicitantes), registrar vales de préstamo con múltiples equipos y documentar el estado físico de cada artículo tanto al entregarse como al devolverse.

---

## Contenido del Repositorio

* **`control_prestamos_db.sql`**: Script DDL completo para crear la base de datos `control_prestamos_db`, sus tablas, llaves primarias, llaves foráneas, índices únicos y restricciones de integridad referencial.
* **`README.md`**: Documentación general del proyecto y descripción del modelo de datos.

---

## Modelo de Datos (3.ª Forma Normal)

La base de datos está estructurada en **5 tablas relacionales**:

1. **`usuarios`**: Almacena los datos de las personas que interactúan con el sistema (alumnos, docentes, encargados y administradores), incluyendo su número de control, correo institucional, contraseña encriptada (`password_hash`) y rol.
2. **`categorias`**: Clasifica los equipos tecnológicos por tipo (por ejemplo: *Laptops*, *Proyectores*, *Redes*, *Audio y Video*, *Accesorios*) para mantener ordenado el catálogo.
3. **`equipos`**: Registra cada dispositivo físico del inventario con su código interno, marca, modelo, número de serie y estado operativo (`disponible`, `prestado`, `mantenimiento`, `baja`).
4. **`prestamos`**: Representa el encabezado o vale de cada operación de préstamo, vinculando al solicitante y al encargado que autoriza la salida, junto con las fechas de préstamo, devolución estimada, devolución real y estatus.
5. **`detalle_prestamo`**: Tabla intermedia que desglosa los equipos incluidos en cada vale de préstamo y registra la condición física de entrega y devolución de cada dispositivo.

### Relaciones y Cardinalidades

* **`categorias` (1) ── (N) `equipos`**: Una categoría agrupa múltiples equipos, mientras que cada equipo pertenece a una sola categoría.
* **`usuarios` (1) ── (N) `prestamos`**: Un usuario puede realizar múltiples solicitudes de préstamo (`id_solicitante`) y un encargado puede autorizar múltiples préstamos (`id_encargado`).
* **`prestamos` (N) ── (M) `equipos` (resuelta mediante `detalle_prestamo`)**: Un mismo vale de préstamo puede incluir varios equipos simultáneamente, y un equipo puede ser prestado en distintas ocasiones a lo largo del tiempo.

---

## Ejecución del Script SQL

1. Abrir **HeidiSQL** o la consola de **MariaDB**.
2. Cargar y ejecutar el archivo `control_prestamos_db.sql`.
3. Verificar que se haya creado la base de datos `control_prestamos_db` con sus 5 tablas bajo el motor `InnoDB`.
1. Abrir **HeidiSQL** o la consola de **MariaDB**.
2. Cargar y ejecutar el archivo `control_prestamos_db.sql`.
3. Verificar que se haya creado la base de datos `control_prestamos_db` con sus 5 tablas bajo el motor `InnoDB`.
