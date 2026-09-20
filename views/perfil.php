<?php
// La sesión ya fue verificada por PaginaControlador::mostrar() antes de
// llegar aquí (ver index.php). No hace falta repetir el chequeo.

$rolActivo = $_SESSION['rol'] ?? 'Aprendiz';
$nombreUsuario =$_SESSION['nombre'] ?? '';
$paginaActiva  = 'perfil';
$tituloPagina  = 'Mi perfil';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<?php include 'includes/head.php'; ?>
</head>
<body>
<div class="app-shell">
    <?php include 'includes/sidebar.php'; ?>
    <div class="app-main">
        <?php include 'includes/topbar.php'; ?>
        <main class="app-content">
            <div class="page-header">
                <div class="eyebrow">Cuenta</div>
                <h1 class="h4 fw-bold mb-0">Mi perfil</h1>
                <p class="text-suave mb-0">Mi Informacion Personal</p>
            </div>

            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card p-4 text-center">
                        <span class="avatar-circ mx-auto mb-3" style="width:84px;height:84px;font-size:1.8rem;">
                            <?= strtoupper(substr($nombreUsuario,0,1)) ?>
                        </span>
                        <h2 class="h6 fw-bold mb-0"><?= htmlspecialchars($nombreUsuario) ?></h2>
                        <p class="text-suave mb-3"><?= $rolActivo ?></p>
                    
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card p-4">
                        <!-- Se eliminó la etiqueta <form> ya que ahora es solo visualización -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">Nombre</label>
                                <p class="fs-6 fw-medium text-dark"><?= htmlspecialchars($nombreUsuario) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">Apellido</label>
                                <p class="fs-6 fw-medium text-dark"><?php echo $_SESSION["apellidos"] ?? ''; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">Numero Documento</label>
                                <p class="fs-6 fw-medium text-dark"><?php echo $_SESSION["Documento"] ?? ''; ?></p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted mb-1">Correo electrónico</label>
                                <p class="fs-6 fw-medium text-dark"><?php echo $_SESSION["Correo"] ?? ''; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
</body>
</html>