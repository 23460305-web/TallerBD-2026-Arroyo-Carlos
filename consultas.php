<?php
require_once 'includes/auth.php';
require_once 'config/conexion.php';

$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario    = $_SESSION['rol'] ?? 'solicitante';

// Detectar nombres exactos de columnas en `prestamos`, `detalle_prestamo` y `categorias`
$colsPrestamos = $pdo->query("SHOW COLUMNS FROM prestamos")->fetchAll(PDO::FETCH_COLUMN);
$colFechaEst   = $colsPrestamos[4] ?? 'fecha_devolucion_estimada';
$colFechaReal  = $colsPrestamos[5] ?? 'fecha_devolucion_real';

$colsDetalle   = $pdo->query("SHOW COLUMNS FROM detalle_prestamo")->fetchAll(PDO::FETCH_COLUMN);
$colCondEnt    = $colsDetalle[3] ?? 'condicion_entrega';
$colCondDev    = $colsDetalle[4] ?? 'condicion_devolucion';

$colsCat       = $pdo->query("SHOW COLUMNS FROM categorias")->fetchAll(PDO::FETCH_COLUMN);
$colNombreCat  = in_array('nombre_categoria', $colsCat) ? 'nombre_categoria' : (in_array('nombre', $colsCat) ? 'nombre' : $colsCat[1]);

// Actualizar automáticamente préstamos vencidos a 'atrasado'
try {
    $pdo->exec("UPDATE prestamos SET estado = 'atrasado' WHERE estado = 'activo' AND $colFechaEst < NOW()");
} catch (PDOException $e) {
}

// Parámetros del filtro superior
$tipoReporte = $_GET['tipo_reporte'] ?? '';
$criterio    = trim($_GET['q'] ?? '');
$resultadosFiltro = [];
$tituloResultado  = '';
$columnasTabla    = [];

