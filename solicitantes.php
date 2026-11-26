<?php
require_once 'includes/auth.php';
require_once 'config/conexion.php';

$idUsuarioActual = (int) ($_SESSION['id_usuario'] ?? 0);
$nombreUsuario   = $_SESSION['nombre_completo'] ?? $_SESSION['nombre'] ?? 'Usuario';
$rolUsuario      = $_SESSION['rol'] ?? 'solicitante';
$esAdmin         = (strtolower($rolUsuario) === 'administrador');
$puedeGestionar  = in_array(strtolower($rolUsuario), ['administrador', 'encargado']);

$mensaje = '';
$tipoAlerta = '';

// 1. Procesar acciones POST (Crear, Editar o Cambiar estado activo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeGestionar) {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $rolAsignar = $esAdmin ? ($_POST['rol'] ?? 'solicitante') : 'solicitante';
            $passPlano  = $_POST['password'] ?? '123456';
            $passHash   = password_hash($passPlano, PASSWORD_DEFAULT);

            $sql = "INSERT INTO usuarios (numero_control, nombre, apellidos, correo, password_hash, telefono, rol, activo)
                    VALUES (:num_ctrl, :nombre, :apellidos, :correo, :pass, :tel, :rol, 1)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':num_ctrl'  => trim($_POST['numero_control']),
                ':nombre'    => trim($_POST['nombre']),
                ':apellidos' => trim($_POST['apellidos']),
                ':correo'    => trim($_POST['correo']),
                ':pass'      => $passHash,
                ':tel'       => trim($_POST['telefono']) ?: null,
                ':rol'       => $rolAsignar
            ]);
            $mensaje = 'Usuario registrado correctamente.';
            $tipoAlerta = 'exito';

        } elseif ($accion === 'editar') {
            $idEdit     = (int) $_POST['id_usuario'];
            $rolAsignar = $esAdmin ? ($_POST['rol'] ?? 'solicitante') : 'solicitante';
            $passNueva  = trim($_POST['password'] ?? '');

            if ($passNueva !== '') {
                $sql = "UPDATE usuarios 
                        SET numero_control = :num_ctrl, nombre = :nombre, apellidos = :apellidos, 
                            correo = :correo, telefono = :tel, rol = :rol, password_hash = :pass
                        WHERE id_usuario = :id";
                $params = [
                    ':num_ctrl'  => trim($_POST['numero_control']),
                    ':nombre'    => trim($_POST['nombre']),
                    ':apellidos' => trim($_POST['apellidos']),
                    ':correo'    => trim($_POST['correo']),
                    ':tel'       => trim($_POST['telefono']) ?: null,
                    ':rol'       => $rolAsignar,
                    ':pass'      => password_hash($passNueva, PASSWORD_DEFAULT),
                    ':id'        => $idEdit
                ];
            } else {
                $sql = "UPDATE usuarios 
                        SET numero_control = :num_ctrl, nombre = :nombre, apellidos = :apellidos, 
                            correo = :correo, telefono = :tel, rol = :rol
                        WHERE id_usuario = :id";
                $params = [
                    ':num_ctrl'  => trim($_POST['numero_control']),
                    ':nombre'    => trim($_POST['nombre']),
                    ':apellidos' => trim($_POST['apellidos']),
                    ':correo'    => trim($_POST['correo']),
                    ':tel'       => trim($_POST['telefono']) ?: null,
                    ':rol'       => $rolAsignar,
                    ':id'        => $idEdit
                ];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $mensaje = 'Datos del usuario actualizados correctamente.';
            $tipoAlerta = 'exito';

        } elseif ($accion === 'toggle_activo') {
            $idToggle = (int) $_POST['id_usuario'];
            if ($idToggle === $idUsuarioActual) {
                throw new Exception('No puedes desactivar tu propia cuenta mientras tienes la sesión abierta.');
            }
            $nuevoEstado = (int) $_POST['nuevo_estado'];
            $stmt = $pdo->prepare("UPDATE usuarios SET activo = :act WHERE id_usuario = :id");
            $stmt->execute([':act' => $nuevoEstado, ':id' => $idToggle]);
            $mensaje = $nuevoEstado === 1 ? 'Cuenta activada.' : 'Cuenta desactivada.';
            $tipoAlerta = 'alerta';
        }
    } catch (Exception $e) {
        if ($e instanceof PDOException && $e->getCode() == 23000) {
            $mensaje = 'Error: El número de control o el correo electrónico ya están registrados.';
        } else {
            $mensaje = 'Error: ' . $e->getMessage();
        }
        $tipoAlerta = 'peligro';
    }
}

