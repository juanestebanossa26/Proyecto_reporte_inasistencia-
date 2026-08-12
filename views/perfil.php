<?php
/**
 * perfil.php — Perfil del usuario autenticado
 * TODO (backend): precargar datos desde `usuarios` + tabla de rol correspondiente;
 * el formulario debe enviarse a actualizar_perfil.php
 */
$rolActivo     = $_GET['rol'] ?? 'admin';
$nombreUsuario = 'Juan Camilo Vanegas';
$paginaActiva  = 'perfil';
$tituloPagina  = 'Mi perfil';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include '../includes/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php include '../includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include '../includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Cuenta</div>
                <h1 class="h4 fw-bold mb-0">Mi perfil</h1>
                <p class="text-suave mb-0">Actualiza tu información personal y contraseña.</p>
            </div>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card p-4 text-center">
                        <span class="avatar-circ mx-auto mb-3" style="width:84px;height:84px;font-size:1.8rem;">
                            <?= strtoupper(substr($nombreUsuario,0,1)) ?>
                        </span>
                        <h2 class="h6 fw-bold mb-0"><?= htmlspecialchars($nombreUsuario) ?></h2>
                        <p class="text-suave mb-3"><?= ucfirst($rolActivo) ?></p>
                        <button class="btn btn-outline-sena btn-sm">Cambiar foto</button>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card p-4">
                        <!-- TODO (backend): action="actualizar_perfil.php" method="POST" -->
                        <form>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre completo</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($nombreUsuario) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" value="juan.vanegas@sena.edu.co">
                                </div>
                            </div>
                            <hr class="my-4">
                            <h2 class="h6 fw-bold mb-3">Cambiar contraseña</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nueva contraseña</label>
                                    <input type="password" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirmar contraseña</label>
                                    <input type="password" class="form-control">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-sena mt-4">Guardar cambios</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../includes/scripts.php'; ?>
</body>
</html>