if ($tipoReporte !== '') {
    $likeQ = "%$criterio%";

    if ($tipoReporte === 'general' || $tipoReporte === 'disponibles') {
        $tituloResultado = $tipoReporte === 'disponibles'
            ? 'Resultado: 2. Equipos Disponibles para Préstamo'
            : 'Resultado: 1. Listado General de Equipos y Situación Actual';
        $columnasTabla = ['Código Inv.', 'Categoría', 'Equipo', 'Marca / Modelo', 'No. Serie', 'Estado'];

        $where = [];
        $params = [];
        if ($tipoReporte === 'disponibles') {
            $where[] = "e.estado = 'disponible'";
        }
        if ($criterio !== '') {
            $where[] = "(e.codigo_inventario LIKE :q OR e.nombre LIKE :q OR e.numero_serie LIKE :q)";
            $params[':q'] = $likeQ;
        }
        $sqlW = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT e.codigo_inventario, c.$colNombreCat AS categoria, e.nombre, CONCAT(e.marca, ' / ', e.modelo) AS marca_mod, e.numero_serie, e.estado
            FROM equipos e
            LEFT JOIN categorias c ON e.id_categoria = c.id_categoria
            $sqlW
            ORDER BY e.codigo_inventario ASC
        ");
        $stmt->execute($params);
        $resultadosFiltro = $stmt->fetchAll(PDO::FETCH_NUM);

    } elseif ($tipoReporte === 'activos' || $tipoReporte === 'vencidos' || $tipoReporte === 'historial') {
        $tituloResultado = match($tipoReporte) {
            'activos'  => 'Resultado: 3. Préstamos Activos y Resguardante',
            'vencidos' => 'Resultado: 4. Préstamos con Fecha de Devolución Vencida',
            default    => 'Resultado: 5. Historial por Persona o Equipo'
        };
        $columnasTabla = ['Folio', 'Equipo', 'Solicitante', 'Fecha Préstamo', 'Fecha Límite', 'Estado'];

        $where = [];
        $params = [];
        if ($tipoReporte === 'activos') {
            $where[] = "p.estado IN ('activo', 'atrasado')";
        } elseif ($tipoReporte === 'vencidos') {
            $where[] = "p.estado = 'atrasado'";
        }
        if ($criterio !== '') {
            $where[] = "(u.numero_control LIKE :q OR CONCAT(u.nombre, ' ', u.apellidos) LIKE :q OR e.codigo_inventario LIKE :q OR e.nombre LIKE :q)";
            $params[':q'] = $likeQ;
        }
        $sqlW = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("
            SELECT 
                CONCAT('#', p.id_prestamo),
                CONCAT(e.codigo_inventario, ' — ', e.nombre),
                CONCAT(u.nombre, ' ', u.apellidos, ' (', u.numero_control, ')'),
                DATE_FORMAT(p.fecha_prestamo, '%Y-%m-%d %H:%i'),
                DATE_FORMAT(p.$colFechaEst, '%Y-%m-%d %H:%i'),
                p.estado
            FROM prestamos p
            INNER JOIN usuarios u ON p.id_solicitante = u.id_usuario
            LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
            LEFT JOIN equipos e ON dp.id_equipo = e.id_equipo
            $sqlW
            ORDER BY p.id_prestamo DESC
        ");
        $stmt->execute($params);
        $resultadosFiltro = $stmt->fetchAll(PDO::FETCH_NUM);
    }
}

// Consulta 4 fija: Alertas de Préstamos Vencidos
$sqlVencidos = "SELECT 
                    p.id_prestamo,
                    p.$colFechaEst AS fecha_estimada,
                    p.estado,
                    u.numero_control,
                    u.correo,
                    u.telefono,
                    CONCAT(u.nombre, ' ', u.apellidos) AS solicitante,
                    GROUP_CONCAT(CONCAT(e.codigo_inventario, ' — ', e.nombre) SEPARATOR ', ') AS equipo_desc
                FROM prestamos p
                INNER JOIN usuarios u ON p.id_solicitante = u.id_usuario
                LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
                LEFT JOIN equipos e ON dp.id_equipo = e.id_equipo
                WHERE p.estado = 'atrasado'
                GROUP BY p.id_prestamo
                ORDER BY p.$colFechaEst ASC";
$prestamosVencidos = $pdo->query($sqlVencidos)->fetchAll(PDO::FETCH_ASSOC);
$totalVencidos = count($prestamosVencidos);

// Consulta 5 fija: Historial Completo de Movimientos
$sqlHistorial = "SELECT 
                    p.id_prestamo,
                    p.fecha_prestamo,
                    p.$colFechaReal AS fecha_real,
                    p.estado,
                    u.numero_control,
                    CONCAT(u.nombre, ' ', u.apellidos) AS solicitante,
                    e.codigo_inventario,
                    e.nombre AS equipo,
                    dp.$colCondEnt AS cond_ent,
                    dp.$colCondDev AS cond_dev
                 FROM prestamos p
                 INNER JOIN usuarios u ON p.id_solicitante = u.id_usuario
                 LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
                 LEFT JOIN equipos e ON dp.id_equipo = e.id_equipo
                 ORDER BY p.id_prestamo DESC";
$historialMovimientos = $pdo->query($sqlHistorial)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultas y Reportes | Soporte TI - Control de Préstamos</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --naranja-brand: #FC4C02;
            --naranja-hover: #e04400;
            --negro-nav: #050507;
            --negro-texto: #18181b;
            --gris-texto: #52525b;
            --fondo-claro: #f7f7fa;
        }

        body.pagina-consultas {
            background-color: var(--fondo-claro);
            color: var(--negro-texto);
        }

        .pagina-consultas .navbar {
            background-color: var(--negro-nav);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: none;
            padding: 0.85rem 2rem;
        }

        .pagina-consultas .navbar-brand {
            color: #ffffff;
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: 0.6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
        }

        .pagina-consultas .nav-links a {
            color: #e4e4e7;
            font-weight: 500;
            border-radius: 0;
            padding: 0.45rem 0.75rem;
        }

        .pagina-consultas .nav-links a:hover {
            background-color: transparent;
            color: #ffffff;
        }

        .pagina-consultas .nav-links a.activo {
            background-color: transparent;
            color: #ffffff;
            font-weight: 700;
            border-bottom: 2px solid #ffffff;
        }

        .nav-acciones-der {
            display: flex;
            align-items: center;
            gap: 0.65rem;
        }

        .btn-nav-outline-dark {
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: transparent;
            color: #ffffff;
            padding: 0.48rem 1rem;
            border-radius: 3px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .btn-nav-outline-dark:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }

        .btn-nav-naranja {
            background: var(--naranja-brand);
            border: 1px solid var(--naranja-brand);
            color: #ffffff;
            padding: 0.48rem 1rem;
            border-radius: 3px;
            font-size: 0.84rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s;
        }

        .btn-nav-naranja:hover {
            background: var(--naranja-hover);
        }

        .hero-consultas {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 45%, rgba(252, 76, 2, 0.25) 0%, rgba(30, 27, 75, 0.25) 38%, rgba(9, 9, 11, 0.98) 76%),
                linear-gradient(125deg, #09090b 0%, #14141c 55%, #18181b 100%);
            color: #ffffff;
            padding: 4rem 2rem;
            border-bottom: 1px solid #27272a;
        }

        .hero-consultas::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px);
            background-size: 22px 22px;
            pointer-events: none;
        }

        .hero-consultas-inner {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.35fr 0.95fr;
            align-items: center;
            gap: 2.5rem;
        }

        .hero-consultas h1 {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin-bottom: 1.1rem;
            max-width: 610px;
        }

        .hero-consultas p {
            font-size: 1.06rem;
            color: #e4e4e7;
            margin-bottom: 1.85rem;
            max-width: 540px;
            font-weight: 500;
        }

        .hero-botones {
            display: flex;
            gap: 0.85rem;
            flex-wrap: wrap;
        }

        .btn-hero-solid {
            display: inline-block;
            background-color: var(--naranja-brand);
            color: #ffffff;
            padding: 0.85rem 2.2rem;
            border-radius: 3px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-hero-solid:hover {
            background-color: var(--naranja-hover);
            transform: translateY(-1px);
        }

        .btn-hero-ghost {
            display: inline-block;
            border: 1px solid rgba(255, 255, 255, 0.45);
            color: #ffffff;
            padding: 0.85rem 1.6rem;
            border-radius: 3px;
            font-size: 0.95rem;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, border-color 0.2s;
        }

        .btn-hero-ghost:hover {
            border-color: #ffffff;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-analitica-derecha {
            background: rgba(24, 24, 27, 0.85);
            border: 1px solid #27272a;
            border-radius: 8px;
            padding: 1.4rem;
            backdrop-filter: blur(6px);
        }

        .metricas-analitica {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.85rem;
            border-bottom: 1px solid #27272a;
        }

        .metrica-box .etiqueta {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #a1a1aa;
        }

        .metrica-box .valor {
            display: block;
            font-size: 1.15rem;
            font-weight: 800;
            color: #ffffff;
            margin-top: 0.15rem;
        }

        .grafica-analitica {
            width: 100%;
            height: 95px;
            filter: drop-shadow(0 4px 10px rgba(252, 76, 2, 0.4));
        }

        .pagina-consultas .contenedor {
            margin-top: 2rem;
        }

        .pagina-consultas .panel {
            border: 1px solid #e4e4e7;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .pagina-consultas .panel h2 {
            color: var(--negro-texto);
        }

        .pagina-consultas input:focus,
        .pagina-consultas select:focus {
            border-color: var(--naranja-brand);
        }

        .pagina-consultas .btn-primario {
            background-color: var(--naranja-brand);
            color: #ffffff;
            border-radius: 3px;
        }

        .pagina-consultas .btn-primario:hover {
            background-color: var(--naranja-hover);
        }

        .pagina-consultas th {
            color: var(--negro-texto);
            border-bottom: 2px solid #e4e4e7;
        }

        @media (max-width: 920px) {
            .nav-acciones-der { display: none; }
            .hero-consultas-inner { grid-template-columns: 1fr; }
            .hero-consultas h1 { font-size: 2.2rem; }
            .metricas-analitica { flex-wrap: wrap; }
        }
    </style>
</head>
<body class="pagina-consultas">
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
            <li><a href="equipos.php">Equipos y Categorías</a></li>
            <li><a href="solicitantes.php">Usuarios / Solicitantes</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
            <li><a href="consultas.php" class="activo">Consultas y Reportes</a></li>
        </ul>

        <div class="nav-acciones-der">
            <a href="#alertas-vencidos" class="btn-nav-naranja">Ver vencidos</a>
            <a href="logout.php" class="btn-nav-outline-dark">Cerrar sesión</a>
        </div>
    </nav>

    <!-- Hero analítico oscuro -->
    <header class="hero-consultas">
        <div class="hero-consultas-inner">
            <div>
                <h1>Consultas y Seguimiento Operativo</h1>
                <p>Reportes mediante consultas JOIN con nombres legibles de equipos, categorías y usuarios.</p>
                <div class="hero-botones">
                    <a href="#filtro-reportes" class="btn-hero-solid">Ejecutar consulta</a>
                    <a href="#historial-movimientos" class="btn-hero-ghost">Historial completo</a>
                </div>
            </div>

            <div class="hero-analitica-derecha" aria-hidden="true">
                <div class="metricas-analitica">
                    <div class="metrica-box">
                        <span class="etiqueta">Consultas</span>
                        <span class="valor">5 REPORTES</span>
                    </div>
                    <div class="metrica-box">
                        <span class="etiqueta">Relaciones</span>
                        <span class="valor">JOIN SQL</span>
                    </div>
                    <div class="metrica-box">
                        <span class="etiqueta">Vencidos</span>
                        <span class="valor" style="color:#FC4C02;"><?= $totalVencidos ?> ALERTA<?= $totalVencidos === 1 ? '' : 'S' ?></span>
                    </div>
                </div>

                <svg class="grafica-analitica" viewBox="0 0 320 95" fill="none">
                    <defs>
                        <linearGradient id="areaNaranja" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#FC4C02" stop-opacity="0.45"/>
                            <stop offset="100%" stop-color="#FC4C02" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="M5 78 L55 62 L105 68 L155 38 L205 46 L260 18 L315 26 L315 90 L5 90 Z" fill="url(#areaNaranja)"/>
                    <path d="M5 78 L55 62 L105 68 L155 38 L205 46 L260 18 L315 26"
                          stroke="#FC4C02" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="260" cy="18" r="5" fill="#FC4C02" stroke="#ffffff" stroke-width="2"/>
                </svg>
            </div>
        </div>
    </header>

    <main class="contenedor">
        <section class="panel" id="filtro-reportes">
            <h2>Filtrar Reporte Específico</h2>
            <form action="consultas.php#filtro-reportes" method="GET">
                <div class="form-grid">
                    <div class="form-grupo">
                        <label for="tipo_reporte">Tipo de Consulta Requerida</label>
                        <select id="tipo_reporte" name="tipo_reporte">
                            <option value="general" <?= $tipoReporte === 'general' ? 'selected' : '' ?>>1. Listado general de equipos y situación actual</option>
                            <option value="disponibles" <?= $tipoReporte === 'disponibles' ? 'selected' : '' ?>>2. Equipos disponibles para préstamo</option>
                            <option value="activos" <?= $tipoReporte === 'activos' ? 'selected' : '' ?>>3. Préstamos activos y resguardante</option>
                            <option value="vencidos" <?= $tipoReporte === 'vencidos' ? 'selected' : '' ?>>4. Préstamos con fecha de devolución vencida</option>
                            <option value="historial" <?= $tipoReporte === 'historial' ? 'selected' : '' ?>>5. Historial por persona o equipo</option>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label for="criterio_busqueda">Buscar por No. Control o Código de Inventario</label>
                        <input type="text" id="criterio_busqueda" name="q" placeholder="Ej. 20460115 o INV-LAP-001"
                               value="<?= htmlspecialchars($criterio) ?>">
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario">Ejecutar Consulta</button>
                    <?php if ($tipoReporte !== ''): ?>
                        <a href="consultas.php" class="btn btn-secundario">Limpiar Filtro</a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($tipoReporte !== ''): ?>
                <div style="margin-top: 1.5rem; border-top: 1px solid #e4e4e7; padding-top: 1.2rem;">
                    <h3 style="margin-bottom: 0.75rem; font-size: 1.05rem;"><?= htmlspecialchars($tituloResultado) ?> (<?= count($resultadosFiltro) ?> registros)</h3>
                    <div class="tabla-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <?php foreach ($columnasTabla as $col): ?>
                                        <th><?= htmlspecialchars($col) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($resultadosFiltro) === 0): ?>
                                    <tr>
                                        <td colspan="<?= count($columnasTabla) ?>" style="text-align:center; padding:1.2rem;">
                                            No se encontraron registros para los criterios seleccionados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($resultadosFiltro as $fila): ?>
                                        <tr>
                                            <?php foreach ($fila as $idx => $valor): ?>
                                                <td>
                                                    <?php if ($idx === count($fila) - 1): ?>
                                                        <?php
                                                        $badgeCl = match($valor) {
                                                            'disponible', 'devuelto' => 'badge-disponible',
                                                            'prestado', 'activo'     => 'badge-activo',
                                                            'mantenimiento'          => 'badge-mantenimiento',
                                                            default                  => 'badge-atrasado'
                                                        };
                                                        ?>
                                                        <span class="badge <?= $badgeCl ?>"><?= htmlspecialchars($valor) ?></span>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars((string)$valor) ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel" id="alertas-vencidos">
            <h2>Alertas: Préstamos Vencidos (Consulta 4)</h2>
            <div class="tabla-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Equipo</th>
                            <th>Solicitante</th>
                            <th>Correo / Teléfono</th>
                            <th>Fecha Estimada</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($prestamosVencidos) === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:1.2rem;">
                                    No hay préstamos vencidos en este momento.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prestamosVencidos as $pv): ?>
                                <tr>
                                    <td>#<?= $pv['id_prestamo'] ?></td>
                                    <td><?= htmlspecialchars($pv['equipo_desc'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($pv['solicitante']) ?> (<?= htmlspecialchars($pv['numero_control']) ?>)</td>
                                    <td><?= htmlspecialchars($pv['correo']) ?> / <?= htmlspecialchars($pv['telefono'] ?: 'Sin tel.') ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($pv['fecha_estimada'])) ?></td>
                                    <td><span class="badge badge-atrasado"><?= htmlspecialchars($pv['estado']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel" id="historial-movimientos">
            <h2>Historial Completo de Movimientos (Consulta 5)</h2>
            <div class="barra-filtro">
                <input type="text" placeholder="Filtrar historial por equipo, solicitante o condición..." data-filtro-tabla="tablaHistorial">
            </div>
            <div class="tabla-responsive">
                <table id="tablaHistorial">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Código Inv.</th>
                            <th>Equipo</th>
                            <th>Solicitante</th>
                            <th>Fecha Préstamo</th>
                            <th>Fecha Devolución</th>
                            <th>Condición Entrega / Devolución</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($historialMovimientos) === 0): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:1.5rem;">
                                    No hay movimientos históricos registrados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historialMovimientos as $hm): ?>
                                <?php
                                $badgeHist = match($hm['estado']) {
                                    'devuelto' => 'badge-devuelto',
                                    'atrasado' => 'badge-atrasado',
                                    default    => 'badge-activo'
                                };
                                ?>
                                <tr>
                                    <td>#<?= $hm['id_prestamo'] ?></td>
                                    <td><?= htmlspecialchars($hm['codigo_inventario'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($hm['equipo'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($hm['solicitante']) ?> (<?= htmlspecialchars($hm['numero_control']) ?>)</td>
                                    <td><?= date('Y-m-d H:i', strtotime($hm['fecha_prestamo'])) ?></td>
                                    <td><?= !empty($hm['fecha_real']) ? date('Y-m-d H:i', strtotime($hm['fecha_real'])) : 'Pendiente' ?></td>
                                    <td><?= htmlspecialchars(($hm['cond_ent'] ?: 'Buen estado') . ' / ' . ($hm['cond_dev'] ?: '—')) ?></td>
                                    <td><span class="badge <?= $badgeHist ?>"><?= htmlspecialchars($hm['estado']) ?></span></td>
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