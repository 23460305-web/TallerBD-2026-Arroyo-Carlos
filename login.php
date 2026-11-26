<?php
session_start();
require_once 'config/conexion.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identificador = trim($_POST['identificador'] ?? '');
    $password      = $_POST['password'] ?? '';

    if ($identificador === '' || $password === '') {
        $error = 'Ingresa tu correo o número de control y tu contraseña.';
    } else {
        $sql = "SELECT id_usuario, numero_control, nombre, apellidos, correo, password_hash, rol, activo 
                FROM usuarios 
                WHERE correo = :id1 OR numero_control = :id2 
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id1' => $identificador, ':id2' => $identificador]);
        $usuario = $stmt->fetch();

        if ($usuario && (int)$usuario['activo'] === 1 && password_verify($password, $usuario['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['id_usuario']      = $usuario['id_usuario'];
            $_SESSION['numero_control']  = $usuario['numero_control'];
            $_SESSION['nombre_completo'] = $usuario['nombre'] . ' ' . $usuario['apellidos'];
            $_SESSION['rol']             = $usuario['rol'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Credenciales incorrectas o cuenta inactiva.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Control de Préstamos TI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h4 class="text-center fw-bold mb-1">Inventario y Préstamos TI</h4>
                        <p class="text-center text-muted small mb-4">Acceso al sistema (db_inventario-03)</p>

                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="login.php" autocomplete="off">
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Correo institucional o No. Control</label>
                                <input type="text" name="identificador" class="form-control" placeholder="ej. roberto.gomez@institucion.edu.mx" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Contraseña</label>
                                <input type="password" name="password" class="form-control" placeholder="••••••" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-semibold">Ingresar</button>
                        </form>

                        <hr class="my-3">
                        <div class="small text-muted">
                            <strong>Accesos de prueba (clave: <code>123456</code>):</strong><br>
                            • Admin: <code>roberto.gomez@institucion.edu.mx</code><br>
                            • Encargado: <code>mariana.salgado@institucion.edu.mx</code><br>
                            • Solicitante: <code>20460115</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>