// 2. Contadores para el Hero
$totalSolicitantes = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'solicitante'")->fetchColumn();
$totalEncargados   = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('encargado', 'administrador') AND activo = 1")->fetchColumn();

// 3. Listado de usuarios
$usuarios = $pdo->query("SELECT * FROM usuarios ORDER BY id_usuario DESC")->fetchAll(PDO::FETCH_ASSOC);

// Si se solicitó editar un usuario por GET
$usuarioEditar = null;
if (isset($_GET['editar']) && $puedeGestionar) {
    $stmtEd = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = :id");
    $stmtEd->execute([':id' => (int) $_GET['editar']]);
    $usuarioEditar = $stmtEd->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios y Solicitantes | Soporte TI - Control de Préstamos</title>
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

        body.pagina-solicitantes {
            background-color: var(--fondo-claro);
            color: var(--negro-texto);
        }

        .pagina-solicitantes .navbar {
            background-color: var(--negro-nav);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: none;
            padding: 0.85rem 2rem;
        }

        .pagina-solicitantes .navbar-brand {
            color: #ffffff;
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: 0.6px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
        }

        .pagina-solicitantes .nav-links a {
            color: #e4e4e7;
            font-weight: 500;
            border-radius: 0;
            padding: 0.45rem 0.75rem;
        }

        .pagina-solicitantes .nav-links a:hover {
            background-color: transparent;
            color: #ffffff;
        }

        .pagina-solicitantes .nav-links a.activo {
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

        .hero-comunidad {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 35%, rgba(252, 76, 2, 0.28) 0%, rgba(120, 53, 15, 0.25) 36%, rgba(15, 15, 18, 0.96) 75%),
                linear-gradient(115deg, #121215 0%, #231712 52%, #18181b 100%);
            color: #ffffff;
            padding: 4rem 2rem;
            border-bottom: 1px solid #27272a;
        }

        .hero-comunidad::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
        }

        .hero-comunidad-inner {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.45fr 0.85fr;
            align-items: center;
            gap: 2rem;
        }

        .hero-comunidad h1 {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -0.5px;
            color: #ffffff;
            margin-bottom: 1.1rem;
            max-width: 620px;
        }

        .hero-comunidad p {
            font-size: 1.08rem;
            color: #e4e4e7;
            margin-bottom: 1.85rem;
            max-width: 520px;
            font-weight: 500;
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

        .hero-telemetria {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 1.5rem;
        }

        .trazo-naranja {
            width: 95px;
            height: 220px;
            filter: drop-shadow(0 0 12px rgba(252, 76, 2, 0.55));
        }

        .metricas-apiladas {
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
            text-align: right;
        }

        .metrica-item .etiqueta {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #d4d4d8;
        }

        .metrica-item .valor {
            display: block;
            font-size: 1.25rem;
            font-weight: 800;
            color: #ffffff;
            margin-top: 0.1rem;
        }

        .pagina-solicitantes .contenedor {
            margin-top: 2rem;
        }

        .pagina-solicitantes .panel {
            border: 1px solid #e4e4e7;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .pagina-solicitantes .panel h2 {
            color: var(--negro-texto);
        }

        .pagina-solicitantes input:focus,
        .pagina-solicitantes select:focus {
            border-color: var(--naranja-brand);
        }

        .pagina-solicitantes .btn-primario {
            background-color: var(--naranja-brand);
            color: #ffffff;
            border-radius: 3px;
        }

        .pagina-solicitantes .btn-primario:hover {
            background-color: var(--naranja-hover);
        }

        .pagina-solicitantes th {
            color: var(--negro-texto);
            border-bottom: 2px solid #e4e4e7;
        }

        .alerta-msg {
            padding: 0.9rem 1.2rem;
            border-radius: 4px;
            margin-bottom: 1.25rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        .alerta-exito { background: #dcfce7; border-color: #16a34a; color: #166534; }
        .alerta-alerta { background: #fef9c3; border-color: #eab308; color: #854d0e; }
        .alerta-peligro { background: #fee2e2; border-color: #dc2626; color: #991b1b; }

        @media (max-width: 920px) {
            .nav-acciones-der { display: none; }
            .hero-comunidad-inner { grid-template-columns: 1fr; }
            .hero-comunidad h1 { font-size: 2.2rem; }
            .hero-telemetria { justify-content: flex-start; padding-top: 0.5rem; }
            .metricas-apiladas { flex-direction: row; flex-wrap: wrap; text-align: left; gap: 1.5rem; }
            .trazo-naranja { height: 110px; width: 60px; }
        }
    </style>
</head>
<body class="pagina-solicitantes">
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
            <li><a href="solicitantes.php" class="activo">Usuarios / Solicitantes</a></li>
            <li><a href="prestamos.php">Préstamos y Devoluciones</a></li>
            <li><a href="consultas.php">Consultas y Reportes</a></li>
        </ul>

        <div class="nav-acciones-der">
            <a href="#form-registro-usuario" class="btn-nav-naranja">Registrar alta</a>
            <a href="logout.php" class="btn-nav-outline-dark">Cerrar sesión</a>
        </div>
    </nav>

    <!-- Hero estilo editorial con trazo naranja y métricas -->
    <header class="hero-comunidad">
        <div class="hero-comunidad-inner">
            <div>
                <h1>Gestiona con orden a cada solicitante y encargado</h1>
                <p>Control de solicitantes, encargados de entrega y administradores mediante matrícula o número de empleado.</p>
                <a href="#form-registro-usuario" class="btn-hero-solid">Registrar usuario</a>
            </div>

            <div class="hero-telemetria" aria-hidden="true">
                <svg class="trazo-naranja" viewBox="0 0 100 220" fill="none">
                    <path d="M25 15 C65 15, 75 55, 45 85 C15 115, 80 135, 50 170 C30 192, 65 205, 85 205"
                          stroke="#FC4C02" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>

                <div class="metricas-apiladas">
                    <div class="metrica-item">
                        <span class="etiqueta">Solicitantes</span>
                        <span class="valor"><?= $totalSolicitantes ?> REG.</span>
                    </div>
                    <div class="metrica-item">
                        <span class="etiqueta">Encargados</span>
                        <span class="valor"><?= $totalEncargados ?> ACTIVOS</span>
                    </div>
                    <div class="metrica-item">
                        <span class="etiqueta">Validación</span>
                        <span class="valor">PDO · TI</span>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="contenedor">
        <?php if ($mensaje): ?>
            <div class="alerta-msg alerta-<?= $tipoAlerta ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($puedeGestionar): ?>
        <section class="panel" id="form-registro-usuario">
            <h2><?= $usuarioEditar ? 'Editar Usuario: ' . htmlspecialchars($usuarioEditar['nombre'] . ' ' . $usuarioEditar['apellidos']) : 'Registrar Nuevo Usuario / Solicitante' ?></h2>
            <form action="solicitantes.php" method="POST">
                <input type="hidden" name="accion" value="<?= $usuarioEditar ? 'editar' : 'crear' ?>">
                <?php if ($usuarioEditar): ?>
                    <input type="hidden" name="id_usuario" value="<?= $usuarioEditar['id_usuario'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-grupo">
                        <label for="numero_control">No. Control / No. Empleado *</label>
                        <input type="text" id="numero_control" name="numero_control" placeholder="21460234 o EMP-102" required maxlength="20"
                               value="<?= htmlspecialchars($usuarioEditar['numero_control'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="nombre">Nombre(s) *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Laura Sofía" required maxlength="100"
                               value="<?= htmlspecialchars($usuarioEditar['nombre'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="apellidos">Apellidos *</label>
                        <input type="text" id="apellidos" name="apellidos" placeholder="Ramírez Ortega" required maxlength="100"
                               value="<?= htmlspecialchars($usuarioEditar['apellidos'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="correo">Correo Institucional *</label>
                        <input type="email" id="correo" name="correo" placeholder="laura.ramirez@institucion.edu.mx" required maxlength="120"
                               value="<?= htmlspecialchars($usuarioEditar['correo'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="telefono">Teléfono de Contacto</label>
                        <input type="tel" id="telefono" name="telefono" placeholder="3121234567" maxlength="15"
                               value="<?= htmlspecialchars($usuarioEditar['telefono'] ?? '') ?>">
                    </div>
                    <div class="form-grupo">
                        <label for="rol">Rol en el Sistema *</label>
                        <select id="rol" name="rol" required <?= !$esAdmin ? 'disabled' : '' ?>>
                            <?php $rolSel = $usuarioEditar['rol'] ?? 'solicitante'; ?>
                            <option value="solicitante" <?= $rolSel === 'solicitante' ? 'selected' : '' ?>>Solicitante</option>
                            <?php if ($esAdmin): ?>
                                <option value="encargado" <?= $rolSel === 'encargado' ? 'selected' : '' ?>>Encargado de Préstamos</option>
                                <option value="administrador" <?= $rolSel === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-grupo">
                        <label for="password_hash"><?= $usuarioEditar ? 'Nueva Contraseña (opcional)' : 'Contraseña de Acceso *' ?></label>
                        <input type="password" id="password_hash" name="password"
                               placeholder="<?= $usuarioEditar ? 'Dejar vacío para mantener la actual' : 'Contraseña inicial' ?>"
                               <?= $usuarioEditar ? '' : 'required' ?>>
                    </div>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn btn-primario"><?= $usuarioEditar ? 'Guardar Cambios' : 'Registrar Usuario' ?></button>
                    <?php if ($usuarioEditar): ?>
                        <a href="solicitantes.php" class="btn btn-secundario">Cancelar Edición</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-secundario">Limpiar</button>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <section class="panel">
            <h2>Padrón de Usuarios Registrados</h2>
            <div class="barra-filtro">
                <input type="text" placeholder="Buscar por matrícula, nombre o rol..." data-filtro-tabla="tablaUsuarios">
            </div>
            <div class="tabla-responsive">
                <table id="tablaUsuarios">
                    <thead>
                        <tr>
                            <th>No. Control</th>
                            <th>Nombre Completo</th>
                            <th>Correo</th>
                            <th>Teléfono</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <?php if ($puedeGestionar): ?>
                                <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($usuarios) === 0): ?>
                            <tr>
                                <td colspan="<?= $puedeGestionar ? 7 : 6 ?>" style="text-align:center; padding:1.5rem;">
                                    No hay usuarios registrados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td><?= htmlspecialchars($u['numero_control']) ?></td>
                                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                                    <td><?= htmlspecialchars($u['correo']) ?></td>
                                    <td><?= htmlspecialchars($u['telefono'] ?: '-') ?></td>
                                    <td style="text-transform:capitalize;"><?= htmlspecialchars($u['rol']) ?></td>
                                    <td>
                                        <?php if ((int)$u['activo'] === 1): ?>
                                            <span class="badge badge-disponible">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-atrasado">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($puedeGestionar): ?>
                                        <td style="white-space:nowrap;">
                                            <a href="solicitantes.php?editar=<?= $u['id_usuario'] ?>#form-registro-usuario" class="btn btn-secundario btn-sm">Editar</a>
                                            <?php if ((int)$u['id_usuario'] !== $idUsuarioActual): ?>
                                                <form action="solicitantes.php" method="POST" style="display:inline;">
                                                    <input type="hidden" name="accion" value="toggle_activo">
                                                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="<?= (int)$u['activo'] === 1 ? 0 : 1 ?>">
                                                    <button type="submit" class="btn <?= (int)$u['activo'] === 1 ? 'btn-alerta' : 'btn-exito' ?> btn-sm">
                                                        <?= (int)$u['activo'] === 1 ? 'Desactivar' : 'Activar' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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