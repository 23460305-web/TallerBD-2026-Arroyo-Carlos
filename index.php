<?php
require_once 'includes/auth.php';
require_once 'config/conexion.php';

$nombreUsuario = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario    = $_SESSION['rol'] ?? 'solicitante';

// Detectar nombres exactos de columnas en `prestamos`
$colsPrestamos = $pdo->query("SHOW COLUMNS FROM prestamos")->fetchAll(PDO::FETCH_COLUMN);
$colFechaEst   = $colsPrestamos[4] ?? 'fecha_devolucion_estimada';

// 1. Consultar KPIs reales de la base de datos
$totalEquipos     = (int) $pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn();
$disponibles      = (int) $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'disponible'")->fetchColumn();
$enPrestamo       = (int) $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'prestado'")->fetchColumn();
$enMantenimiento  = (int) $pdo->query("SELECT COUNT(*) FROM equipos WHERE estado = 'mantenimiento'")->fetchColumn();
$vencidos         = (int) $pdo->query("SELECT COUNT(*) FROM prestamos WHERE estado = 'atrasado'")->fetchColumn();

// 2. Consultar préstamos activos o atrasados recientes para la tabla y la tarjeta derecha
$sqlRecientes = "SELECT 
                    p.id_prestamo,
                    p.fecha_prestamo,
                    p.$colFechaEst AS fecha_estimada,
                    p.estado,
                    u.numero_control,
                    CONCAT(u.nombre, ' ', u.apellidos) AS solicitante,
                    e.codigo_inventario,
                    e.nombre AS equipo,
                    dp.condicion_entrega
                 FROM prestamos p
                 INNER JOIN usuarios u ON p.id_solicitante = u.id_usuario
                 LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
                 LEFT JOIN equipos e ON dp.id_equipo = e.id_equipo
                 WHERE p.estado IN ('activo', 'atrasado')
                 ORDER BY p.id_prestamo DESC
                 LIMIT 5";
