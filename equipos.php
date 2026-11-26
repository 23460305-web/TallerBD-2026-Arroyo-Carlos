<?php
require_once 'includes/auth.php';
require_once 'config/conexion.php';

$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario    = $_SESSION['rol'] ?? 'solicitante';
$puedeEditar   = in_array(strtolower($rolUsuario), ['administrador', 'encargado']);

$mensaje = '';
$tipoAlerta = '';

// Detectar columnas de `categorias` para soportar nombre/nombre_categoria y descripcion
$colsCat = $pdo->query("SHOW COLUMNS FROM categorias")->fetchAll(PDO::FETCH_COLUMN);
$colNombreCat = in_array('nombre_categoria', $colsCat) ? 'nombre_categoria' : (in_array('nombre', $colsCat) ? 'nombre' : $colsCat[1]);
$colDescCat   = in_array('descripcion', $colsCat) ? 'descripcion' : null;

// 1. Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeEditar) {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $sql = "INSERT INTO equipos (id_categoria, codigo_inventario, nombre, marca, modelo, numero_serie, estado, observaciones) 
                    VALUES (:id_categoria, :codigo_inventario, :nombre, :marca, :modelo, :numero_serie, :estado, :observaciones)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_categoria'      => (int) $_POST['id_categoria'],
                ':codigo_inventario' => trim($_POST['codigo_inventario']),
                ':nombre'            => trim($_POST['nombre']),
                ':marca'             => trim($_POST['marca']),
                ':modelo'            => trim($_POST['modelo']),
                ':numero_serie'      => trim($_POST['numero_serie']),
                ':estado'            => $_POST['estado'] ?? 'disponible',
                ':observaciones'     => trim($_POST['observaciones']) ?: null
            ]);
            $mensaje = 'Equipo registrado correctamente en el inventario.';
            $tipoAlerta = 'exito';

        } elseif ($accion === 'editar') {
            $sql = "UPDATE equipos 
                    SET id_categoria = :id_categoria,
                        codigo_inventario = :codigo_inventario,
                        nombre = :nombre,
                        marca = :marca,
                        modelo = :modelo,
                        numero_serie = :numero_serie,
                        estado = :estado,
                        observaciones = :observaciones
                    WHERE id_equipo = :id_equipo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_categoria'      => (int) $_POST['id_categoria'],
                ':codigo_inventario' => trim($_POST['codigo_inventario']),
                ':nombre'            => trim($_POST['nombre']),
                ':marca'             => trim($_POST['marca']),
                ':modelo'            => trim($_POST['modelo']),
                ':numero_serie'      => trim($_POST['numero_serie']),
                ':estado'            => $_POST['estado'],
                ':observaciones'     => trim($_POST['observaciones']) ?: null,
                ':id_equipo'         => (int) $_POST['id_equipo']
            ]);
            $mensaje = 'Datos del equipo actualizados correctamente.';
            $tipoAlerta = 'exito';

        } elseif ($accion === 'cambiar_estado') {
            $stmt = $pdo->prepare("UPDATE equipos SET estado = :estado WHERE id_equipo = :id_equipo");
            $stmt->execute([
                ':estado'    => $_POST['nuevo_estado'],
                ':id_equipo' => (int) $_POST['id_equipo']
            ]);
            $mensaje = 'Estado operativo del equipo actualizado.';
            $tipoAlerta = 'exito';

        } elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM equipos WHERE id_equipo = :id_equipo");
            $stmt->execute([':id_equipo' => (int) $_POST['id_equipo']]);
            $mensaje = 'Equipo eliminado del inventario.';
            $tipoAlerta = 'alerta';

        } elseif ($accion === 'crear_categoria') {
            $nombreCat = trim($_POST['nombre']);
            $descCat   = trim($_POST['descripcion'] ?? '');
            if ($colDescCat) {
                $stmtCat = $pdo->prepare("INSERT INTO categorias ($colNombreCat, $colDescCat) VALUES (:nom, :desc)");
                $stmtCat->execute([':nom' => $nombreCat, ':desc' => $descCat ?: null]);
            } else {
                $stmtCat = $pdo->prepare("INSERT INTO categorias ($colNombreCat) VALUES (:nom)");
                $stmtCat->execute([':nom' => $nombreCat]);
            }
            $mensaje = "Categoría '$nombreCat' agregada correctamente.";
            $tipoAlerta = 'exito';
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $mensaje = 'Error: El código de inventario, número de serie o categoría ya existe, o el equipo tiene historial ligado.';
        } else {
            $mensaje = 'Error en la base de datos: ' . $e->getMessage();
        }
        $tipoAlerta = 'peligro';
    }
}

