<?php
/**
 * marcaje.php — Simulación de lectura RFID (HU 8, 16)
 *
 * TODO (backend):
 *   - El input #codigoRfid debe enviarse (por AJAX o form POST) a registrar_marcaje.php,
 *     que identifica al aprendiz por su código, decide si es entrada o salida, y hace
 *     INSERT/UPDATE en `ingresos`, comparando contra `horarios` para fijar el estado
 *     (a_tiempo / retardo / salida_anticipada).
 *   - El listado "Últimos marcajes" debe poblarse con los registros más recientes del día.
 */
$rolActivo     = $_GET['rol'] ?? 'aprendiz';
$nombreUsuario = 'Laura Martínez';
$paginaActiva  = 'marcaje';
$tituloPagina  = 'Marcaje RFID';
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
                <div class="eyebrow">Control</div>
                <h1 class="h4 fw-bold mb-0">Marcaje de ingreso / salida</h1>
                <p class="text-suave mb-0">Acerca el carné al lector o digita el código RFID manualmente.</p>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="rfid-reader text-center">
                        <div class="rfid-scan-circle mb-3">
                            <i class="bi bi-wifi"></i>
                        </div>
                        <h2 class="h5 fw-bold text-white mb-1">Esperando lectura...</h2>
                        <p style="color:rgba(255,255,255,.7);">Acerca tu carné RFID al lector</p>

                        <!-- TODO (backend): form / AJAX -> registrar_marcaje.php -->
                        <form class="d-flex gap-2 justify-content-center mt-4" style="max-width:320px; margin:0 auto;">
                            <input type="text" class="form-control" placeholder="Ingresar código manualmente" id="codigoRfid">
                            <button class="btn btn-sena" type="submit"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card p-3 h-100">
                        <h2 class="h6 fw-bold mb-3">Último marcaje registrado</h2>
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:var(--bg-app);">
                            <span class="avatar-circ" style="width:52px;height:52px; font-size:1.1rem;">LM</span>
                            <div class="flex-grow-1">
                                <div class="fw-bold">Laura Martínez</div>
                                <div class="text-suave" style="font-size:.82rem;">Ficha 2589451 · ADSO</div>
                            </div>
                            <span class="badge-estado badge-a-tiempo">Entrada · 6:58 a.m.</span>
                        </div>

                        <h2 class="h6 fw-bold mt-4 mb-3">Últimos marcajes de hoy</h2>
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span><i class="bi bi-arrow-down-circle text-success me-2"></i>Julián Rojas</span>
                                <span class="text-suave" style="font-size:.82rem;">7:12 a.m.</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                <span><i class="bi bi-arrow-up-circle text-primary me-2"></i>Diego Peña (salida)</span>
                                <span class="text-suave" style="font-size:.82rem;">12:40 p.m.</span>
                            </li>
                            <li class="d-flex justify-content-between align-items-center py-2">
                                <span><i class="bi bi-arrow-down-circle text-success me-2"></i>Laura Martínez</span>
                                <span class="text-suave" style="font-size:.82rem;">6:58 a.m.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'includes/scripts.php'; ?>
</body>
</html>