$prestamosRecientes = $pdo->query($sqlRecientes)->fetchAll(PDO::FETCH_ASSOC);
$destacado = $prestamosRecientes[0] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | Soporte TI - Control de Préstamos</title>
    <link rel="stylesheet" href="css/estilos.css">
    <style>
        :root {
            --naranja-brand: #FC4C02;
            --naranja-hover: #e04400;
            --negro-texto: #242428;
            --gris-suave: #606065;
            --borde-claro: #e5e7eb;
        }

        body.pagina-inicio {
            background-color: #ffffff;
            color: var(--negro-texto);
        }

        .pagina-inicio .navbar {
            background-color: #ffffff;
            color: var(--negro-texto);
            border-bottom: 1px solid var(--borde-claro);
            box-shadow: none;
            padding: 0.85rem 2rem;
        }

        .pagina-inicio .navbar-brand {
            color: var(--naranja-brand);
            font-weight: 900;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
        }

        .pagina-inicio .nav-toggle {
            color: var(--negro-texto);
            border-color: #cbd5e1;
        }

        .pagina-inicio .nav-links a {
            color: var(--negro-texto);
            font-weight: 500;
            border-radius: 4px;
        }

        .pagina-inicio .nav-links a:hover {
            background-color: transparent;
            color: var(--naranja-brand);
        }

        .pagina-inicio .nav-links a.activo {
            background-color: transparent;
            color: var(--naranja-brand);
            font-weight: 700;
            border-bottom: 2px solid var(--naranja-brand);
            border-radius: 0;
        }

        .btn-nav-outline {
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: var(--negro-texto);
            padding: 0.45rem 1rem;
            border-radius: 4px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-nav-outline:hover {
            border-color: var(--naranja-brand);
            color: var(--naranja-brand);
        }

        .hero-strava {
            max-width: 1320px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1.15fr 1fr;
            min-height: 460px;
            border-bottom: 1px solid var(--borde-claro);
        }

        .hero-visual-izq {
            background: linear-gradient(160deg, #1e293b 0%, #0f172a 100%);
            color: #ffffff;
            padding: 2.2rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .hero-visual-izq::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            background: radial-gradient(circle, rgba(252,76,2,0.32) 0%, transparent 70%);
            bottom: -40px;
            right: -40px;
        }

        .tarjeta-flotante-oscura {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(6px);
            border-radius: 10px;
            padding: 1.1rem;
            position: relative;
            z-index: 1;
        }

        .hero-centro {
            padding: 2.8rem 2.2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            background: #ffffff;
        }

        .hero-centro h1 {
            font-size: 2.15rem;
            font-weight: 800;
            color: var(--negro-texto);
            line-height: 1.18;
            margin-bottom: 0.85rem;
        }

        .hero-centro p.subtitulo {
            color: var(--gris-suave);
            font-size: 0.96rem;
            max-width: 410px;
            margin-bottom: 1.5rem;
        }

        .btn-naranja-full {
            display: inline-block;
            width: 100%;
            max-width: 340px;
            background-color: var(--naranja-brand);
            color: #ffffff;
            border: none;
            padding: 0.78rem 1rem;
            border-radius: 4px;
            font-size: 0.96rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 0.25rem;
        }

        .btn-naranja-full:hover {
            background-color: var(--naranja-hover);
        }

        .nota-roles {
            font-size: 0.85rem;
            color: var(--gris-suave);
            margin-top: 1rem;
            max-width: 380px;
        }

        .nota-roles span {
            color: var(--naranja-brand);
            font-weight: 700;
            text-transform: uppercase;
        }

        .hero-visual-der {
            background: linear-gradient(160deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 2.2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .mockup-dispositivo {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.3rem;
            width: 100%;
            max-width: 280px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
            border: 1px solid #e2e8f0;
        }

        .mockup-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--gris-suave);
            padding-bottom: 0.65rem;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 0.85rem;
        }

        .mockup-metrica {
            background: #18181b;
            color: #ffffff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 0.85rem;
        }

        .mockup-metrica .tiempo {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--naranja-brand);
        }

        .seccion-operativa {
            background-color: #f7f7fa;
            padding: 2.2rem 1rem;
        }

        .pagina-inicio .kpi-card {
            border-left-color: var(--naranja-brand);
        }

        .pagina-inicio .panel h2,
        .pagina-inicio th {
            color: var(--negro-texto);
        }

        .footer-strava {
            background: #ffffff;
            border-top: 1px solid var(--borde-claro);
            padding: 2.5rem 2rem;
            text-align: left;
        }

        .footer-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr repeat(3, 1fr);
            gap: 1.5rem;
        }

        .footer-grid h4 {
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--negro-texto);
            margin-bottom: 0.75rem;
        }

        .footer-grid ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .footer-grid a {
            text-decoration: none;
            color: var(--gris-suave);
            font-size: 0.88rem;
        }

        .footer-grid a:hover {
            color: var(--naranja-brand);
        }

        @media (max-width: 960px) {
            .hero-strava { grid-template-columns: 1fr; }
            .hero-visual-izq, .hero-visual-der { padding: 1.5rem; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .btn-nav-outline { display: none; }
        }

        @media (max-width: 600px) {
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="pagina-inicio">
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 2L4 14H11L10 22L20 10H13L12 2Z" fill="#FC4C02"/>
            </svg>
            Soporte TI | Préstamos
        </a>

        <button class="nav-toggle" id="btnMenu" aria-label="Abrir menú">☰ Menú</button>

        <ul class="nav-links" id="navLinks">
            <li><a href="index.php" class="activo">Inicio</a></li>
            <li><a href="equipos.php">Equipos y Categorías</a></li>
            <li><a href="solicitantes.php">Usuarios / Solicitantes</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
            <li><a href="consultas.php">Consultas y Reportes</a></li>
        </ul>

        <a href="logout.php" class="btn-nav-outline">Cerrar sesión</a>
    </nav>

    <!-- Sección Principal estilo Hero -->
    <section class="hero-strava">
        <!-- Columna Izquierda -->
        <div class="hero-visual-izq">
            <div>
                <span style="background:#FC4C02; padding:0.25rem 0.6rem; border-radius:4px; font-size:0.75rem; font-weight:700; letter-spacing:0.5px;">INVENTARIO TI</span>
                <h2 style="font-size:1.35rem; margin-top:0.85rem; line-height:1.3;">Trazabilidad total de cada equipo bajo resguardo</h2>
            </div>

            <div class="tarjeta-flotante-oscura">
                <div style="font-size:0.8rem; color:#cbd5e1; margin-bottom:0.35rem;">Disponibilidad inmediata</div>
                <div style="font-size:1.5rem; font-weight:800; color:#ffffff;"><?= $disponibles ?> / <?= $totalEquipos ?> Equipos</div>
                <div style="font-size:0.82rem; color:#94a3b8; margin-top:0.3rem;">Laptops, proyectores, kits de red y diagnóstico listos para entrega.</div>
            </div>
        </div>

        <!-- Columna Central -->
        <div class="hero-centro">
            <h1>Gestiona préstamos junto a tu equipo</h1>
            <p class="subtitulo">
                Controla la disponibilidad en tiempo real, evita préstamos duplicados y conserva el historial completo del departamento.
            </p>

            <a href="prestamos.php" class="btn-naranja-full">Ir a Control de Préstamos</a>

            <p class="nota-roles">
                Sesión iniciada como <strong><?= htmlspecialchars($nombreUsuario) ?></strong> · Perfil: <span><?= htmlspecialchars($rolUsuario) ?></span>
            </p>
        </div>

        <!-- Columna Derecha -->
        <div class="hero-visual-der">
            <div class="mockup-dispositivo">
                <?php if ($destacado): ?>
                    <div class="mockup-header">
                        <span>PRÉSTAMO <?= strtoupper(htmlspecialchars($destacado['estado'])) ?></span>
                        <span style="color:#FC4C02;">● EN CURSO</span>
                    </div>
                    <div class="mockup-metrica">
                        <div style="font-size:0.72rem; color:#a1a1aa; text-transform:uppercase;">
                            Folio #<?= $destacado['id_prestamo'] ?> · <?= htmlspecialchars($destacado['equipo'] ?? 'Equipo') ?>
                        </div>
                        <div class="tiempo"><?= htmlspecialchars($destacado['codigo_inventario'] ?? 'S/C') ?></div>
                        <div style="font-size:0.78rem; color:#d4d4d8; margin-top:0.25rem;">
                            Límite: <?= date('d/m/Y H:i', strtotime($destacado['fecha_estimada'])) ?>
                        </div>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:#475569;">
                        <span>Control: <strong><?= htmlspecialchars($destacado['numero_control']) ?></strong></span>
                        <span style="color:#16a34a; font-weight:700;"><?= htmlspecialchars($destacado['condicion_entrega'] ?: 'Buen estado') ?></span>
                    </div>
                <?php else: ?>
                    <div class="mockup-header">
                        <span>ESTADO DEL DEPARTAMENTO</span>
                        <span style="color:#16a34a;">● AL DÍA</span>
                    </div>
                    <div class="mockup-metrica">
                        <div style="font-size:0.72rem; color:#a1a1aa; text-transform:uppercase;">Sin pendientes</div>
                        <div class="tiempo"><?= $disponibles ?> DISP.</div>
                        <div style="font-size:0.78rem; color:#d4d4d8; margin-top:0.25rem;">Todos los equipos en resguardo</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Resumen Operativo -->
    <div class="seccion-operativa">
        <main class="contenedor" style="margin-top:0;">
            <section class="grid-kpi">
                <div class="kpi-card">
                    <h3>Total de Equipos</h3>
                    <div class="numero"><?= $totalEquipos ?></div>
                </div>
                <div class="kpi-card verde">
                    <h3>Disponibles</h3>
                    <div class="numero"><?= $disponibles ?></div>
                </div>
                <div class="kpi-card">
                    <h3>En Préstamo Activo</h3>
                    <div class="numero"><?= $enPrestamo ?></div>
                </div>
                <div class="kpi-card amarillo">
                    <h3>En Mantenimiento</h3>
                    <div class="numero"><?= $enMantenimiento ?></div>
                </div>
                <div class="kpi-card rojo">
                    <h3>Préstamos Vencidos</h3>
                    <div class="numero"><?= $vencidos ?></div>
                </div>
            </section>

            <section class="panel" style="margin-bottom:0;">
                <h2>Préstamos Activos Recientes</h2>
                <div class="tabla-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Folio</th>
                                <th>Código Equipo</th>
                                <th>Equipo</th>
                                <th>Solicitante</th>
                                <th>Fecha Préstamo</th>
                                <th>Devolución Estimada</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($prestamosRecientes) === 0): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:1.5rem; color:#606065;">
                                        No hay préstamos activos o atrasados en este momento.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($prestamosRecientes as $pr): ?>
                                    <tr>
                                        <td>#<?= $pr['id_prestamo'] ?></td>
                                        <td><?= htmlspecialchars($pr['codigo_inventario'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($pr['equipo'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($pr['solicitante']) ?> (<?= htmlspecialchars($pr['numero_control']) ?>)</td>
                                        <td><?= date('Y-m-d H:i', strtotime($pr['fecha_prestamo'])) ?></td>
                                        <td><?= date('Y-m-d H:i', strtotime($pr['fecha_estimada'])) ?></td>
                                        <td>
                                            <span class="badge <?= $pr['estado'] === 'atrasado' ? 'badge-atrasado' : 'badge-activo' ?>">
                                                <?= htmlspecialchars($pr['estado']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Footer en columnas -->
    <footer class="footer-strava">
        <div class="footer-grid">
            <div>
                <div style="color:#FC4C02; font-weight:900; font-size:1.1rem; margin-bottom:0.5rem;">SOPORTE TI | PRÉSTAMOS</div>
                <p style="font-size:0.84rem; color:#606065;">Departamento de Soporte y Servicios Tecnológicos · Taller de Base de Datos.</p>
            </div>
            <div>
                <h4>Módulos</h4>
                <ul>
                    <li><a href="equipos.php">Equipos y Categorías</a></li>
                    <li><a href="solicitantes.php">Usuarios / Solicitantes</a></li>
                    <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
                </ul>
            </div>
            <div>
                <h4>Seguimiento</h4>
                <ul>
                    <li><a href="consultas.php">Equipos disponibles</a></li>
                    <li><a href="consultas.php">Préstamos vencidos</a></li>
                    <li><a href="consultas.php">Historial por equipo</a></li>
                </ul>
            </div>
            <div>
                <h4>Plataforma</h4>
                <ul>
                    <li><a href="index.php">Linux · Apache · PHP 8</a></li>
                    <li><a href="index.php">MariaDB Relacional</a></li>
                    <li><a href="index.php">Diseño Web Responsivo</a></li>
                </ul>
            </div>
        </div>
    </footer>

    <script src="js/app.js"></script>
</body>
</html>