// 2. Obtener categorías para el selector
$categorias = $pdo->query("SELECT id_categoria, $colNombreCat AS nombre_cat FROM categorias ORDER BY id_categoria ASC")->fetchAll(PDO::FETCH_ASSOC);

// 3. Consultar listado general de equipos
$sqlEquipos = "SELECT e.*, c.$colNombreCat AS categoria 
               FROM equipos e 
               LEFT JOIN categorias c ON e.id_categoria = c.id_categoria 
               ORDER BY e.id_equipo DESC";
$equipos = $pdo->query($sqlEquipos)->fetchAll(PDO::FETCH_ASSOC);

// Si se solicitó editar un equipo por GET
$equipoEditar = null;
if (isset($_GET['editar']) && $puedeEditar) {
    $stmtEd = $pdo->prepare("SELECT * FROM equipos WHERE id_equipo = :id");
    $stmtEd->execute([':id' => (int) $_GET['editar']]);
    $equipoEditar = $stmtEd->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipos y Categorías | Soporte TI - Control de Préstamos</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --naranja-brand: #FC4C02;
            --naranja-hover: #e04400;
            --negro-base: #09090b;
            --panel-oscuro: #141417;
            --panel-borde: #27272a;
            --input-bg: #0d0d10;
            --texto-claro: #f4f4f5;
            --texto-aten: #a1a1aa;
        }

        body.pagina-equipos-dark {
            background-color: var(--negro-base);
            color: var(--texto-claro);
        }

        .pagina-equipos-dark .navbar {
            background-color: #050507;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: none;
            padding: 0.9rem 2rem;
        }

        .pagina-equipos-dark .navbar-brand {
            color: #ffffff;
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: 0.6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
        }

        .pagina-equipos-dark .nav-links a {
            color: #e4e4e7;
            font-weight: 500;
            border-radius: 4px;
        }

        .pagina-equipos-dark .nav-links a:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .pagina-equipos-dark .nav-links a.activo {
            background-color: transparent;
            color: var(--naranja-brand);
            font-weight: 700;
            border-bottom: 2px solid var(--naranja-brand);
            border-radius: 0;
        }

        .nav-util-links {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nav-util-links a {
            color: #d4d4d8;
            text-decoration: none;
            transition: color 0.2s;
        }

        .nav-util-links a:hover {
            color: var(--naranja-brand);
        }

        .hero-equipos-dark {
            position: relative;
            overflow: hidden;
            text-align: center;
            padding: 3.5rem 1.5rem 4rem;
            background:
                radial-gradient(circle at 50% 85%, rgba(252, 76, 2, 0.28) 0%, rgba(124, 29, 8, 0.22) 38%, rgba(9, 9, 11, 0.98) 75%),
                #09090b;
            border-bottom: 1px solid var(--panel-borde);
        }

        .hero-watermark {
            position: absolute;
            right: 4%;
            bottom: -25px;
            opacity: 0.06;
            pointer-events: none;
        }

        .hero-eyebrow {
            font-size: 0.95rem;
            font-weight: 600;
            color: #e4e4e7;
            margin-bottom: 1.4rem;
            letter-spacing: 0.3px;
        }

        .hero-eyebrow span {
            font-weight: 900;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .hero-badge-escudo {
            width: 58px;
            height: 64px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(252, 76, 2, 0.12);
            border: 2px solid var(--naranja-brand);
            border-radius: 10px 10px 28px 28px;
            box-shadow: 0 0 28px rgba(252, 76, 2, 0.35);
        }

        .hero-equipos-dark h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            max-width: 680px;
            margin: 0 auto 1rem;
        }

        .hero-equipos-dark p {
            color: #d4d4d8;
            font-size: 1.05rem;
            max-width: 600px;
            margin: 0 auto 1.8rem;
        }

        .btn-hero-naranja {
            background-color: var(--naranja-brand);
            color: #ffffff;
            padding: 0.8rem 1.6rem;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-hero-naranja:hover {
            background-color: var(--naranja-hover);
            transform: translateY(-1px);
        }

        .pagina-equipos-dark .contenedor {
            margin-top: 2rem;
        }

        .pagina-equipos-dark .panel {
            background-color: var(--panel-oscuro);
            border: 1px solid var(--panel-borde);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45);
        }

        .pagina-equipos-dark .panel h2 {
            color: #ffffff;
            border-bottom-color: var(--panel-borde);
        }

        .pagina-equipos-dark label {
            color: #e4e4e7;
        }

        .pagina-equipos-dark input,
        .pagina-equipos-dark select,
        .pagina-equipos-dark textarea {
            background-color: var(--input-bg);
            border: 1px solid #3f3f46;
            color: #ffffff;
        }

        .pagina-equipos-dark input::placeholder,
        .pagina-equipos-dark textarea::placeholder {
            color: #71717a;
        }

        .pagina-equipos-dark input:focus,
        .pagina-equipos-dark select:focus,
        .pagina-equipos-dark textarea:focus {
            border-color: var(--naranja-brand);
        }

        .pagina-equipos-dark .btn-primario {
            background-color: var(--naranja-brand);
            color: #ffffff;
        }

        .pagina-equipos-dark .btn-primario:hover {
            background-color: var(--naranja-hover);
        }

        .pagina-equipos-dark .btn-secundario {
            background-color: #27272a;
            color: #f4f4f5;
        }

        .pagina-equipos-dark .btn-secundario:hover {
            background-color: #3f3f46;
        }

        .pagina-equipos-dark th {
            background-color: #1c1c21;
            color: var(--naranja-brand);
            border-bottom: 1px solid #3f3f46;
        }

        .pagina-equipos-dark td {
            border-bottom: 1px solid #27272a;
            color: #e4e4e7;
        }

        .pagina-equipos-dark tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .alerta-msg {
            padding: 0.9rem 1.2rem;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .alerta-exito { background: rgba(22, 163, 74, 0.16); border-color: #16a34a; color: #86efac; }
        .alerta-alerta { background: rgba(234, 179, 8, 0.16); border-color: #eab308; color: #fde047; }
        .alerta-peligro { background: rgba(220, 38, 38, 0.16); border-color: #dc2626; color: #fca5a5; }

        .pagina-equipos-dark footer {
            border-top: 1px solid var(--panel-borde);
            color: var(--texto-aten);
            margin-top: 2rem;
        }

        @media (max-width: 860px) {
            .nav-util-links { display: none; }
            .hero-equipos-dark h1 { font-size: 1.9rem; }
        }
    </style>
</head>
<body class="pagina-equipos-dark">
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2L4 14H11L10 22L20 10H13L12 2Z" fill="#FC4C02"/>
            </svg>
            Soporte TI | Préstamos
        </a>

        <button class="nav-toggle" id="btnMenu" aria-label="Abrir menú">☰ Menú</button>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php">Inicio</a></li>
            <li><a href="equipos.php" class="activo">Equipos y Categorías</a></li>
            <li><a href="solicitantes.php">Usuarios / Solicitantes</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
            <li><a href="consultas.php">Consultas y Reportes</a></li>
        </ul>

        <div class="nav-util-links">
            <a href="#inventario-general" style="color:#FC4C02;">Ver Inventario</a>
            <a href="logout.php">Cerrar sesión</a>
        </div>
    </nav>

    <!-- Encabezado Hero oscuro con emblema naranja -->
    <header class="hero-equipos-dark">
        <svg class="hero-watermark" width="260" height="280" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 2L3 6V12C3 17.55 6.84 22.74 12 24C17.16 22.74 21 17.55 21 12V6L12 2Z" stroke="#FC4C02" stroke-width="1.5"/>
            <path d="M12 6L7 14H11.5L10.5 19L17 11H12.5L12 6Z" fill="#FC4C02"/>
        </svg>

        <div class="hero-eyebrow">Inventario y Catálogo · <span>Soporte TI</span></div>

        <div class="hero-badge-escudo" aria-hidden="true">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                <path d="M12 2L4 14H11L10 22L20 10H13L12 2Z" fill="#FC4C02"/>
            </svg>
        </div>

        <h1>Control de Equipos y Categorías</h1>
        <p>Registro individualizado por código de inventario y número de serie, controlando disponibilidad y mantenimiento.</p>
        <a href="#form-registro-equipo" class="btn-hero-naranja">Registrar nuevo equipo</a>
    </header>

    <main class="contenedor">
        <?php if ($mensaje): ?>
            <div class="alerta-msg alerta-<?= $tipoAlerta ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($puedeEditar): ?>
        <section class="panel" id="form-registro-equipo">
            <h2><?= $equipoEditar ? 'Editar Equipo: ' . htmlspecialchars($equipoEditar['codigo_inventario']) : 'Registrar / Editar Equipo Tecnológico' ?></h2>
            <form action="equipos.php" method="POST">
                <input type="hidden" name="accion" value="<?= $equipoEditar ? 'editar' : 'crear' ?>">
                <?php if ($equipoEditar): ?>
                    <input type="hidden" name="id_equipo" value="<?= $equipoEditar['id_equipo'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-grupo">
                        <label for="codigo_inventario">Código de Inventario *</label>
                        <input type="text" id="codigo_inventario" name="codigo_inventario" placeholder="INV-LAP-005" required maxlength="50"
                               value="<?= htmlspecialchars($equipoEditar['codigo_inventario'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="id_categoria">Categoría *</label>
                        <select id="id_categoria" name="id_categoria" required>
                            <option value="">Seleccione categoría...</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id_categoria'] ?>" <?= (($equipoEditar['id_categoria'] ?? '') == $cat['id_categoria']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre_cat']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label for="nombre">Nombre del Equipo *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Laptop Lenovo ThinkPad T14" required maxlength="100"
                               value="<?= htmlspecialchars($equipoEditar['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="marca">Marca *</label>
                        <input type="text" id="marca" name="marca" placeholder="Lenovo" required maxlength="60"
                               value="<?= htmlspecialchars($equipoEditar['marca'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="modelo">Modelo *</label>
                        <input type="text" id="modelo" name="modelo" placeholder="ThinkPad T14 Gen 3" required maxlength="60"
                               value="<?= htmlspecialchars($equipoEditar['modelo'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="numero_serie">Número de Serie *</label>
                        <input type="text" id="numero_serie" name="numero_serie" placeholder="PF3X992A" required maxlength="80"
                               value="<?= htmlspecialchars($equipoEditar['numero_serie'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="estado">Estado Operativo *</label>
                        <select id="estado" name="estado" required>
                            <?php
                            $estados = [
                                'disponible'    => 'Disponible',
                                'prestado'      => 'Prestado',
                                'mantenimiento' => 'Mantenimiento (Fuera de servicio)',
                                'baja'          => 'Baja definitiva'
                            ];
                            $estadoSel = $equipoEditar['estado'] ?? 'disponible';
                            foreach ($estados as $val => $etq):
                            ?>
                                <option value="<?= $val ?>" <?= $estadoSel === $val ? 'selected' : '' ?>><?= $etq ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-grupo completo">
                        <label for="observaciones">Observaciones / Condición física</label>
                        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Incluye cargador original de 65W y maletín."><?= htmlspecialchars($equipoEditar['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario"><?= $equipoEditar ? 'Actualizar Equipo' : 'Guardar Equipo' ?></button>
                    <?php if ($equipoEditar): ?>
                        <a href="equipos.php" class="btn btn-secundario">Cancelar Edición</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-secundario">Limpiar</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Nueva Categoría de Inventario</h2>
            <form action="equipos.php" method="POST">
                <input type="hidden" name="accion" value="crear_categoria">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label for="cat_nombre">Nombre de Categoría *</label>
                        <input type="text" id="cat_nombre" name="nombre" placeholder="Herramientas de Diagnóstico" required>
                    </div>
                    <div class="form-grupo">
                        <label for="cat_desc">Descripción</label>
                        <input type="text" id="cat_desc" name="descripcion" placeholder="Multímetros, testeadores de cableado y cautines">
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Agregar Categoría</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="panel" id="inventario-general">
            <h2>Inventario General de Equipos</h2>
            <div class="barra-filtro">
                <input type="text" placeholder="Buscar por código, nombre, serie o estado..." data-filtro-tabla="tablaEquipos">
            </div>
            <div class="tabla-responsive">
                <table id="tablaEquipos">
                    <thead>
                        <tr>
                            <th>Código Inv.</th>
                            <th>Categoría</th>
                            <th>Equipo</th>
                            <th>Marca / Modelo</th>
                            <th>No. Serie</th>
                            <th>Estado</th>
                            <?php if ($puedeEditar): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($equipos) === 0): ?>
                            <tr>
                                <td colspan="<?= $puedeEditar ? 7 : 6 ?>" style="text-align:center; padding:1.5rem;">
                                    No hay equipos registrados en el inventario.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($equipos as $eq): ?>
                                <?php
                                $claseBadge = match($eq['estado']) {
                                    'disponible'    => 'badge-disponible',
                                    'prestado'      => 'badge-prestado',
                                    'mantenimiento' => 'badge-mantenimiento',
                                    default         => 'badge-atrasado'
                                };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($eq['codigo_inventario']) ?></td>
                                    <td><?= htmlspecialchars($eq['categoria'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($eq['nombre']) ?></td>
                                    <td><?= htmlspecialchars($eq['marca'] . ' / ' . $eq['modelo']) ?></td>
                                    <td><?= htmlspecialchars($eq['numero_serie']) ?></td>
                                    <td><span class="badge <?= $claseBadge ?>"><?= htmlspecialchars($eq['estado']) ?></span></td>
                                    <?php if ($puedeEditar): ?>
                                        <td style="white-space:nowrap;">
                                            <a href="equipos.php?editar=<?= $eq['id_equipo'] ?>#form-registro-equipo" class="btn btn-secundario btn-sm">Editar</a>
                                            <?php if ($eq['estado'] === 'disponible'): ?>
                                                <form action="equipos.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="mantenimiento">
                                                    <button type="submit" class="btn btn-alerta btn-sm">Mantenimiento</button>
                                                </form>
                                            <?php elseif ($eq['estado'] === 'mantenimiento'): ?>
                                                <form action="equipos.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="disponible">
                                                    <button type="submit" class="btn btn-exito btn-sm">Habilitar</button>
                                                </form>
                                            <?php endif; ?>
                                            <form action="equipos.php" method="POST" style="display:inline;" onsubmit="return confirm('¿Dar de baja definitiva / eliminar este equipo?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id_equipo" value="<?= $eq['id_equipo'] ?>">
                                                <button type="submit" class="btn btn-peligro btn-sm">Baja</button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <footer>Taller de Base de Datos — Proyecto Integrador</footer>
    <script src="js/app.js"></script>
</body>
</html>